<?php
// Include necessary Moodle files and libraries
require('../config.php');
require_once($CFG->libdir . '/authlib.php');
require_once(__DIR__ . '/lib.php');
require_once('forgot_password_form.php');
require_once('set_password_form.php');
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$PAGE->set_url('/login/forgot_password.php');
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// Set up page layout and styles
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('passwordforgotten'));
$PAGE->set_heading($COURSE->fullname);

// Output page header
echo $OUTPUT->header();

// CSS styles for the form and popup
echo '<style>
#page {
   background-image: linear-gradient(to right, #00126C 0%, #00126C 100%) !important;
}
.popup {
    position: relative;
    height: fit-content;
    background: #FFFFFF;
    border-radius: 13px;
}
.form {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
    gap: 20px;
}
.icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: #ECF1FD;
    box-shadow: 0px 0.5px 0.5px #EFEFEF, 0px 1px 0.5px rgba(239, 239, 239, 0.5);
    border-radius: 5px;
}
.note {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.title {
    font-style: normal;
    font-weight: 700;
    font-size: 17px;
    line-height: 24px;
    color: #2B2B2F;
}
.subtitle {
    font-style: normal;
    font-weight: 600;
    font-size: 13px;
    line-height: 18px;
    color: #5F5D6B;
}
.input_field {
    width: 100%;
    height: 42px;
    padding: 0 0 0 12px;
    border-radius: 5px;
    outline: none;
    border: 1px solid #e5e5e5;
    filter: drop-shadow(0px 1px 0px #efefef) drop-shadow(0px 1px 0.5px rgba(239, 239, 239, 0.5));
    transition: all 0.3s cubic-bezier(0.15, 0.83, 0.66, 1);
}
.input_field:focus {
    border: 1px solid transparent;
    box-shadow: 0px 0px 0px 1px #2B2B2F;
    background-color: transparent;
}
.form button.submit {
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    padding: 10px 18px;
    gap: 10px;
    width: 100%;
    height: 42px;
    background-color: #C7AE72;
    box-shadow: 0px 0.5px 0.5px #EFEFEF, 0px 1px 0.5px rgba(239, 239, 239, 0.5);
    border-radius: 5px;
    border: 0;
    font-style: normal;
    font-weight: 600;
    font-size: 12px;
    line-height: 15px;
    color: #ffffff;
}
.logo2 {
display: none;
}
.our-log {
    background-color: #fff;
}
</style>';

// Validate email presence in Moodle's user table only if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize email input
    $email = trim(optional_param('email', '', PARAM_EMAIL));

    // Check if user with given email exists
    $user = $DB->get_record('user', array('email' => $email));

    $url = $CFG->wwwroot;

    // Parse the URL to get the host
    $host = parse_url($url, PHP_URL_HOST);

    // Extract the part before the domain extension
    $domainName = explode('.', $host)[0];


    if ($user) {
        $newPassword = rand(100000, 999999);

        // Update the user's password
        $user->password = hash_internal_user_password($newPassword);
        $DB->update_record('user', $user);

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 0;  // Disable verbose debug output
            $mail->isSMTP();  // Send using SMTP
            $mail->Host = 'smtp-relay.brevo.com';  // Set the SMTP server to send through
            $mail->SMTPAuth = true;  // Enable SMTP authentication
            $mail->Username = '7904e1001@smtp-brevo.com';  // SMTP username
            $mail->Password = 'FkHtnapMr6PEOwVB';  // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // Enable TLS encryption
            $mail->Port = 587;  // TCP port to connect to

            //Recipients
            $mail->setFrom('www.3alemny@gmail.com', $domainName);
            $mail->addAddress($user->email, htmlspecialchars($user->firstname, ENT_QUOTES, 'UTF-8'));  // Add a recipient

            // Content
            $mail->isHTML(true);  // Set email format to HTML
            $mail->Subject = 'Password Reset Request';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    .email-body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        padding: 20px;
                    }
                    .email-content {
                        background-color: #ffffff;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
                    }
                    .email-header {
                        font-size: 24px;
                        margin-bottom: 20px;
                    }
                    .email-text {
                        font-size: 16px;
                        line-height: 1.5;
                        color: #333333;
                    }
                    .email-footer {
                        margin-top: 20px;
                        font-size: 14px;
                        color: #777777;
                    }
                    .reset-button {
                        display: inline-block;
                        padding: 10px 20px;
                        margin-top: 20px;
                        background-color: #C7AE72;
                        color: #ffffff !important;
                        text-decoration: none;
                        border-radius: 5px;
                    }
                </style>
            </head>
            <body>
                <div class="email-body">
                    <div class="email-content">
                        <div class="email-header">Password Reset Request</div>
                        <div class="email-text">
                            <p>Hello,</p>
                            <p>We received a request to reset your password. If you made this request, please click the button below to reset your password:</p>
                            <p>New Password is: '.$newPassword.'</p>
                            <p>If you did not request a password reset, please ignore this email.</p>
                        </div>
                        <div class="email-footer">
                            <p>Thank you,</p>
                            <p>Support Team</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ';

            $mail->AltBody = '
            Hello,
            
            We received a request to reset your password. If you made this request, please click the link below to reset your password:
            
            New Password is: '.$newPassword.'
            
            If you did not request a password reset, please ignore this email.
            
            Thank you,
            Support Team
            ';


            $mail->send();

            // Since there is no getMessage() method, we assume success if no exception is thrown
            \core\notification::add('Email sent successfully.', \core\output\notification::NOTIFY_SUCCESS);
        } catch (Exception $e) {
            \core\notification::add('Exception when calling Sendinblue API: ' . $mail->ErrorInfo, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Notify user if email is not found
        \core\notification::add('Email not found!', \core\output\notification::NOTIFY_ERROR);
    }
}
?>

<div class="popup">
    <form class="form" method="post">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 34 34" height="34" width="34">
                <path stroke-linejoin="round" stroke-width="2.5" stroke="#00126C" d="M7.08385 9.91666L5.3572 11.0677C4.11945 11.8929 3.50056 12.3055 3.16517 12.9347C2.82977 13.564 2.83226 14.3035 2.83722 15.7825C2.84322 17.5631 2.85976 19.3774 2.90559 21.2133C3.01431 25.569 3.06868 27.7468 4.67008 29.3482C6.27148 30.9498 8.47873 31.0049 12.8932 31.1152C15.6396 31.1838 18.3616 31.1838 21.1078 31.1152C25.5224 31.0049 27.7296 30.9498 29.331 29.3482C30.9324 27.7468 30.9868 25.569 31.0954 21.2133C31.1413 19.3774 31.1578 17.5631 31.1639 15.7825C31.1688 14.3035 31.1712 13.564 30.8359 12.9347C30.5004 12.3055 29.8816 11.8929 28.6437 11.0677L26.9171 9.91666"></path>
                <path stroke-linejoin="round" stroke-width="2.5" stroke="#00126C" d="M2.83331 14.1667L12.6268 20.0427C14.7574 21.3211 15.8227 21.9603 17 21.9603C18.1772 21.9603 19.2426 21.3211 21.3732 20.0427L31.1666 14.1667"></path>
                <path stroke-width="2.5" stroke="#00126C" d="M7.08331 17V8.50001C7.08331 5.82872 7.08331 4.49307 7.91318 3.66321C8.74304 2.83334 10.0787 2.83334 12.75 2.83334H21.25C23.9212 2.83334 25.2569 2.83334 26.0868 3.66321C26.9166 4.49307 26.9166 5.82872 26.9166 8.50001V17"></path>
                <path stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" stroke="#00126C" d="M14.1667 14.1667H19.8334M14.1667 8.5H19.8334"></path>
            </svg>
        </div>
        <div class="note">
            <label class="title">Restore password</label>
            <span class="subtitle">If you already have an account, enter your email and click Recover. The password will be sent to your email.</span>
        </div>
        <input placeholder="Enter your e-mail" title="Enter your e-mail" name="email" type="email" class="input_field" required>
        <button class="btn submit" type="submit">Submit</button>
    </form>
</div>

<?php
// Output page footer
echo $OUTPUT->footer();
?>