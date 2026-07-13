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
// Base URLs are configurable so we can point at test-api / production.
define('KASHIER_API_BASE',     rtrim(getenv('KASHIER_BASE_URL') ?: 'https://api.kashier.io', '/'));
define('KASHIER_REFUND_BASE',  rtrim(getenv('KASHIER_REFUND_BASE_URL') ?: 'https://fep.kashier.io', '/'));

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

    if (!in_array($type, ['manager', 'student'], true)) {
        throw new \coding_exception("kashier_account(): unknown type '$type'.");
    }

    // Single-account config (KASHIER_API_KEY / _SECRET_KEY / _MERCHANT_ID) is the
    // primary source and is used for every flow. The older per-account keys
    // (KASHIER_MANAGER_* / KASHIER_STUDENT_*) are only a fallback for legacy
    // deployments that still split money between two merchants.
    $creds = [
        'merchant_id' => getenv('KASHIER_MERCHANT_ID') ?: (getenv("KASHIER_{$T}_MERCHANT_ID") ?: ''),
        'api_key'     => getenv('KASHIER_API_KEY')     ?: (getenv("KASHIER_{$T}_API_KEY")     ?: ''),
        'secret_key'  => getenv('KASHIER_SECRET_KEY')  ?: (getenv("KASHIER_{$T}_SECRET_KEY")  ?: ''),
        // legacy hash key — kept for old redirect flows
        'hash_key'    => getenv('KASHIER_HASH_KEY')    ?: (getenv("KASHIER_{$T}_HASH_KEY")    ?: ''),
    ];

    if (empty($creds['merchant_id'])) {
        throw new \coding_exception("Kashier MERCHANT_ID missing in .env (checked KASHIER_MERCHANT_ID and KASHIER_{$T}_MERCHANT_ID).");
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
    string $type         = '',
    array  $customer     = []
): array {
    global $USER;

    if ($type === '') $type = kashier_account_for_order($order_id);
    $creds = kashier_account($type);

    // Kashier's Sessions API requires a customer object (despite the docs
    // marking it optional). Default to the logged-in user when not supplied.
    if (empty($customer)) {
        $customer = [
            'reference' => (string)($USER->id ?? '0'),
        ];
        if (!empty($USER->email)) {
            $customer['email'] = $USER->email;
        }
        $fullname = trim(($USER->firstname ?? '') . ' ' . ($USER->lastname ?? ''));
        if ($fullname !== '') {
            $customer['name'] = $fullname;
        }
    }

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
        'customer'            => $customer,
    ];

    if ($webhook_url) $body['serverWebhook'] = $webhook_url;
    if ($description) $body['metaData']      = ['description' => $description];

    // Diagnostic: log the outgoing request WITHOUT secrets. Confirms which
    // account/keys are in play and whether credentials are even present.
    error_log(sprintf(
        'kashier_create_session[%s] → order=%s amount=%s merchant=%s api_key(len=%d) secret(len=%d) body=%s',
        $type,
        $order_id,
        $body['amount'],
        $creds['merchant_id'] !== '' ? $creds['merchant_id'] : 'MISSING',
        strlen($creds['api_key']),
        strlen($creds['secret_key']),
        json_encode($body, JSON_UNESCAPED_SLASHES)
    ));

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

    // Diagnostic: log the raw gateway response so failures are inspectable.
    error_log(sprintf(
        'kashier_create_session[%s] ← order=%s http=%d curl_err=%s response=%s',
        $type,
        $order_id,
        $http_code,
        $curl_error !== '' ? $curl_error : 'none',
        $raw_response !== false ? $raw_response : '(false)'
    ));

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
            'api-key: '       . $creds['api_key'],
        ],
    ]);

    $raw       = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log(sprintf(
        'kashier_verify_session[%s] session=%s http=%d response=%s',
        $type, $session_id, $http_code, $raw !== false ? $raw : '(false)'
    ));

    $data = json_decode($raw, true);
    return $data['data'] ?? $data ?? [];
}

