<?php
require_once('vendor/autoload.php');
require_once('TCPDF/vendor/autoload.php');
require_once(__DIR__ . '/../config.php'); // Ensure the correct path to your config file
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use TCPDF;

// Initialize HTTP client
$client = new Client();

// Securely fetch API keys using environment variables
$api_key = 'ZXlKaGJHY2lPaUpJVXpVeE1pSXNJblI1Y0NJNklrcFhWQ0o5LmV5SmpiR0Z6Y3lJNklrMWxjbU5vWVc1MElpd2ljSEp2Wm1sc1pWOXdheUk2T1Rrd056TTNMQ0p1WVcxbElqb2lhVzVwZEdsaGJDSjkuN3BRd0VQU1ZWQUR3VzZwV2JRVGFrOFZrakx4UVE0WUltZWR0bmo0aHpnTjhIRG9OT2NQRk5CNW53a3IwRTdZUG9ERWJYanhuSlBlX2dDeWU1YVJRUmc=';
$wallet_api_key = '8b5a0e6d266ae2c3250a98ac3a568a95';

// Validate GET parameters
$required_params = ['amount_cents', 'success', 'hmac', 'currency', 'order'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param])) {
        http_response_code(400);
        \core\notification::add('Invalid request parameters.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
        exit;
    }
}

