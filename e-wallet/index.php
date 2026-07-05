<?php
require('../config.php');
global $DB, $CFG, $USER, $PAGE, $OUTPUT;

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/e-wallet/index.php');
$PAGE->set_title('Wallet');
$PAGE->set_heading('Wallet');


// Check if user is logged in and not a guest
if (!isloggedin() || isguestuser()) {
    redirect($CFG->wwwroot . '/login/index.php');
    exit;
}

if (isset($_GET['envo'])) {
    $envo = htmlspecialchars($_GET['envo']);
    $pdfUrl = $CFG->wwwroot . '/e-wallet/invoices/invoice' . $envo . '.pdf';

    // Display an alert with the download link
    echo '<script type="text/javascript">
            alert("Your invoice is ready. You can view or download it using the link below: \n\n' . $pdfUrl . '");
            window.open("' . $pdfUrl . '", "_blank");
          </script>';
}


function upload_user_image($id)
{
    global $DB, $CFG;
    $user = $DB->get_record('user', array('id' => $id));
    $user_context = $DB->get_record('context', array('instanceid' => $id, 'contextlevel' => 30));
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    if (count($files) < 1) {
        $image = '' . $CFG->wwwroot . '/pluginfile.php/' . $user_context->id . '/user/icon/0/f1.jpg?rev=0';
    } else {
        $file = reset($files);
        unset($files);
        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
        $image = $CFG->wwwroot . '/pluginfile.php' . $path . "?rev=" . $user->picture;
    }
    return $image;
}

// API details
$platform_uuid = "17b931f8-5a3e-11ef-b921-005056472f78";
$api_key = "8b5a0e6d266ae2c3250a98ac3a568a95";