/**
 * Decide whether a verified session represents a completed payment.
 *
 * Kashier's session GET returns status values like CREATED / PENDING / PAID /
 * CAPTURED (NOT the "SUCCESS" string used by the old redirect flow), plus a
 * capturedAmount. Treat the payment as done when the status is a paid one, or
 * when the captured amount covers the order amount.
 *
 * @param array $verified  Response from kashier_verify_session()
 * @return bool
 */
function kashier_session_is_paid(array $verified): bool {
    $status = strtoupper((string)($verified['status'] ?? $verified['paymentStatus'] ?? ''));
    $paidstatuses = ['PAID', 'SUCCESS', 'CAPTURED', 'COMPLETED', 'SUCCEEDED'];
    if (in_array($status, $paidstatuses, true)) {
        return true;
    }
    $amount   = (float)($verified['amount'] ?? 0);
    $captured = (float)($verified['capturedAmount'] ?? 0);
    return $captured > 0 && $captured >= $amount;
}

/**
 * Record a not-yet-paid order so the callback can recover its session id by
 * order id alone — independent of whatever params Kashier's redirect carries.
 * The Kashier session id is parked in transaction_id until payment completes.
 */
function kashier_store_pending(string $order_id, string $session_id, int $userid, float $amount, string $type): void {
    global $DB;
    $existing = $DB->get_record('kashier_transactions', ['order_id' => $order_id]);
    if ($existing) {
        if ($existing->status === 'success') {
            return; // Never downgrade a completed order.
        }
        $existing->transaction_id = $session_id;
        $existing->user_id        = $userid;
        $existing->amount         = $amount;
        $existing->type           = $type;
        $existing->status         = 'pending';
        $DB->update_record('kashier_transactions', $existing);
        return;
    }
    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $session_id,
        'user_id'        => $userid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => $type,
        'status'         => 'pending',
        'timecreated'    => time(),
    ]);
}

/**
 * Return the stored Kashier session id for a pending order (empty if none or
 * already completed).
 */
function kashier_lookup_session_id(string $order_id): string {
    global $DB;
    $rec = $DB->get_record('kashier_transactions', ['order_id' => $order_id], 'transaction_id, status');
    return ($rec && $rec->status !== 'success') ? (string)$rec->transaction_id : '';
}

/**
 * Idempotently mark an order paid (upsert). Safe to call from both the browser
 * callback and the server webhook — the first one wins, the rest no-op.
 *
 * @return bool  true if this call transitioned the order to success (i.e. the
 *               caller should now grant access), false if already processed.
 */
function kashier_mark_success(string $order_id, string $transaction_id, int $userid, float $amount, string $type): bool {
    global $DB;
    $existing = $DB->get_record('kashier_transactions', ['order_id' => $order_id]);
    if ($existing) {
        if ($existing->status === 'success') {
            return false; // Already processed.
        }
        $existing->transaction_id = $transaction_id ?: $existing->transaction_id;
        $existing->user_id        = $userid ?: (int)$existing->user_id;
        $existing->amount         = $amount ?: (float)$existing->amount;
        $existing->type           = $type ?: $existing->type;
        $existing->status         = 'success';
        $DB->update_record('kashier_transactions', $existing);
        return true;
    }
    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'user_id'        => $userid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => $type,
        'status'         => 'success',
        'timecreated'    => time(),
    ]);
    return true;
}

/**
 * Fulfil a PAID order: enrol + group (video), wallet recharge (deposit), code
 * generation (codes), or subscription activation (sub). Idempotent and safe to
 * call from the browser callback, the server webhook, and the reconcile cron —
 * membership/enrolment are guarded and the transaction is upserted to success.
 *
 * Does NOT redirect or emit notifications; the caller decides what to show.
 *
 * @return array{type:string,valid:bool,already:bool,done:bool,userid:int,courseid:int,cmid:int,planid:int}
 */
