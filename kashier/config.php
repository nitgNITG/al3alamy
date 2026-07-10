<?php
/**
 * Kashier Gateway — merchant credentials.
 * Fill in the values from your Kashier merchant dashboard:
 * https://merchant.kashier.io → Developers → API Keys
 */
defined('MOODLE_INTERNAL') || die();

// Merchant ID (starts with MID_...)
define('KASHIER_MERCHANT_ID', 'MID_xxxxxxxxxx');

// Hash / Secret key (used for HMAC-SHA256 signature)
define('KASHIER_HASH_KEY', 'YOUR_HASH_KEY_HERE');

// Kashier checkout base URL
define('KASHIER_CHECKOUT_URL', 'https://checkout.kashier.io/');

// Currency
define('KASHIER_CURRENCY', 'EGP');

/**
 * Build the HMAC-SHA256 hash required by Kashier.
 *
 * Kashier signature string:
 *   ?merchantId={MID}&orderId={orderId}&amount={amount}&currency={currency}
 *
 * @param string $order_id
 * @param string $amount   Decimal string e.g. "250.00"
 * @return string  lowercase hex HMAC
 */
function kashier_build_hash(string $order_id, string $amount): string {
    $message = '?merchantId=' . KASHIER_MERCHANT_ID
             . '&orderId='    . $order_id
             . '&amount='     . $amount
             . '&currency='   . KASHIER_CURRENCY;
    return hash_hmac('sha256', $message, KASHIER_HASH_KEY);
}

/**
 * Build the full Kashier checkout redirect URL.
 *
 * @param string $order_id        Unique order reference
 * @param float  $amount          Amount in EGP
 * @param string $redirect_url    Where Kashier should redirect after payment
 * @param string $webhook_url     Server-to-server callback
 * @param string $description     Order description (shown on checkout page)
 * @param string $allowed_methods Comma-separated: "card,wallet,valu,…" (empty = all)
 * @return string  Full checkout URL to redirect the user to
 */
function kashier_checkout_url(
    string $order_id,
    float  $amount,
    string $redirect_url,
    string $webhook_url  = '',
    string $description  = '',
    string $allowed_methods = ''
): string {
    $amount_str = number_format($amount, 2, '.', '');
    $hash       = kashier_build_hash($order_id, $amount_str);

    $params = [
        'merchantId'       => KASHIER_MERCHANT_ID,
        'orderId'          => $order_id,
        'amount'           => $amount_str,
        'currency'         => KASHIER_CURRENCY,
        'hash'             => $hash,
        'merchantRedirect' => $redirect_url,
        'redirectMethod'   => 'get',
        'display'          => 'ar',
        'enabledPaymentMethods' => 'card,wallet',
    ];

    if ($webhook_url) {
        $params['serverWebhook'] = $webhook_url;
    }
    if ($description) {
        $params['description'] = $description;
    }
    if ($allowed_methods) {
        $params['enabledPaymentMethods'] = $allowed_methods;
    }

    return KASHIER_CHECKOUT_URL . '?' . http_build_query($params);
}

/**
 * Verify the HMAC hash returned by Kashier on the callback redirect.
 *
 * @param string $order_id
 * @param string $amount
 * @param string $received_hash  Hash sent back by Kashier in GET params
 * @return bool
 */
function kashier_verify_hash(string $order_id, string $amount, string $received_hash): bool {
    $expected = kashier_build_hash($order_id, $amount);
    return hash_equals($expected, strtolower($received_hash));
}