// Start output to the browser
echo $OUTPUT->header();
?>
<!-- Bootstrap already included in Moodle theme -->
<style>
    a {
        text-decoration: none !important;
    }
    .btn-primary {
        background-color: #0E504D !important;
    }
    .btn-success {
        background-color: #C7AE72 !important;
    }
    .card {
        box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
        padding: 15px;
        margin-bottom: 15px;
    }

    #card_balance {
        display: flex;
        justify-content: space-between;
        flex-direction: row-reverse;
        align-items: center;
    }

    #balance_card {
        direction: ltr;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    #svg_wallet {
        width: 80px;
    }

    @media (max-width: 768px) {
        #card_balance {
            flex-direction: column;
            text-align: center;
        }

        #svg_wallet {
            width: 60px;
        }

        .card {
            padding: 10px;
        }
    }

    @media (max-width: 576px) {
        #svg_wallet {
            width: 50px;
            display: none;
        }

        .card {
            padding: 8px;
        }
    }

    /* tooltip settings 👇 */

    .copy {
        /* button */
        --button-bg: #C7AE72;
        --button-hover-bg: #464646;
        --button-text-color: #0E504D;
        --button-hover-text-color: #8bb9fe;
        --button-border-radius: 10px;
        --button-diameter: 36px;
        --button-outline-width: 1px;
        --button-outline-color: rgb(141, 141, 141);
        /* tooltip */
        --tooltip-bg: #f4f3f3;
        --toolptip-border-radius: 4px;
        --tooltip-font-family: Menlo, Roboto Mono, monospace;
        /* 👆 this field should not be empty */
        --tooltip-font-size: 12px;
        /* 👆 this field should not be empty */
        --tootip-text-color: rgb(50, 50, 50);
        --tooltip-padding-x: 7px;
        --tooltip-padding-y: 7px;
        --tooltip-offset: 8px;
        /* --tooltip-transition-duration: 0.3s; */
        /* 👆 if you need a transition, 
  just remove the comment,
  but I didn't like the transition :| */
    }

    .copy {
        box-sizing: border-box;
        width: var(--button-diameter);
        height: var(--button-diameter);
        border-radius: var(--button-border-radius);
        background-color: var(--button-bg);
        color: var(--button-text-color);
        border: none;
        cursor: pointer;
        position: relative;
        outline: none;
    }

    .tooltip {
        position: absolute;
        opacity: 0;
        visibility: 0;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        font: var(--tooltip-font-size) var(--tooltip-font-family);
        color: var(--tootip-text-color);
        background: var(--tooltip-bg);
        padding: var(--tooltip-padding-y) var(--tooltip-padding-x);
        border-radius: var(--toolptip-border-radius);
        pointer-events: none;
        transition: all var(--tooltip-transition-duration) cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    .tooltip::before {
        content: attr(data-text-initial);
    }

    .tooltip::after {
        content: "";
        position: absolute;
        bottom: calc(var(--tooltip-padding-y) / 2 * -1);
        width: var(--tooltip-padding-y);
        height: var(--tooltip-padding-y);
        background: inherit;
        left: 50%;
        transform: translateX(-50%) rotate(45deg);
        z-index: -999;
        pointer-events: none;
    }

    .copy svg {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .checkmark {
        display: none;
    }

    /* actions */

    .copy:hover .tooltip,
    .copy:focus:not(:focus-visible) .tooltip {
        opacity: 1;
        visibility: visible;
        top: calc((100% + var(--tooltip-offset)) * -1);
    }

    .copy:focus:not(:focus-visible) .tooltip::before {
        content: attr(data-text-end);
    }

    .copy:focus:not(:focus-visible) .clipboard {
        display: none;
    }

    .copy:focus:not(:focus-visible) .checkmark {
        display: block;
    }

    .copy:hover,
    .copy:focus {
        background-color: var(--button-hover-bg);
    }

    .copy:active {
        outline: var(--button-outline-width) solid var(--button-outline-color);
    }

    .copy:hover svg {
        color: var(--button-hover-text-color);
    }
</style>
<?php
// Check if the user already has a wallet in the "user_wallet" table
$wallet = $DB->get_record('user_wallet', array('user_id' => $USER->id));

if ($wallet) {
    echo '<div id="wallet-container">';
    echo '<div class="alert alert-info" role="alert">' . get_string('loading_wallet_details', 'theme_edumy') . '</div>';
    echo '</div>';
} else {
    // No wallet yet — try to auto-create it now, then reload
    $platform_uuid = "17b931f8-5a3e-11ef-b921-005056472f78";
    $api_key       = "8b5a0e6d266ae2c3250a98ac3a568a95";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://salem-mar3y.com/e-wallet/src/api/create_wallet.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['platform_uuid' => $platform_uuid]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    $api_response = curl_exec($ch);
    curl_close($ch);

    $wallet_data = $api_response ? json_decode($api_response, true) : null;
    if (!empty($wallet_data['status']) && $wallet_data['status'] === 'success') {
        $record = new stdClass();
        $record->user_id     = $USER->id;
        $record->wallet_uuid = $wallet_data['data']['wallet_uuid'];
        $DB->insert_record('user_wallet', $record);
        // Reload so the wallet UI shows correctly
        redirect(new moodle_url('/e-wallet/'));
    } else {
        // API failed — show a gentle error, no button needed
        echo '<div class="container mt-4">';
        echo '<div class="alert alert-danger text-center" role="alert">';
        echo '<p class="mb-0">تعذّر إنشاء المحفظة تلقائياً. يرجى المحاولة مرة أخرى لاحقاً.</p>';
        echo '</div>';
        echo '</div>';
    }
}

echo $OUTPUT->footer();

?>

<!-- Bootstrap JS already included in Moodle theme -->
<!-- Modal -->
<div class="modal fade" id="rechargeModal" tabindex="-1" aria-labelledby="rechargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rechargeModalLabel"><?php echo get_string('recharge_balance', 'theme_edumy'); ?></h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="<?php echo get_string('close', 'theme_edumy'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="amount" class="form-label"><?php echo get_string('enter_balance_amount', 'theme_edumy'); ?></label>
                    <input type="number" class="form-control" id="amount" name="amount" required>
                </div>
                <button type="submit" onclick="deposit('card')" class="btn btn-success">Recharge with Visa</button>
                <button type="submit" onclick="deposit('wallet')" class="btn btn-success">Recharge with Wallet</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 600px !important;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transferModalLabel">Transfer Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- نموذج التحويل -->
                <form id="transferForm">
                    <div class="mb-3">
                        <label for="recipientUuid" class="form-label">Recipient Wallet UUID</label>
                        <input type="text" class="form-control" id="recipientUuid" placeholder="Enter wallet UUID" required>
                    </div>
                    <div class="mb-3">
                        <label for="transferAmount" class="form-label">Amount to Transfer</label>
                        <input type="number" class="form-control" id="transferAmount" placeholder="Enter amount" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Transfer</button>
                </form>
                <!-- محتوى التأكيد (سيتم إخفاءه بشكل افتراضي) -->
                <div id="confirmationContent" style="display: none;" contenteditable>
                    <div class="d-flex mb-5" style="justify-content: center;align-items: center;flex-direction: column;text-align: center;">
                        <div class="maintrans" style="max-width: 600px;">
                            <div class="your_data d-flex flex-row bg-white p-2 mb-5" style="border-radius: 5px;box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;">
                                <img style="border-radius: 5px;width: 70px;" src="<?php echo upload_user_image($USER->id); ?>">
                                <div class="d-flex flex-column w-100" style="align-items: flex-start;gap: 5px;margin-left: 1rem;">
                                    <strong><?php echo fullname($USER); ?></strong>
                                    <small id="sourceWalletUuid"><?php echo $DB->get_field('user_wallet', 'wallet_uuid', array('user_id' => $USER->id)); ?></small>
                                </div>
                            </div>
                            <div class="d-flex flex-column justify-content-center" style="align-items: center;">
                                <strong id="confirmAmount2" style="position: absolute;background: white;padding: 5px 8px;border-radius: 5px;box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;color: green;"></strong>
                                <i class="fa fa-long-arrow-down" style="font-size: x-large;color: #ccc;" aria-hidden="true"></i>
                                <i class="fa fa-long-arrow-down" style="font-size: x-large;margin-top: 17px;color: #ccc;" aria-hidden="true"></i>
                            </div>
                            <div class="recipient_data d-flex flex-row-reverse bg-white p-2 mt-4" style="border-radius: 5px;box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;">
                                <img style="border-radius: 5px;width: 70px;" id="recipientImage" src="">
                                <div class="d-flex flex-column w-100" style="align-items: flex-end;gap: 5px;margin-right: 1rem;">
                                    <strong id="recipientName"></strong>
                                    <small id="recipientAccount"></small>
                                </div>
                            </div>
                        </div>
                        <div id="transfaremessages" style="padding: 50px 20px;padding-bottom: 0;color: #9a9a9a;"></div>
                    </div>
                    <button id="confirmTransferBtn" class="btn btn-success w-100">Confirm Transfer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- create-wallet-button removed: wallet is now created automatically -->

<script>
    // PHP check for admin status
    var isAdmin = <?php echo json_encode(is_siteadmin()); ?>;

    // Translation strings from PHP
    var balance = "<?php echo get_string('balance', 'theme_edumy'); ?>";
    var recharge = "<?php echo get_string('recharge', 'theme_edumy'); ?>";
    var transfer = "<?php echo get_string('transfer', 'theme_edumy'); ?>";
    var transactions = "<?php echo get_string('transactions', 'theme_edumy'); ?>";
    var fetchWalletError = "<?php echo get_string('fetch_wallet_error', 'theme_edumy'); ?>";
    var fetchTransactionsError = "<?php echo get_string('fetch_transactions_error', 'theme_edumy'); ?>";
    var noTransactions = "<?php echo get_string('no_transactions', 'theme_edumy'); ?>";
    var latestTransactions = "<?php echo get_string('latest_transactions', 'theme_edumy'); ?>";
    var stydents = "<?php echo get_string('config_students', 'block_cocoon_course_instructor'); ?>";

    // Function to fetch wallet details using AJAX
    function fetchWalletDetails() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', './wallet_data.php', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                var container = document.getElementById('wallet-container');

                if (response.status === 'success') {
                    var data = response.data;
                    container.innerHTML = `
                    <div class="container">
                      <div class="row">
                        <div class="col-md-12 col-lg-4 mb-4">
                          <div class="card">
                            <div class="card-body">
                              <h6 class="card-subtitle mb-2 text-muted">UUID</h6>
                              <div class="d-flex flex-row-reverse">
                                  <input type="password" id="uuidField1" class="form-control" value="${data.uuid}" style="border-radius: 0px ;" readonly>
                                  <div class="append">
                                      <button class="btn btn-outline-secondary" type="button" id="toggleUuid1" style="border-radius: 0px ;">
                                          <i class="fa fa-eye"></i>
                                      </button>
                                  </div>
                              </div>
                              <br>
                              <h6 class="card-subtitle mb-2 text-muted">Platform UUID</h6>
                              <div class="d-flex flex-row-reverse">
                                  <input type="password" id="uuidField2" class="form-control" value="${data.platform_uuid}" style="border-radius: 0px ;" readonly>
                                  <div class="append">
                                      <button class="btn btn-outline-secondary" type="button" id="toggleUuid2" style="border-radius: 0px ;">
                                          <i class="fa fa-eye"></i>
                                      </button>
                                  </div>
                              </div>
                              <br>
                              <div class="d-flex justify-content-between align-items-center mb-3">
                                  <span class="status-box">Status: <strong>${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</strong></span>
                                  <span class="created-at">Created At: ${data.created_at}</span>
                              </div>
                            </div>
                          </div>
                          ${isAdmin ? `
                              <div class="card mt-4">
                                  <div class="card-body">
                                    <form class="d-flex" id="rechargeForm" method="post" action="./recharge_wallet.php">
                                        <div class="form-group">
                                            <input type="hidden" class="form-control" id="uuid" name="uuid" value="<?php echo $wallet->wallet_uuid; ?>" required>
                                        </div>
                                        <div class="form-group" style="position: absolute;">
                                            <input style="border: 3px solid #0a0a0a;" type="number" class="form-control" placeholder="Amount" id="amount" name="amount" required>
                                        </div>
                                        <button style="width: 100%;height: fit-content;background-color: #0a0a0a;text-align: right;"  type="submit" class="btn btn-primary">Ad Recharge</button>
                                    </form>
                                  </div>
                                  <div class="card-body">
                                      <h2 class="card-title">${stydents}</h2>
                                      <!-- Fetch and display all users -->
                                      <?php

                                        // Fetch all users
                                        $users = $DB->get_records('user', array('deleted' => 0), 'id DESC');

                                        if ($users) {

                                            echo '<ul class="list-group" style="height: 250px;overflow: auto;">';
                                            foreach ($users as $user) {
                                                if ($user->id == $USER->id || $user->id == 1) {
                                                    continue;
                                                }
                                                // Fetch the wallet UUID associated with the user
                                                $wallet_uuid = $DB->get_field('user_wallet', 'wallet_uuid', array('user_id' => $user->id));

                                                $background = 'badge-primary';
                                                if ($wallet_uuid) {
                                                    // Request wallet details using cURL
                                                    $api_url = 'https://salem-mar3y.com/e-wallet/src/api/get_wallet_details.php';
                                                    $authorization_token = '8b5a0e6d266ae2c3250a98ac3a568a95';

                                                    $data = array(
                                                        'wallet_uuid' => $wallet_uuid,
                                                    );

                                                    $ch = curl_init($api_url);
                                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                        'Authorization: Bearer ' . $authorization_token,
                                                        'Content-Type: application/json'
                                                    ));
                                                    curl_setopt($ch, CURLOPT_POST, true);
                                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                                                    $response = curl_exec($ch);
                                                    curl_close($ch);

                                                    $response_data = json_decode($response, true);

                                                    // Check if the response is valid and contains balance information
                                                    if ($response_data && isset($response_data['data']['balance'])) {
                                                        $balance = $response_data['data']['balance'] . " LE";
                                                        $background = 'bg-success';
                                                    } else {
                                                        $balance = "N/A"; // Handle cases where balance data is not available
                                                        $background = 'bg-info';
                                                    }
                                                } else {
                                                    $balance = "No Wallet"; // Handle cases where the user does not have a wallet
                                                }

                                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                                // عرض صورة المستخدم
                                                echo '<div class="d-flex align-items-center">';
                                                echo '<img src="' . upload_user_image($user->id) . '" alt="User Image" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; margin-right: 10px;">';
                                                echo '<div>';
                                                echo '<span class="font-weight-bold">' . fullname($user) . '</span><br>';
                                                echo '<small class="text-muted" style="font-size: 10px;">' . $user->email . '</small>';
                                                echo '</div>';
                                                echo '</div>';

                                                // عرض الرصيد والزر
                                                echo '<div class="d-flex align-items-center">';
                                                echo '<span class="badge ' . $background . ' badge-pill mr-2">' . $balance . '</span>';
                                                if ($wallet_uuid) {
                                                    echo '
                                                            <button class="copy btn btn-outline-secondary btn-sm" onclick="copyToClipboard(\'' . $wallet_uuid . '\', this)">
                                                                <span data-text-end="Copied!" data-text-initial="Copy" class="tooltip"></span>
                                                                <span>
                                                                    <svg xml:space="preserve" style="enable-background:new 0 0 512 512" viewBox="0 0 6.35 6.35" y="0" x="0" height="16" width="16" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" xmlns="http://www.w3.org/2000/svg" class="clipboard">
                                                                        <g>
                                                                            <path fill="currentColor" d="M2.43.265c-.3 0-.548.236-.573.53h-.328a.74.74 0 0 0-.735.734v3.822a.74.74 0 0 0 .735.734H4.82a.74.74 0 0 0 .735-.734V1.529a.74.74 0 0 0-.735-.735h-.328a.58.58 0 0 0-.573-.53zm0 .529h1.49c.032 0 .049.017.049.049v.431c0 .032-.017.049-.049.049H2.43c-.032 0-.05-.017-.05-.049V.843c0-.032.018-.05.05-.05zm-.901.53h.328c.026.292.274.528.573.528h1.49a.58.58 0 0 0 .573-.529h.328a.2.2 0 0 1 .206.206v3.822a.2.2 0 0 1-.206.205H1.53a.2.2 0 0 1-.206-.205V1.529a.2.2 0 0 1 .206-.206z"></path>
                                                                        </g>
                                                                    </svg>
                                                                    <svg xml:space="preserve" style="enable-background:new 0 0 512 512" viewBox="0 0 24 24" y="0" x="0" height="16" width="16" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" xmlns="http://www.w3.org/2000/svg" class="checkmark">
                                                                        <g>
                                                                            <path data-original="#000000" fill="currentColor" d="M9.707 19.121a.997.997 0 0 1-1.414 0l-5.646-5.647a1.5 1.5 0 0 1 0-2.121l.707-.707a1.5 1.5 0 0 1 2.121 0L9 14.171l9.525-9.525a1.5 1.5 0 0 1 2.121 0l.707.707a1.5 1.5 0 0 1 0 2.121z"></path>
                                                                        </g>
                                                                    </svg>
                                                                </span>
                                                            </button>';
                                                }
                                                echo '</div>';

                                                echo '</li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<p>No users found.</p>';
                                        }
                                        ?>
                                  </div>
                              </div>
                          ` : ''}
                        </div>
                        <div class="col-md-12 col-lg-8">
                          <div class="card border-success">
                              <div class="card-body" id="card_balance">
                                  <div id="balance_card">
                                    <h2 class="card-title">${balance}</h2>
                                    <p style="font-size: x-large;margin: auto 20px;" class="card-text">LE ${data.balance}</p>
                                  </div>
                                  <div id="svg_wallet">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-224c0-35.3-28.7-64-64-64L80 128c-8.8 0-16-7.2-16-16s7.2-16 16-16l368 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L64 32zM416 272a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></svg>
                                  </div>
                              </div>
                              <div class="d-flex justify-content-between" style="gap: 10px;">
                                  <button type="button" class="btn btn-success btn-lg w-100 me-2" data-toggle="modal" data-target="#rechargeModal">${recharge}</button>
                                  <button class="btn btn-primary btn-lg w-100 ms-2" data-toggle="modal" data-target="#transferModal">${transfer}</button>
                              </div>
                          </div>
                          <br>

                          <h2 class="d-flex" style="direction: ltr;color: #ccc;">${transactions}</h2>
                          <div class="d-flex flex-wrap" style="gap: 20px;text-align: center;">
                            <div class="bg-light p-3 rounded shadow-sm" style="flex: 1;">
                              <div class="card-body">
                                  <h1 class="card-title">${data.total_transactions}</h1>
                                  <p class="card-text">Total</p>
                              </div>
                            </div>
                            <div class="bg-light p-3 rounded shadow-sm" style="flex: 1;">
                              <div class="card-body">
                                  <h1 class="card-title">${data.recharge_transactions || '0'}</h1>
                                  <p class="card-text">Recharge</p>
                              </div>
                            </div>
                            <div class="bg-light p-3 rounded shadow-sm" style="flex: 1;">
                              <div class="card-body">
                                  <h1 class="card-title">${data.payment_transactions || '0'}</h1>
                                  <p class="card-text">Payment</p>
                              </div>
                            </div>
                            <div class="bg-light p-3 rounded shadow-sm" style="flex: 1;">
                              <div class="card-body">
                                  <h1 class="card-title">${data.transfer_transactions || '0'}</h1>
                                  <p class="card-text">Transfer</p>
                              </div>
                            </div>
                          </div>

                          <br>
                          <div id="transactions-container"></div>
                        </div>
                      </div>
                    </div>
                    `;

                    // Call the function to handle UUID masking for both fields
                    maskUuid('uuidField1', 'toggleUuid1');
                    maskUuid('uuidField2', 'toggleUuid2');

                    // Fetch transactions in real-time
                    fetchTransactions();
                } else {
                    container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        ${response.message}
                    </div>
                `;
                }
            } else if (xhr.readyState === 4) {
                // Handle possible errors here
                var container = document.getElementById('wallet-container');
                container.innerHTML = `
                <div class="alert alert-danger" role="alert">${fetch_wallet_error}</div>`;
            }
        };
        xhr.send();
    }

    // Function to fetch transactions in real-time
    function fetchTransactions() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', './transactions_data.php', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                var transactionsContainer = document.getElementById('transactions-container');

                if (response.status === 'success') {
                    var transactions = response.data;
                    var transactionsHtml = `<h4 class="d-flex" style="direction: ltr;">${latestTransactions}</h4><ul class="list-group" style="height: 500px; overflow: auto;">`;

                    transactions.forEach(function(transaction) {
                        let icon = ''; // Initialize the icon variable
                        let background = '';

                        // Determine the icon based on the transaction type
                        if (transaction.type === 'recharge') {
                            icon = "<i class='fa fa-credit-card' style='font-size: 24px;'></i>";
                            background = "bg-info";
                        } else if (transaction.type === 'payment') {
                            icon = "<i class='fa fa-money' style='font-size: 24px;'></i>";
                            background = "bg-success";
                        } else if (transaction.type === 'transfer') {
                            icon = "<i class='fa fa-exchange' style='font-size: 24px;'></i>";
                            background = "bg-primary;";
                        } else {
                            icon = "<i class='fa fa-info-circle'></i>"; // Default icon for unknown types
                        }

                        // Append the transaction details to the transactionsHtml
                        transactionsHtml += `
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between" style="direction: ltr;align-items: center;">
                            <div class="d-flex" style='align-items: center;'>
                              ${icon}
                              <div class='d-flex flex-column' style='align-items: flex-start;padding: 0px 10px;'>
                                <strong>${transaction.type}</strong>
                                <small style='font-size: 60% !important;'>${transaction.description} | ${transaction.created_at}</small>
                              </div>
                            </div>
                            <span class="badge ${background} rounded-pill">LE ${transaction.amount}</span> 
                            </div>
                        </li>`;
                    });

                    transactionsHtml += '</ul>';
                    transactionsContainer.innerHTML = transactionsHtml;
                } else {
                    transactionsContainer.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        No transactions found.
                    </div>`;
                }
            } else if (xhr.readyState === 4) {
                // Handle possible errors here
                transactionsContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">${fetch_transactions_error}</div>`;
            }
        };
        xhr.send();
    }

    // Fetch wallet details on page load if wallet exists
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('wallet-container')) {
            fetchWalletDetails();
        }
    });

    // Optionally, fetch transactions periodically
    setInterval(fetchTransactions, 30000); // 30 seconds

    // Function to mask and unmask the UUID
    function maskUuid(uuidFieldId, toggleUuidId) {
        const uuidField = document.getElementById(uuidFieldId);
        const toggleUuid = document.getElementById(toggleUuidId);
        const uuid = uuidField.value;
        const visiblePart = uuid.substring(0, 5); // أول 5 أرقام
        const hiddenPart = '*'.repeat(uuid.length - 5); // الجزء المخفي

        uuidField.value = visiblePart + hiddenPart;

        toggleUuid.addEventListener('click', function() {
            if (uuidField.type === 'password') {
                uuidField.type = 'text';
                uuidField.value = uuid;
            } else {
                uuidField.type = 'password';
                uuidField.value = visiblePart + hiddenPart;
            }
        });
    }

    // Optionally, fetch wallet details periodically
    setInterval(fetchWalletDetails, 30000); // 30 seconds