function kashier_fulfill_order(string $order_id, string $transaction_id, float $amount): array {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/enrollib.php');
    require_once($CFG->dirroot . '/group/lib.php');

    $r = ['type' => 'unknown', 'valid' => false, 'already' => false, 'done' => false,
          'userid' => 0, 'courseid' => 0, 'cmid' => 0, 'planid' => 0];

    $already = $DB->record_exists('kashier_transactions', ['order_id' => $order_id, 'status' => 'success']);
    $r['already'] = $already;
    $parts = explode('-', $order_id);

    if (strpos($order_id, 'vid-') === 0) {
        // vid-{userid}-{courseid}-{groupid}-{cmid}-{timestamp}
        $r['type'] = 'video';
        $uid = (int)($parts[1] ?? 0); $cid = (int)($parts[2] ?? 0);
        $gid = (int)($parts[3] ?? 0); $cmid = (int)($parts[4] ?? 0);
        $r['userid'] = $uid; $r['courseid'] = $cid; $r['cmid'] = $cmid;
        if (!$uid || !$cid || !$gid) { return $r; }
        $r['valid'] = true;
        if ($already) { return $r; }

        $ctx = context_course::instance($cid);
        if (!is_enrolled($ctx, $uid)) {
            $instance = $DB->get_record('enrol', ['courseid' => $cid, 'enrol' => 'manual', 'status' => 0]);
            if ($instance) {
                $srole = $DB->get_record('role', ['shortname' => 'student']);
                enrol_get_plugin('manual')->enrol_user($instance, $uid, $srole ? (int)$srole->id : 5);
            }
        }
        if (!$DB->record_exists('groups_members', ['userid' => $uid, 'groupid' => $gid])) {
            groups_add_member($gid, $uid);
        }
        kashier_mark_success($order_id, $transaction_id, $uid, $amount, 'video');
        $r['done'] = true;
        return $r;

    } elseif (strpos($order_id, 'dep-') === 0) {
        // dep-{userid}-{amount_egp}-{timestamp}
        $r['type'] = 'deposit';
        $uid = (int)($parts[1] ?? 0);
        $r['userid'] = $uid;
        if (!$uid) { return $r; }
        $r['valid'] = true;
        if ($already) { return $r; }

        kashier_mark_success($order_id, $transaction_id, $uid, $amount, 'deposit');
        $wallet_uuid = $DB->get_field('user_wallet', 'wallet_uuid', ['user_id' => $uid]);
        if ($wallet_uuid) {
            $ch = curl_init('https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['wallet_uuid' => $wallet_uuid, 'amount' => $amount, 'description' => 'Kashier deposit']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer 8b5a0e6d266ae2c3250a98ac3a568a95', 'Content-Type: application/json'],
            ]);
            curl_exec($ch); curl_close($ch);
        }
        $r['done'] = true;
        return $r;

    } elseif (strpos($order_id, 'codes-') === 0) {
        // codes-{userid}-{count}-{timestamp}
        $r['type'] = 'codes';
        $uid = (int)($parts[1] ?? 0); $count = (int)($parts[2] ?? 0);
        $r['userid'] = $uid;
        if (!$uid || $count < 1) { return $r; }
        $r['valid'] = true;
        if ($already) { return $r; }

        kashier_mark_success($order_id, $transaction_id, $uid, $amount, 'codes');
        require_once($CFG->dirroot . '/local/registrationcodes/classes/manager.php');
        \local_registrationcodes\manager::generate_codes($count, '', null, 'kashier-order:' . $order_id, $uid);
        $r['done'] = true;
        return $r;

    } elseif (strpos($order_id, 'sub-') === 0) {
        // sub-{userid}-{planid}-{timestamp}
        $r['type'] = 'subscription';
        $uid = (int)($parts[1] ?? 0); $planid = (int)($parts[2] ?? 0);
        $r['userid'] = $uid; $r['planid'] = $planid;
        if (!$uid || !$planid) { return $r; }
        $r['valid'] = true;
        if ($already) { return $r; }

        kashier_mark_success($order_id, $transaction_id, $uid, $amount, 'subscription');
        if (class_exists('\local_subscriptions\manager')) {
            if (!\local_subscriptions\manager::has_active_subscription($uid)) {
                \local_subscriptions\manager::activate_for_user(
                    $planid, $uid, $amount, \local_subscriptions\manager::SOURCE_ONLINE, $order_id, $transaction_id);
            }
        }
        $r['done'] = true;
        return $r;
    }

    return $r;
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
