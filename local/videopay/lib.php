<?php
/**
 * local_videopay — per-module pricing hooks.
 *
 * Adds a "Pricing" section to every course module editing form so teachers
 * can mark each activity/resource as either free or set an EGP price.
 *
 * The price is stored in mdl_local_videopay_prices (cmid → price, is_free).
 * The course renderer reads this table to decide whether to show a payment
 * popup or let the student access the content freely.
 */

defined('MOODLE_INTERNAL') || die();

// ── Module edit form: add Pricing section ─────────────────────────────────

/**
 * Called from modedit.php when building the module editing form.
 * Injects a "Pricing" header + price field + free checkbox.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_videopay_coursemodule_standard_elements($formwrapper, $mform): void {
    global $DB;

    // Determine if we're editing an existing CM.
    $cmid = optional_param('update', 0, PARAM_INT);

    $price   = 0.0;
    $is_free = 1;

    if ($cmid > 0) {
        $rec = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);
        if ($rec) {
            $price   = (float)$rec->price;
            $is_free = (int)$rec->is_free;
        }
    }

    // ── Section header ───────────────────────────────────────────────────
    $mform->addElement('header', 'videopay_header',
        get_string('pricing_header', 'local_videopay'));
    $mform->setExpanded('videopay_header', true);

    // ── Free / Paid toggle ───────────────────────────────────────────────
    $mform->addElement('advcheckbox', 'videopay_is_free',
        get_string('is_free', 'local_videopay'),
        get_string('is_free_label', 'local_videopay'));
    $mform->setDefault('videopay_is_free', $is_free);
    $mform->addHelpButton('videopay_is_free', 'is_free', 'local_videopay');

    // ── Price field (EGP) ────────────────────────────────────────────────
    $mform->addElement('text', 'videopay_price',
        get_string('price', 'local_videopay'),
        ['size' => 8, 'placeholder' => '0']);
    $mform->setType('videopay_price', PARAM_FLOAT);
    $mform->setDefault('videopay_price', $price);
    $mform->addHelpButton('videopay_price', 'price', 'local_videopay');

    // Disable the price input when "Free" is ticked.
    $mform->disabledIf('videopay_price', 'videopay_is_free', 'checked');

    // Price must be ≥ 0 when paid.
    $mform->addRule('videopay_price', null, 'numeric',  null, 'client');
}

// ── Module edit form: save price after CM is saved ────────────────────────

/**
 * Called from modedit.php after the module record is written.
 * Upserts the price record for this CM.
 *
 * @param stdClass $data  The submitted form data (has ->coursemodule)
 * @param stdClass $course
 * @return stdClass $data  Must be returned unchanged.
 */
function local_videopay_coursemodule_edit_post_actions($data, $course): stdClass {
    global $DB;

    $cmid    = (int)$data->coursemodule;
    $price   = isset($data->videopay_price)   ? (float)$data->videopay_price          : 0.0;
    $is_free = isset($data->videopay_is_free) ? (int)(bool)$data->videopay_is_free    : 1;

    // If ticked as free, force price to 0.
    if ($is_free) {
        $price = 0.0;
    }

    $now      = time();
    $existing = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);

    if ($existing) {
        $existing->price        = $price;
        $existing->is_free      = $is_free;
        $existing->timemodified = $now;
        $DB->update_record('local_videopay_prices', $existing);
    } else {
        $DB->insert_record('local_videopay_prices', [
            'cmid'         => $cmid,
            'price'        => $price,
            'is_free'      => $is_free,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    return $data;
}

// ── Helper: fetch price for a CM (used by renderer) ──────────────────────

/**
 * Return [price, is_free] for a given course module ID.
 * Defaults to free (price=0, is_free=1) if no record exists.
 *
 * @param int $cmid
 * @return array  [float $price, bool $is_free]
 */
function local_videopay_get_price(int $cmid): array {
    global $DB;
    $rec = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);
    if (!$rec) {
        return [0.0, true];
    }
    return [(float)$rec->price, (bool)$rec->is_free];
}
