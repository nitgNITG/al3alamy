<?php
/**
 * buy.php — Manager purchase page for registration codes.
 *
 * Managers (users with local/registrationcodes:generate) arrive here to
 * choose how many codes to buy (10 EGP each), then get redirected to the
 * Kashier checkout page.
 *
 * After successful payment Kashier redirects back to kashier/callback.php
 * with order_id = codes-{uid}-{count}-{ts}, which generates the codes and
 * redirects to local/registrationcodes/codes_ready.php.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/kashier/config.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/registrationcodes:generate', $syscontext);

// Site admins use the free admin panel — redirect them rather than charging them.
if (is_siteadmin()) {
    redirect(
        new moodle_url('/local/registrationcodes/admin.php'),
        'Admins can generate codes for free from the admin panel.',
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$PAGE->set_url(new moodle_url('/local/registrationcodes/buy.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('buycodes_title', 'local_registrationcodes'));
$PAGE->set_heading(get_string('buycodes_title', 'local_registrationcodes'));

const PRICE_PER_CODE = 10; // EGP

// ── Handle form submission ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $count = (int) required_param('count', PARAM_INT);

    if ($count < 1 || $count > 500) {
        redirect(
            new moodle_url('/local/registrationcodes/buy.php'),
            get_string('buycodes_count_error', 'local_registrationcodes'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $total    = $count * PRICE_PER_CODE;
    $order_id = 'codes-' . $USER->id . '-' . $count . '-' . time();
    $callback = (new moodle_url('/kashier/callback.php'))->out(false);
    $webhook  = (new moodle_url('/kashier/webhook.php'))->out(false);
    $desc     = "شراء {$count} كود تسجيل — al3alamy";

    try {
        $session = kashier_create_session(
            $order_id,
            (float) $total,
            $callback,
            $webhook,
            $desc
        );
        redirect($session['sessionUrl']);
    } catch (\Exception $e) {
        error_log('kashier buy.php session error: ' . $e->getMessage());
        redirect(
            new moodle_url('/local/registrationcodes/buy.php'),
            'خطأ في الاتصال ببوابة الدفع. حاول مرة أخرى. (' . $e->getMessage() . ')',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// ── Render form ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

$sesskey   = sesskey();
$form_url  = (new moodle_url('/local/registrationcodes/buy.php'))->out(false);

?>
<div class="container al-buycodes-wrap" dir="rtl" style="max-width:540px;margin:40px auto;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="card-title mb-4" style="font-size:1.4rem;">
        <i class="fa fa-ticket" aria-hidden="true"></i>
        <?php echo get_string('buycodes_title', 'local_registrationcodes'); ?>
      </h2>

      <form method="post" action="<?php echo $form_url; ?>" id="al-buycodes-form">
        <input type="hidden" name="sesskey" value="<?php echo $sesskey; ?>">

        <div class="form-group row mb-3">
          <label class="col-sm-5 col-form-label" for="al-codes-count">
            <?php echo get_string('buycodes_count_label', 'local_registrationcodes'); ?>
          </label>
          <div class="col-sm-7">
            <input type="number" id="al-codes-count" name="count"
                   class="form-control" value="10" min="1" max="500"
                   required autocomplete="off">
          </div>
        </div>

        <div class="form-group row mb-3">
          <label class="col-sm-5 col-form-label">
            <?php echo get_string('buycodes_price_label', 'local_registrationcodes'); ?>
          </label>
          <div class="col-sm-7 d-flex align-items-center">
            <strong>10 <?php echo get_string('currency', 'core') ?: 'جنيه'; ?></strong>
          </div>
        </div>

        <div class="form-group row mb-4">
          <label class="col-sm-5 col-form-label">
            <?php echo get_string('buycodes_total_label', 'local_registrationcodes'); ?>
          </label>
          <div class="col-sm-7 d-flex align-items-center">
            <strong id="al-total-price">100</strong>&nbsp;<?php echo 'جنيه'; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block al-kashier-pay-btn">
          <i class="fa fa-credit-card" aria-hidden="true"></i>
          <?php echo get_string('buycodes_pay_btn', 'local_registrationcodes'); ?>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
    var inp   = document.getElementById('al-codes-count');
    var total = document.getElementById('al-total-price');
    if (!inp || !total) return;
    function update() {
        var n = parseInt(inp.value, 10);
        total.textContent = (isNaN(n) || n < 1) ? '0' : (n * 10).toString();
    }
    inp.addEventListener('input', update);
    update();
}());
</script>
<?php

echo $OUTPUT->footer();
