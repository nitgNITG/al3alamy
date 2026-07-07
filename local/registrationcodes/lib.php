<?php
/**
 * lib.php — Moodle signup-form hooks for local_registrationcodes.
 *
 * Moodle calls these functions automatically via get_plugins_with_function().
 */

defined('MOODLE_INTERNAL') || die();

// ── 1. Extend the signup form ──────────────────────────────────────────────

/**
 * Add the "Registration Code" field to the signup form.
 *
 * @param \MoodleQuickForm $mform
 */
function local_registrationcodes_extend_signup_form($mform) {
    $mform->addElement(
        'text',
        'regcode',
        get_string('regcode', 'local_registrationcodes'),
        ['size' => 25, 'autocomplete' => 'off']
    );
    $mform->setType('regcode', PARAM_ALPHANUMEXT);
    $mform->addRule('regcode', get_string('error_code_required', 'local_registrationcodes'), 'required', null, 'client');
    $mform->addRule('regcode', get_string('error_code_required', 'local_registrationcodes'), 'required', null, 'server');
    $mform->addHelpButton('regcode', 'regcode', 'local_registrationcodes');
}

// ── 2. Validate the submitted code ────────────────────────────────────────

/**
 * Validate the registration code field during signup.
 *
 * @param array $data  Form data.
 * @return array       Associative array of errors (empty = no errors).
 */
function local_registrationcodes_validate_extend_signup_form($data) {
    $errors = [];

    $rawcode = isset($data['regcode']) ? trim($data['regcode']) : '';

    if ($rawcode === '') {
        $errors['regcode'] = get_string('error_code_required', 'local_registrationcodes');
        return $errors;
    }

    $result = \local_registrationcodes\manager::validate_code($rawcode);

    if (!$result['valid']) {
        $errors['regcode'] = get_string($result['error'], 'local_registrationcodes');
    }

    return $errors;
}

// ── 3. After successful registration ──────────────────────────────────────

/**
 * Mark the code as used after the account is successfully created.
 *
 * Moodle passes the full user object (which includes form fields such as 'regcode').
 *
 * @param \stdClass $user  The newly created user object.
 */
function local_registrationcodes_post_signup_requests($user) {
    // Prefer value stored in user object; fall back to POST param as safety net.
    $rawcode = '';
    if (!empty($user->regcode)) {
        $rawcode = $user->regcode;
    } else {
        $rawcode = optional_param('regcode', '', PARAM_ALPHANUMEXT);
    }

    if ($rawcode === '') {
        return;
    }

    \local_registrationcodes\manager::consume_code($rawcode, (int)$user->id);
}

// ── 4. Extend user profile view (admin only) ──────────────────────────────

/**
 * Add a "Registration Code" section to the user profile settings navigation
 * (visible to managers only).
 *
 * @param \settings_navigation $settingsnav
 * @param \context             $context
 */
function local_registrationcodes_extend_navigation_user_settings(
    \settings_navigation $settingsnav,
    \context $context
) {
    global $PAGE;

    if (!has_capability('local/registrationcodes:viewreports', \context_system::instance())) {
        return;
    }

    $userid = optional_param('id', 0, PARAM_INT);
    if (!$userid && isset($PAGE->url)) {
        $userid = (int)$PAGE->url->get_param('id');
    }
    if (!$userid) {
        return;
    }

    $url  = new \moodle_url('/local/registrationcodes/userinfo.php', ['userid' => $userid]);
    $node = $settingsnav->add(
        get_string('regcode_info', 'local_registrationcodes'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_registrationcodes_userinfo'
    );
    $node->set_force_into_more_menu(false);
}
