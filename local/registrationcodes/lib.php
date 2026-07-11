<?php
/**
 * lib.php — Moodle signup-form hooks for local_registrationcodes.
 *
 * Moodle calls these functions automatically via get_plugins_with_function().
 */

defined('MOODLE_INTERNAL') || die();

// ── Option helpers ─────────────────────────────────────────────────────────────

/**
 * Returns the ordered list of Egyptian governorates for the signup dropdown.
 * Keys and values are identical (Arabic text) — that is what gets stored in
 * user_info_data and displayed on the profile page.
 */
function local_registrationcodes_governorate_options(): array {
    return [
        ''              => get_string('choosedots'),
        'القاهرة'       => 'القاهرة',
        'الإسكندرية'    => 'الإسكندرية',
        'الجيزة'        => 'الجيزة',
        'القليوبية'     => 'القليوبية',
        'الشرقية'       => 'الشرقية',
        'الدقهلية'      => 'الدقهلية',
        'البحيرة'       => 'البحيرة',
        'الغربية'       => 'الغربية',
        'المنوفية'      => 'المنوفية',
        'كفر الشيخ'     => 'كفر الشيخ',
        'دمياط'         => 'دمياط',
        'بورسعيد'       => 'بورسعيد',
        'الإسماعيلية'   => 'الإسماعيلية',
        'السويس'        => 'السويس',
        'شمال سيناء'    => 'شمال سيناء',
        'جنوب سيناء'    => 'جنوب سيناء',
        'الفيوم'        => 'الفيوم',
        'بني سويف'      => 'بني سويف',
        'المنيا'        => 'المنيا',
        'أسيوط'         => 'أسيوط',
        'سوهاج'         => 'سوهاج',
        'قنا'           => 'قنا',
        'الأقصر'        => 'الأقصر',
        'أسوان'         => 'أسوان',
        'البحر الأحمر'  => 'البحر الأحمر',
        'الوادي الجديد' => 'الوادي الجديد',
        'مطروح'         => 'مطروح',
    ];
}

/**
 * Returns the four Baccalaureate track options for the signup dropdown.
 */
function local_registrationcodes_track_options(): array {
    return [
        ''                              => get_string('choosedots'),
        'مسار الطب وعلوم الحياة'       => 'مسار الطب وعلوم الحياة',
        'مسار الهندسة وعلوم الحاسب'   => 'مسار الهندسة وعلوم الحاسب',
        'مسار الأعمال'                  => 'مسار الأعمال',
        'مسار الآداب والفنون'           => 'مسار الآداب والفنون',
    ];
}

// ── 1. Extend the signup form ──────────────────────────────────────────────────

/**
 * Inject the four student profile fields before the password field, then append
 * the registration-code field at the very end.
 *
 * Profile fields are named "profile_field_*" so that Moodle's profile_save_data()
 * (called inside auth/email::user_signup) saves them automatically to
 * {user_info_data} without any extra hook.
 *
 * @param \MoodleQuickForm $mform
 */
function local_registrationcodes_extend_signup_form($mform) {

    // ── 1. Parent phone ────────────────────────────────────────────────────
    $mform->insertElementBefore(
        $mform->createElement(
            'text',
            'profile_field_parentphone',
            get_string('field_parentphone', 'local_registrationcodes'),
            ['size' => 20, 'maxlength' => 15, 'autocomplete' => 'off',
             'placeholder' => get_string('field_parentphone_placeholder', 'local_registrationcodes')]
        ),
        'password'
    );
    $mform->setType('profile_field_parentphone', PARAM_TEXT);
    $mform->addRule(
        'profile_field_parentphone',
        get_string('required'),
        'required', null, 'client'
    );
    $mform->addRule(
        'profile_field_parentphone',
        get_string('required'),
        'required', null, 'server'
    );
    $mform->addHelpButton('profile_field_parentphone', 'field_parentphone', 'local_registrationcodes');

    // ── 2. Governorate ─────────────────────────────────────────────────────
    $mform->insertElementBefore(
        $mform->createElement(
            'select',
            'profile_field_governorate',
            get_string('field_governorate', 'local_registrationcodes'),
            local_registrationcodes_governorate_options()
        ),
        'password'
    );
    $mform->setType('profile_field_governorate', PARAM_TEXT);
    $mform->addHelpButton('profile_field_governorate', 'field_governorate', 'local_registrationcodes');

    // ── 3. Track ───────────────────────────────────────────────────────────
    $mform->insertElementBefore(
        $mform->createElement(
            'select',
            'profile_field_track',
            get_string('field_track', 'local_registrationcodes'),
            local_registrationcodes_track_options()
        ),
        'password'
    );
    $mform->setType('profile_field_track', PARAM_TEXT);
    $mform->addHelpButton('profile_field_track', 'field_track', 'local_registrationcodes');

    // ── 4. Address (optional) ──────────────────────────────────────────────
    $mform->insertElementBefore(
        $mform->createElement(
            'textarea',
            'profile_field_address',
            get_string('field_address', 'local_registrationcodes'),
            ['rows' => 3, 'cols' => 40]
        ),
        'password'
    );
    $mform->setType('profile_field_address', PARAM_TEXT);
    $mform->addHelpButton('profile_field_address', 'field_address', 'local_registrationcodes');

    // ── 5. Registration code (appended after city/country as before) ───────
    $mform->addElement(
        'text',
        'regcode',
        get_string('regcode', 'local_registrationcodes'),
        ['size' => 25, 'autocomplete' => 'off']
    );
    $mform->setType('regcode', PARAM_ALPHANUMEXT);
    $mform->addRule(
        'regcode',
        get_string('error_code_required', 'local_registrationcodes'),
        'required', null, 'client'
    );
    $mform->addRule(
        'regcode',
        get_string('error_code_required', 'local_registrationcodes'),
        'required', null, 'server'
    );
    $mform->addHelpButton('regcode', 'regcode', 'local_registrationcodes');
}