</script>

<script>
    // deposit function
    function deposit(type) {
        var amount = document.getElementById('amount').value;
        amount = parseInt(amount); // تحويل القيمة إلى int

        // قم بفحص إذا كان المبلغ فارغًا أو غير صالح
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount.');
            return;
        }

        // إعادة التوجيه إلى deposit.php مع تمرير المبلغ في URL
        window.location.href = '<?php $CFG->wwwroot?>/e-wallet/deposit.php?amount=' + amount + '&type=' + type;
    }
</script>

<!-- JavaScript for copying wallet UUID and updating tooltip -->
<script>
    function copyToClipboard(walletUUID, element) {
        const tempInput = document.createElement("input");
        tempInput.style.position = "absolute";
        tempInput.style.left = "-9999px";
        tempInput.value = walletUUID;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);

        // Update tooltip text to "Copied!"
        const tooltip = element.querySelector('.tooltip');
        tooltip.textContent = tooltip.getAttribute('data-text-end');

        // Change the icon from clipboard to checkmark
        element.querySelector('.clipboard').style.display = 'none';
        element.querySelector('.checkmark').style.display = 'block';
    }
</script>

<!-- جافاسكريبت للتعامل مع النموذج وتحديث محتوى الـ Modal -->
<script>
    document.getElementById('transferForm').addEventListener('submit', function(event) {
        event.preventDefault();

        var uuid = document.getElementById('recipientUuid').value;
        var amount = document.getElementById('transferAmount').value;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'get_recipient.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);

                if (response.status === 'success') {
                    document.getElementById('confirmAmount2').textContent = amount + ' LE';
                    document.getElementById('recipientName').textContent = response.username;
                    document.getElementById('recipientAccount').textContent = uuid;
                    document.getElementById('recipientImage').src = response.imgurl;

                    document.getElementById('transferForm').style.display = 'none';
                    document.getElementById('confirmationContent').style.display = 'block';
                } else {
                    alert(response.message);
                }
            }
        };
        xhr.send('uuid=' + encodeURIComponent(uuid));
    });

    document.getElementById('confirmTransferBtn').addEventListener('click', function() {
        var sourceWalletUuid = document.getElementById('sourceWalletUuid').textContent; // UUID الخاص بحساب المرسل
        var destinationWalletUuid = document.getElementById('recipientAccount').textContent; // UUID الخاص بحساب المستلم
        var amount = document.getElementById('transferAmount').value; // المبلغ الذي سيتم تحويله
        var description = 'Transfer payment'; // الوصف

        fetch('https://xmathsacademy.com/e-wallet/src/api/transfer_funds.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer 8b5a0e6d266ae2c3250a98ac3a568a95' // استبدل بالرمز المميز الخاص بك
                },
                body: JSON.stringify({
                    source_wallet_uuid: sourceWalletUuid,
                    destination_wallet_uuid: destinationWalletUuid,
                    amount: amount,
                    description: description
                })
            })
            .then(response => {
                console.log('Response status:', response.status); // تحقق من حالة الاستجابة
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // تحقق من البيانات المستلمة
                if (data.status === 'success') {

                    document.getElementById('transfaremessages').innerHTML = `
                    <video width="200" autoplay muted playsinline style="opacity: 0.4;">
                      <source src="./img/Success.mp4" type="video/mp4">
                      Your browser does not support the video tag.
                    </video>
                    <br>
                    <strong>Transfer confirmed! 
                    <br>Transaction ID: ` + data.data.transaction_uuid + `</strong>`;
                    // إعادة تحميل الصفحة بعد عرض رسالة التأكيد
                    document.getElementById('confirmTransferBtn').style.display = 'none';
                    //window.location.reload();
                } else {
                    console.error('Transfer failed: ' + data.message); // عرض رسالة الخطأ في الكونسول
                    alert('Transfer failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error); // عرض تفاصيل الخطأ في الكونسول
                alert('An error occurred while processing the transfer. Check the console for more details.');
            });
    });
</script>

