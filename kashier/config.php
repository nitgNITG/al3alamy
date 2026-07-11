<?php
/**
 * kashier/config.php — Kashier gateway helpers (Payment Sessions API).
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
 *   kashier_account(string $type): array
 *   kashier_account_for_order(string $order_id): string
 *   kashier_create_session(...): array          — creates session, returns ['sessionUrl', 'sessionId']
 *   kashier_verify_session(string $sid, string $type): array
 *   kashier_verify_hash(...)                    — kept for legacy vid-/dep- redirect flows
 *   kashier_build_hash(...)                     — kept for legacy vid-/dep- redirect flows
 *   kashier_checkout_url(...)                   — kept for legacy vid-/dep- redirect flows
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/env.php');
kashier_load_env();

// ── Shared constants ──────────────────────────────────────────────────────────

define('KASHIER_CURRENCY',     getenv('KASHIER_CURRENCY')     ?: 'EGP');
define('KASHIER_CHECKOUT_URL', 'https://checkout.kashier.io/');
define('KASHIER_API_BASE',     'https://api.kashier.io');

// ── Account registry ──────────────────────────────────────────────────────────

/**
 * Returns credentials for the requested account type.
 *
 * @param string $type  'manager' | 'student'
 * @return array{merchant_id:string, api_key:string, secret_key:string}
 */
function kashier_account(string $type): array {
    $type = strtolower(trim($type));
    $T    = strtoupper($type);

    $creds = [
        'merchant_id' => getenv("KASHIER_{$T}_MERCHANT_ID") ?: '',
        'api_key'     => getenv("KASHIER_{$T}_API_KEY")     ?: '',
        'secret_key'  => getenv("KASHIER_{$T}_SECRET_KEY")  ?: '',
        // legacy hash key — kept for old redirect flows
        'hash_key'    => getenv("KASHIER_{$T}_HASH_KEY")    ?: '',
    ];

    if (!in_array($type, ['manager', 'student'], true)) {
        throw new \coding_exception("kashier_account(): unknown type '$type'.");
    }
    if (empty($creds['merchant_id'])) {
        throw new \coding_exception("Kashier '$type' MERCHANT_ID missing in .env.");
    }

    return $creds;
}

/**
 * Auto-detect account type from order_id prefix.
 *   codes- → manager  |  vid- / dep- / anything else → student
 */
function kashier_account_for_order(string $order_id): string {
    return (strpos($order_id, 'codes-') === 0) ? 'manager' : 'student';
}

// ── Payment Sessions API ──────────────────────────────────────────────────────

/**
 * Create a Kashier Payment Session (server-to-server POST).
 *
 * @param string $order_id       Unique order reference
 * @param float  $amount         Amount in EGP
 * @param string $redirect_url   Where Kashier redirects the browser after payment
 * @param string $webhook_url    Server-to-server webhook (optional)
 * @param string $description    Shown on the checkout page (optional)
 * @param string $type           'manager' | 'student' (auto-detected if omitted)
 * @return array{sessionUrl:string, sessionId:string, raw:array}
 * @throws \Exception  if Kashier API returns an error
 */
function kashier_create_session(
    string $order_id,
    float  $amount,
    string $redirect_url,
    string $webhook_url  = '',
    string $description  = '',
    string $type         = ''
): array {
    if ($type === '') $type = kashier_account_for_order($order_id);
    $creds = kashier_account($type);

    $body = [
        'amount'              => number_format($amount, 2, '.', ''),
        'currency'            => KASHIER_CURRENCY,
        'order'               => $order_id,
        'merchantRedirect'    => $redirect_url,
        'paymentType'         => 'credit',
        'type'                => 'one-time',
        'merchantId'          => $creds['merchant_id'],
        'maxFailureAttempts'  => 3,
        'expireAt'            => gmdate('Y-m-d\TH:i:s.v\Z', time() + 3600),
        'allowedMethods'      => 'card,wallet',
    ];

    if ($webhook_url) $body['serverWebhook'] = $webhook_url;
    if ($description) $body['metaData']      = ['description' => $description];

    $ch = curl_init(KASHIER_API_BASE . '/v3/payment/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $creds['secret_key'],
            'api-key: '       . $creds['api_key'],
            'Content-Type: application/json',
        ],
    ]);

    $raw_response = curl_exec($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error   = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new \Exception("Kashier session cURL error: $curl_error");
    }

    $data = json_decode($raw_response, true);

    if ($http_code < 200 || $http_code >= 300 || empty($data)) {
        throw new \Exception(
            "Kashier session creation failed (HTTP $http_code): $raw_response"
        );
    }

    // Support both response shapes: direct or nested under 'data'
    $payload    = $data['data'] ?? $data;
    $session_url = $payload['sessionUrl'] ?? $payload['session_url'] ?? '';
    $session_id  = $payload['_id']        ?? $payload['sessionId']   ?? '';

    if (!$session_url) {
        throw new \Exception("Kashier session response missing sessionUrl: $raw_response");
    }

    return [
        'sessionUrl' => $session_url,
        'sessionId'  => $session_id,
        'raw'        => $data,
    ];
}

/**
 * Verify a payment session by calling Kashier's GET endpoint.
 * Use this in callback.php to confirm payment status server-side.
 *
 * @param string $session_id  The session _id returned when creating the session
 * @param string $type        'manager' | 'student'
 * @return array  Kashier response (check ['paymentStatus'] === 'SUCCESS')
 */
function kashier_verify_session(string $session_id, string $type): array {
    $creds = kashier_account($type);
    $url   = KASHIER_API_BASE . '/v3/payment/sessions/' . urlencode($session_id) . '/payment';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $creds['secret_key'],
        ],
    ]);

    $raw      = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($raw, true);
    return $data['data'] ?? $data ?? [];
}

// ── Legacy helpers (kept for existing vid- / dep- redirect flows) ─────────────

function kashier_build_hash(string $order_id, string $amount, string $type = 'student'): string {
    $creds   = kashier_account($type);
    $key     = $creds['hash_key'];
    $message = '?merchantId=' . $creds['merchant_id']
             . '&orderId='    . $order_id
             . '&amount='     . $amount
             . '&currency='   . KASHIER_CURRENCY;
    return hash_hmac('sha256', $message, $key);
}

function kashier_verify_hash(string $order_id, string $amount, string $received_hash, string $type = ''): bool {
    if ($type === '') $type = kashier_account_for_order($order_id);
    $expected = kashier_build_hash($order_id, $amount, $type);
    return hash_equals($expected, strtolower($received_hash));
}

function kashier_checkout_url(
    string $order_id,
    float  $amount,
    string $redirect_url,
    string $webhook_url     = '',
    string $description     = '',
    string $allowed_methods = '',
    string $type            = ''
): string {
    if ($type === '') $type = kashier_account_for_order($order_id);
    $creds      = kashier_account($type);
    $amount_str = number_format($amount, 2, '.', '');
    $hash       = kashier_build_hash($order_id, $amount_str, $type);

    $params = [
        'merchantId'            => $creds['merchant_id'],
        'orderId'               => $order_id,
        'amount'                => $amount_str,
        'currency'              => KASHIER_CURRENCY,
        'hash'                  => $hash,
        'merchantRedirect'      => $redirect_url,
        'redirectMethod'        => 'get',
        'display'               => 'ar',
        'enabledPaymentMethods' => $allowed_methods ?: 'card,wallet',
    ];
    if ($webhook_url) $params['serverWebhook'] = $webhook_url;
    if ($description) $params['description']   = $description;

    return KASHIER_CHECKOUT_URL . '?' . http_build_query($params);
}
