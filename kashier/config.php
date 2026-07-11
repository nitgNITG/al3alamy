<?php
/**
 * kashier/config.php — Kashier gateway helpers (two-account setup).
 *
 * TWO separate Kashier merchant accounts are supported:
 *
 *   'manager' — registration-code purchases (money → admin/school account)
 *               order_id prefix: codes-
 *
 *   'student' — video / lesson purchases + wallet deposits
 *               order_id prefixes: vid-  dep-
 *
 * Credentials are read from $CFG->dirroot/.env — never hardcoded here.
 * See .env.example for the required keys.
 *
 * Public API
 * ──────────
 *   kashier_account(string $type): array          — get credentials for 'manager'|'student'
 *   kashier_account_for_order(string $order_id)   — auto-detect account from order prefix
 *   kashier_build_hash(string $oid, string $amt, string $type): string
 *   kashier_verify_hash(string $oid, string $amt, string $hash, string $type): bool
 *   kashier_checkout_url(string $oid, float $amt, string $redirect, string $webhook,
 *                        string $desc, string $methods, string $type): string
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/env.php');
kashier_load_env();

// ── Shared constants ──────────────────────────────────────────────────────────

define('KASHIER_CURRENCY',     getenv('KASHIER_CURRENCY')     ?: 'EGP');
define('KASHIER_CHECKOUT_URL', getenv('KASHIER_CHECKOUT_URL') ?: 'https://checkout.kashier.io/');

// ── Account registry ──────────────────────────────────────────────────────────

/**
 * Returns the credentials array for the requested account type.
 *
 * @param string $type  'manager' or 'student'
 * @return array{merchant_id: string, hash_key: string}
 * @throws \coding_exception  if .env keys are missing
 */
function kashier_account(string $type): array {
    $type = strtolower(trim($type));

    $map = [
        'manager' => [
            'merchant_id' => getenv('KASHIER_MANAGER_MERCHANT_ID'),
            'hash_key'    => getenv('KASHIER_MANAGER_HASH_KEY'),
        ],
        'student' => [
            'merchant_id' => getenv('KASHIER_STUDENT_MERCHANT_ID'),
            'hash_key'    => getenv('KASHIER_STUDENT_HASH_KEY'),
        ],
    ];

    if (!isset($map[$type])) {
        throw new \coding_exception("kashier_account(): unknown type '$type'. Use 'manager' or 'student'.");
    }

    $creds = $map[$type];

    if (empty($creds['merchant_id']) || empty($creds['hash_key'])) {
        throw new \coding_exception(
            "Kashier '$type' credentials missing in .env. " .
            "Set KASHIER_" . strtoupper($type) . "_MERCHANT_ID and KASHIER_" . strtoupper($type) . "_HASH_KEY."
        );
    }

    return $creds;
}

/**
 * Auto-detect the account type from an order_id prefix.
 *
 *   codes- → manager
 *   vid-   → student
 *   dep-   → student
 *
 * @param string $order_id
 * @return string  'manager' or 'student'
 */
function kashier_account_for_order(string $order_id): string {
    if (strpos($order_id, 'codes-') === 0) return 'manager';
    return 'student'; // vid- , dep- , or anything else
}

// ── Crypto helpers ────────────────────────────────────────────────────────────

/**
 * Build the HMAC-SHA256 hash required by Kashier for a given account.
 *
 * @param string $order_id
 * @param string $amount    Decimal string e.g. "250.00"
 * @param string $type      'manager' | 'student'
 * @return string  lowercase hex HMAC
 */
function kashier_build_hash(string $order_id, string $amount, string $type = 'student'): string {
    $creds   = kashier_account($type);
    $message = '?merchantId=' . $creds['merchant_id']
             . '&orderId='    . $order_id
             . '&amount='     . $amount
             . '&currency='   . KASHIER_CURRENCY;
    return hash_hmac('sha256', $message, $creds['hash_key']);
}

/**
 * Verify the HMAC hash returned by Kashier on the callback/webhook.
 *
 * @param string $order_id
 * @param string $amount
 * @param string $received_hash  Hash sent back by Kashier
 * @param string $type           'manager' | 'student'  (auto-detected if omitted)
 * @return bool
 */
function kashier_verify_hash(string $order_id, string $amount, string $received_hash, string $type = ''): bool {
    if ($type === '') {
        $type = kashier_account_for_order($order_id);
    }
    $expected = kashier_build_hash($order_id, $amount, $type);
    return hash_equals($expected, strtolower($received_hash));
}

// ── Checkout URL builder ──────────────────────────────────────────────────────

/**
 * Build the full Kashier checkout redirect URL.
 *
 * @param string $order_id
 * @param float  $amount
 * @param string $redirect_url     Where Kashier redirects the browser after payment
 * @param string $webhook_url      Server-to-server callback (optional)
 * @param string $description      Shown on the checkout page (optional)
 * @param string $allowed_methods  e.g. "card,wallet" — empty = Kashier default
 * @param string $type             'manager' | 'student' (auto-detected if omitted)
 * @return string  Full checkout URL
 */
function kashier_checkout_url(
    string $order_id,
    float  $amount,
    string $redirect_url,
    string $webhook_url      = '',
    string $description      = '',
    string $allowed_methods  = '',
    string $type             = ''
): string {
    if ($type === '') {
        $type = kashier_account_for_order($order_id);
    }

    $creds      = kashier_account($type);
    $amount_str = number_format($amount, 2, '.', '');
    $hash       = kashier_build_hash($order_id, $amount_str, $type);

    $params = [
        'merchantId'             => $creds['merchant_id'],
        'orderId'                => $order_id,
        'amount'                 => $amount_str,
        'currency'               => KASHIER_CURRENCY,
        'hash'                   => $hash,
        'merchantRedirect'       => $redirect_url,
        'redirectMethod'         => 'get',
        'display'                => 'ar',
        'enabledPaymentMethods'  => $allowed_methods ?: 'card,wallet',
    ];

    if ($webhook_url)  $params['serverWebhook'] = $webhook_url;
    if ($description)  $params['description']   = $description;

    return KASHIER_CHECKOUT_URL . '?' . http_build_query($params);
}
