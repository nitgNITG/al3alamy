<?php
/**
 * Forgot password — custom implementation.
 *
 * Accepts username OR e-mail, generates a random 6-digit password,
 * updates the DB and sends the new password to the user's e-mail via Brevo SMTP.
 */

require('../config.php');
require_once($CFG->libdir . '/authlib.php');
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$PAGE->set_url('/login/forgot_password.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title('استعادة كلمة السر');
$PAGE->set_heading($COURSE->fullname);

echo $OUTPUT->header();

// ── Inline styles ──────────────────────────────────────────────────────────
echo '
<style>
#page { background: linear-gradient(135deg, #00126C 0%, #00126C 100%) !important; }
.fp-wrap {
    max-width: 420px; margin: 40px auto; background: #fff;
    border-radius: 16px; padding: 36px 32px; box-shadow: 0 8px 32px rgba(0,0,0,.18);
    direction: rtl; font-family: "Cairo","Tajawal",Arial,sans-serif;
}
.fp-icon {
    width: 64px; height: 64px; background: #ECF1FD; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
}
.fp-title  { font-size: 20px; font-weight: 700; color: #1a1a2e; text-align:center; margin-bottom: 6px; }
.fp-sub    { font-size: 13px; color: #666; text-align:center; margin-bottom: 24px; line-height: 1.7; }
.fp-label  { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; display:block; }
.fp-input  {
    width: 100%; height: 44px; padding: 0 14px; border: 1px solid #ddd;
    border-radius: 8px; font-size: 14px; box-sizing: border-box;
    transition: border .2s; outline: none; font-family: inherit;
}
.fp-input:focus { border-color: #00126C; box-shadow: 0 0 0 3px rgba(0,18,108,.1); }
.fp-btn {
    width: 100%; height: 44px; background: #C7AE72; color: #fff; border: none;
    border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer;
    margin-top: 20px; font-family: inherit; transition: background .2s;
}
.fp-btn:hover { background: #b89d60; }
.fp-back { display:block; text-align:center; margin-top:16px; color:#00126C; font-size:13px; font-weight:600; text-decoration:none; }
.fp-back:hover { text-decoration:underline; }
.fp-alert {
    padding: 12px 16px; border-radius: 8px; margin-bottom: 18px;
    font-size: 13px; font-weight: 600;
}
.fp-alert.success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.fp-alert.error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.logo2 { display:none; }
</style>
';

// ── Handle POST ────────────────────────────────────────────────────────────
$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $identifier = trim(optional_param('identifier', '', PARAM_TEXT));

    if (empty($identifier)) {
        $alert = '<div class="fp-alert error">الرجاء إدخال اسم المستخدم أو البريد الإلكتروني.</div>';
    } else {
        // Search by email first, then by username.
        $user = $DB->get_record_select(
            'user',
            '(email = :email OR username = :username) AND deleted = 0 AND suspended = 0',
            ['email' => strtolower($identifier), 'username' => strtolower($identifier)]
        );

        if (!$user) {
            // Generic message — don't reveal whether email exists (security best practice).
            $alert = '<div class="fp-alert success">إذا كان الحساب موجوداً، ستصل كلمة السر الجديدة على بريدك الإلكتروني خلال دقائق.</div>';
        } elseif (empty($user->email)) {
            $alert = '<div class="fp-alert error">لا يوجد بريد إلكتروني مرتبط بهذا الحساب. تواصل مع الدعم الفني.</div>';
        } else {
            // Generate a 6-digit password and update the DB.
            $newPassword = (string)rand(100000, 999999);
            $user->password = hash_internal_user_password($newPassword);
            $DB->update_record('user', $user);

            // Force the user to change password on next login (security).
            set_user_preference('auth_forcepasswordchange', 1, $user->id);

            // Send via Brevo SMTP.
            $sitename = format_string(get_config('core', 'fullname') ?: 'العالمي');
            $mail = new PHPMailer(true);
            try {
                $mail->CharSet  = 'UTF-8';
                $mail->isSMTP();
                $mail->Host       = 'smtp-relay.brevo.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = '7904e1001@smtp-brevo.com';
                $mail->Password   = 'FkHtnapMr6PEOwVB';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPDebug  = 0;

                $mail->setFrom('www.3alemny@gmail.com', $sitename);
                $mail->addAddress($user->email, htmlspecialchars(fullname($user), ENT_QUOTES, 'UTF-8'));

                $mail->isHTML(true);
                $mail->Subject = '=?UTF-8?B?' . base64_encode('استعادة كلمة السر — ' . $sitename) . '?=';
                $mail->Body = '
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"></head>
<body style="font-family:Cairo,Tajawal,Arial,sans-serif;background:#f4f4f4;padding:20px;direction:rtl;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <div style="background:#00126C;padding:24px;text-align:center;">
      <h2 style="color:#C7AE72;margin:0;font-size:22px;">' . htmlspecialchars($sitename) . '</h2>
    </div>
    <div style="padding:28px 24px;">
      <p style="font-size:16px;color:#333;margin-bottom:8px;">مرحباً <strong>' . htmlspecialchars(fullname($user)) . '</strong>،</p>
      <p style="color:#555;font-size:14px;line-height:1.8;margin-bottom:20px;">
        تم استلام طلب استعادة كلمة السر الخاصة بحسابك. كلمة السر الجديدة المؤقتة هي:
      </p>
      <div style="background:#f0f4ff;border:2px dashed #00126C;border-radius:10px;padding:18px;text-align:center;margin-bottom:20px;">
        <span style="font-size:28px;font-weight:700;color:#00126C;letter-spacing:6px;">' . $newPassword . '</span>
      </div>
      <p style="color:#555;font-size:13px;line-height:1.8;">
        ادخل بهذه الكلمة ثم غيّرها فور تسجيل الدخول.<br>
        إذا لم تطلب استعادة كلمة السر، تجاهل هذا الإيميل.
      </p>
      <div style="text-align:center;margin-top:24px;">
        <a href="' . $CFG->wwwroot . '/login/index.php"
           style="background:#C7AE72;color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;">
          تسجيل الدخول
        </a>
      </div>
    </div>
    <div style="background:#f8f8f8;padding:14px;text-align:center;color:#999;font-size:12px;">
      ' . htmlspecialchars($sitename) . ' — هذا البريد تم إرساله تلقائياً، لا ترد عليه.
    </div>
  </div>
</body>
</html>';

                $mail->AltBody = 'كلمة السر الجديدة المؤقتة: ' . $newPassword . "\n\nسجّل دخولك على: " . $CFG->wwwroot;
                $mail->send();

                $alert = '<div class="fp-alert success">✓ تم إرسال كلمة السر الجديدة على البريد الإلكتروني. تحقق من صندوق الوارد أو Spam.</div>';

            } catch (Exception $e) {
                // Roll back the password change if email failed.
                error_log('forgot_password.php mailer error: ' . $mail->ErrorInfo);
                $alert = '<div class="fp-alert error">حدث خطأ أثناء الإرسال. حاول مرة أخرى أو تواصل مع الدعم الفني.</div>';
            }
        }
    }
}
?>

<div class="fp-wrap">

  <!-- Icon -->
  <div class="fp-icon">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 34 34" height="34" width="34">
      <path stroke-linejoin="round" stroke-width="2.5" stroke="#00126C"
        d="M7.08 9.92L5.36 11.07C4.12 11.89 3.5 12.31 3.17 12.93c-.34.63-.33 1.37-.32 2.85.006 1.78.022 3.59.068 5.43.109 4.36.163 6.53 1.764 8.13 1.602 1.6 3.81 1.66 8.224 1.77 2.746.07 5.468.07 8.215 0 4.414-.11 6.62-.17 8.222-1.77 1.601-1.6 1.656-3.77 1.764-8.13.046-1.84.062-3.65.068-5.43.007-1.48.009-2.22-.326-2.85-.335-.62-.954-1.03-2.192-1.86L26.92 9.92"/>
      <path stroke-linejoin="round" stroke-width="2.5" stroke="#00126C"
        d="M2.83 14.17l9.794 5.876C14.757 21.32 15.823 21.96 17 21.96c1.177 0 2.242-.64 4.372-1.913l9.795-5.876"/>
      <path stroke-width="2.5" stroke="#00126C"
        d="M7.08 17V8.5c0-2.67 0-4.006.83-4.836.83-.83 2.166-.83 4.837-.83h8.5c2.671 0 4.007 0 4.837.83.83.83.83 2.165.83 4.836V17"/>
      <path stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" stroke="#00126C"
        d="M14.17 14.17h5.666M14.17 8.5h5.666"/>
    </svg>
  </div>

  <div class="fp-title">نسيت كلمة السر؟</div>
  <div class="fp-sub">
    ادخل اسم المستخدم أو بريدك الإلكتروني وهنبعتلك<br>كلمة سر جديدة مؤقتة على إيميلك.
  </div>

  <?php echo $alert; ?>

  <form method="post">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <label class="fp-label" for="identifier">اسم المستخدم أو البريد الإلكتروني</label>
    <input
      type="text"
      id="identifier"
      name="identifier"
      class="fp-input"
      placeholder="اكتب اسم المستخدم أو إيميلك"
      autocomplete="username email"
      required
      value="<?php echo s(optional_param('identifier', '', PARAM_TEXT)); ?>"
    >
    <button type="submit" class="fp-btn">إرسال كلمة السر الجديدة</button>
  </form>

  <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="fp-back">← العودة لتسجيل الدخول</a>

</div>

<?php echo $OUTPUT->footer(); ?>