// Sanitize and validate input
$transaction_id = filter_var($_GET['id'], FILTER_SANITIZE_STRING);
$amount_cents = filter_var($_GET['amount_cents'], FILTER_VALIDATE_INT);
$success = filter_var($_GET['success'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$currency = filter_var($_GET['currency'], FILTER_SANITIZE_STRING);
$order_id = filter_var($_GET['order'], FILTER_SANITIZE_STRING);

if ($amount_cents === false || $amount_cents < 0) {
    http_response_code(400);
    \core\notification::add('Invalid amount_cents value.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
    exit;
}

// Check for existing transaction
$existing_transaction = $DB->get_field('transactions', 'id', ['transaction_id' => $transaction_id]);
if ($existing_transaction) {
    \core\notification::add('Payment has already been processed.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
    exit;
}

// Proceed with transaction processing
try {
    $response = $client->post('https://accept.paymob.com/api/auth/tokens', [
        'json' => ['api_key' => $api_key]
    ]);
    $responseData = json_decode($response->getBody()->getContents(), true);
    $access_token = $responseData['token'];

    // Fetch transaction details using the access token
    $response = $client->get('https://accept.paymob.com/api/acceptance/transactions/' . $transaction_id, [
        'headers' => ['Authorization' => 'Bearer ' . $access_token]
    ]);
    $responseData = json_decode($response->getBody()->getContents(), true);

    // Validate transaction details and match with request
    if ($responseData['success'] && $responseData['billing_data']['email'] == $USER->email) {
        $amount   = $responseData['amount_cents'] / 100;
        $currency = $responseData['currency'];
        $order    = $responseData['order'];

        // ── Detect enrollment payment ──────────────────────────────────────
        $merchant_order_id = $order['merchant_order_id'] ?? '';
        $is_enrol_payment  = (strpos($merchant_order_id, 'enrol-') === 0);

        if ($is_enrol_payment) {
            // Parse: enrol-{userid}-{courseid}-{groupid}
            $parts    = explode('-', $merchant_order_id);  // ['enrol', userid, courseid, groupid]
            $pay_uid  = isset($parts[1]) ? (int)$parts[1] : 0;
            $courseid = isset($parts[2]) ? (int)$parts[2] : 0;
            $groupid  = isset($parts[3]) ? (int)$parts[3] : 0;

            // Security: logged-in user must match the intent
            if ($pay_uid !== (int)$USER->id || !$courseid || !$groupid) {
                \core\notification::add('Enrollment payment mismatch.', \core\output\notification::NOTIFY_ERROR);
                redirect(new moodle_url('/e-wallet'));
                exit;
            }

            // Record the transaction (prevent replay)
            $DB->insert_record('transactions', [
                'transaction_id' => $transaction_id,
                'user_id'        => $USER->id,
                'amount'         => $amount,
                'currency'       => $currency,
                'status'         => 'successful',
            ]);

            // ── Enroll in course via manual enrolment plugin ───────────────
            $course_context = context_course::instance($courseid);
            if (!is_enrolled($course_context, $USER)) {
                $enrol_instance = $DB->get_record('enrol', [
                    'courseid' => $courseid,
                    'enrol'    => 'manual',
                    'status'   => 0,   // ENROL_INSTANCE_ENABLED
                ]);
                if ($enrol_instance) {
                    $enrol_plugin = enrol_get_plugin('manual');
                    $student_role = $DB->get_record('role', ['shortname' => 'student']);
                    $roleid       = $student_role ? (int)$student_role->id : 5;
                    $enrol_plugin->enrol_user($enrol_instance, $USER->id, $roleid);
                } else {
                    error_log("paymob_response.php: no manual enrol instance for course $courseid");
                }
            }

            // ── Add to group ───────────────────────────────────────────────
            if (!$DB->record_exists('groups_members', ['userid' => $USER->id, 'groupid' => $groupid])) {
                groups_add_member($groupid, $USER->id);
            }

            \core\notification::add('تم الدفع والتسجيل بنجاح! Payment successful — you are now enrolled.',
                \core\output\notification::NOTIFY_SUCCESS);
            redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
            exit;
        }
        // ── End enrollment branch — continue below for wallet recharge ─────

        $invoice = [
            'Payment Status' => 'Successful',
            'Invoice ID' => $responseData['id'],
            'Amount' => $amount,
            'Currency' => $currency,
            'message' => $responseData['data']['message'],
            'Payment Method' => $responseData['source_data']['type'],
            'card_num' => $responseData['data']['card_num'],
            'card_type' => $responseData['data']['card_type'],
            'name' => $responseData['billing_data']['first_name'],
            'Order ID' => $order['id'],
            'Payment Date' => $responseData['paid_at']
        ];

        // Generate PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Company Name');
        $pdf->SetTitle('Invoice');
        $pdf->SetSubject('Payment Invoice');
        $pdf->SetKeywords('TCPDF, PDF, invoice, test, guide');

        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Invoice', 'Generated by Your Company');

        // Set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 12);

        // Prepare HTML content for PDF
        $html = '<h1>Invoice Details</h1>';
        $html .= '<table border="1" cellpadding="5">';
        foreach ($invoice as $key => $value) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Save the PDF to a file
        $pdfFilePath = __DIR__ . '/invoices/invoice' . $responseData['id'] . '.pdf';
        $pdf->Output($pdfFilePath, 'F'); // 'F' stands for file save

        // Fetch user's wallet UUID
        $wallet_uuid = $DB->get_field('user_wallet', 'wallet_uuid', ['user_id' => $USER->id]);

        if ($wallet_uuid) {
            // Request to recharge wallet
            $walletResponse = $client->request('POST', 'https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $wallet_api_key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'wallet_uuid' => $wallet_uuid,
                    'amount' => $amount,
                    'description' => 'Recharged wallet',
                ],
            ]);

            if ($walletResponse->getStatusCode() === 200) {
                // Insert transaction record in the database
                $DB->insert_record('transactions', [
                    'transaction_id' => $transaction_id,
                    'user_id' => $USER->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'successful'
                ]);

                \core\notification::add('Wallet recharged successfully.', \core\output\notification::NOTIFY_SUCCESS);
                redirect(new moodle_url('/e-wallet/?envo='.$transaction_id.'')); // Redirect after processing the shipment
            } else {
                \core\notification::add('Failed to recharge wallet.', \core\output\notification::NOTIFY_ERROR);
                redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
            }
        }
    } else {
        \core\notification::add('Payment details mismatch or invalid user email.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
    }
} catch (RequestException $e) {
    error_log('Error in transaction processing: ' . $e->getMessage());
    \core\notification::add('Error in transaction processing.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/e-wallet')); // Redirect after processing the shipment
}
