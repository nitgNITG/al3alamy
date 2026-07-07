<?php
/**
 * Show the registration code info for a specific user (admin view).
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_registrationcodes\manager;

$userid = required_param('userid', PARAM_INT);

require_login();
$systemcontext = context_system::instance();
require_capability('local/registrationcodes:viewreports', $systemcontext);

$user = core_user::get_user($userid, '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/registrationcodes/userinfo.php', ['userid' => $userid]));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('regcode_info', 'local_registrationcodes'));
$PAGE->set_heading(fullname($user));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('regcode_info', 'local_registrationcodes'));
echo '<p><a href="' . (new moodle_url('/user/profile.php', ['id' => $userid]))->out(false) . '">&larr; ' . fullname($user) . '</a></p>';

$record = manager::get_user_code($userid);

if (!$record) {
    echo $OUTPUT->notification(get_string('profile_not_found', 'local_registrationcodes'), 'info');
} else {
    // Creator info.
    $creator = $record->created_by ? core_user::get_user($record->created_by) : null;

    echo '<table class="generaltable table table-bordered" style="max-width:600px;">';
    echo '<tbody>';

    $rows = [
        get_string('profile_code',    'local_registrationcodes') => '<code>' . s($record->code)  . '</code>',
        get_string('status',          'local_registrationcodes') => get_string('status_' . $record->status, 'local_registrationcodes'),
        get_string('profile_regdate', 'local_registrationcodes') => $record->timeused ? userdate($record->timeused) : '—',
        get_string('timecreated',     'local_registrationcodes') => userdate($record->timecreated),
        get_string('timeexpiry',      'local_registrationcodes') => $record->timeexpiry ? userdate($record->timeexpiry) : get_string('never', 'moodle'),
        get_string('profile_code_by', 'local_registrationcodes') => $creator ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $creator->id]), fullname($creator)) : '—',
        get_string('notes',           'local_registrationcodes') => s($record->notes ?? ''),
    ];

    foreach ($rows as $label => $value) {
        echo '<tr><th class="text-right pr-3" style="width:40%;">' . $label . '</th><td>' . $value . '</td></tr>';
    }
    echo '</tbody></table>';
}

echo $OUTPUT->footer();