// ── 2. Server-side validation ──────────────────────────────────────────────────

/**
 * Validate all custom signup fields server-side.
 *
 * @param array $data  Raw form data array.
 * @return array       Associative array of field => error message (empty = OK).
 */
function local_registrationcodes_validate_extend_signup_form($data) {
    $errors = [];

    // ── Registration code ──────────────────────────────────────────────────
    $rawcode = trim($data['regcode'] ?? '');
    if ($rawcode === '') {
        $errors['regcode'] = get_string('error_code_required', 'local_registrationcodes');
    } else {
        $result = \local_registrationcodes\manager::validate_code($rawcode);
        if (!$result['valid']) {
            $errors['regcode'] = get_string($result['error'], 'local_registrationcodes');
        }
    }

    // ── Parent phone ───────────────────────────────────────────────────────
    $phone = trim($data['profile_field_parentphone'] ?? '');
    if ($phone === '') {
        $errors['profile_field_parentphone'] = get_string('required');
    } elseif (!preg_match('/^01[0125][0-9]{8}$/', $phone)) {
        $errors['profile_field_parentphone'] = get_string('error_invalid_phone', 'local_registrationcodes');
    }

    // ── Governorate ────────────────────────────────────────────────────────
    $gov = $data['profile_field_governorate'] ?? '';
    if ($gov === '') {
        $errors['profile_field_governorate'] = get_string('required');
    } elseif (!array_key_exists($gov, local_registrationcodes_governorate_options())) {
        $errors['profile_field_governorate'] = get_string('invalid', 'error');
    }

    // ── Track ──────────────────────────────────────────────────────────────
    $track = $data['profile_field_track'] ?? '';
    if ($track === '') {
        $errors['profile_field_track'] = get_string('required');
    } elseif (!array_key_exists($track, local_registrationcodes_track_options())) {
        $errors['profile_field_track'] = get_string('invalid', 'error');
    }

    // address is optional — no validation needed.

    return $errors;
}

// ── 3. After successful registration ──────────────────────────────────────────

/**
 * Mark the registration code as used.
 * Profile fields (profile_field_*) are already saved automatically by
 * auth/email::user_signup() → profile_save_data($user).
 *
 * @param \stdClass $user  The newly created user object (includes form fields).
 */
function local_registrationcodes_post_signup_requests($user) {
    global $CFG;

    // Safety net: ensure profile fields are saved even if the auth plugin
    // doesn't call profile_save_data() (e.g. custom auth plugins).
    if (!empty($user->id)) {
        require_once($CFG->dirroot . '/user/profile/lib.php');
        profile_save_data($user);
    }

    // Consume the registration code.
    $rawcode = !empty($user->regcode)
        ? $user->regcode
        : optional_param('regcode', '', PARAM_ALPHANUMEXT);

    if ($rawcode !== '') {
        \local_registrationcodes\manager::consume_code($rawcode, (int)$user->id);
    }
}

// ── 4. Manager "Buy Codes" navbar button ──────────────────────────────────────

/**
 * Inject a "شراء أكواد" button into the navbar for users who hold the
 * local/registrationcodes:generate capability (i.e. managers/admins).
 * The button is appended via JS to `ul.sign_up_btn` — the same list that
 * holds the Login/Register pills — so it matches the existing pill styling.
 *
 * @return string  HTML/JS snippet added just before </body>.
 */
function local_registrationcodes_before_standard_top_of_body_html(): string {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }
    if (!has_capability('local/registrationcodes:generate', context_system::instance())) {
        return '';
    }

    $url   = (new moodle_url('/local/registrationcodes/buy.php'))->out(false);
    $label = get_string('buycodes_nav', 'local_registrationcodes');

    // Escape for safe embedding in a JS string literal.
    $url_js   = addslashes($url);
    $label_js = addslashes($label);

    $script = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.querySelector('ul.sign_up_btn');
    if (!nav) return;
    var li = document.createElement('li');
    li.className = 'list-inline-item list_s al-buy-codes-li';
    li.innerHTML = '<a href="{$url_js}" class="al-nav-btn al-buy-codes-btn">'
                 + '<i class="fa fa-ticket" aria-hidden="true"></i> {$label_js}</a>';
    nav.insertBefore(li, nav.firstChild);
});
JS;

    return html_writer::script($script);
}

// ── 5. Extend user profile navigation (admin view) ──────────────────────────

/**
 * Add a "Registration Code" link to the user settings navigation for managers.
 */
function local_registrationcodes_extend_navigation_user_settings(
    \navigation_node $usersetting,
    $user,
    $usercontext,
    $course,
    $coursecontext
) {
    if (!has_capability('local/registrationcodes:viewreports', \context_system::instance())) {
        return;
    }
    if (empty($user->id)) {
        return;
    }
    $url = new \moodle_url('/local/registrationcodes/userinfo.php', ['userid' => $user->id]);
    $usersetting->add(
        get_string('regcode_info', 'local_registrationcodes'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_registrationcodes_userinfo'
    );
}
