<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "My devices" — lets a user view and remove their registered devices so they
 * can free a slot when they hit the device limit (US-AD-11 enforcement).
 *
 * @package    local_deviceregistration
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_user::instance($USER->id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/deviceregistration/mydevices.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mydevices', 'local_deviceregistration'));
$PAGE->set_heading(get_string('mydevices', 'local_deviceregistration'));

// ── Handle device removal ────────────────────────────────────────────────────

$delete = optional_param('delete', 0, PARAM_INT);
if ($delete && confirm_sesskey()) {
    // A user can only remove their own devices.
    $device = $DB->get_record('local_devreg_device', ['id' => $delete, 'userid' => $USER->id]);
    if ($device) {
        $DB->delete_records('local_devreg_device', ['id' => $device->id, 'userid' => $USER->id]);
        redirect(
            $PAGE->url,
            get_string('device_removed', 'local_deviceregistration'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// ── Data ─────────────────────────────────────────────────────────────────────

$max     = local_deviceregistration_max_devices();
$token   = local_deviceregistration_get_cookie_token();
$devices = $DB->get_records('local_devreg_device', ['userid' => $USER->id], 'timelastseen DESC');
$count   = count($devices);

// ── Output ───────────────────────────────────────────────────────────────────

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mydevices', 'local_deviceregistration'));

echo html_writer::tag('p', get_string('mydevices_intro', 'local_deviceregistration'), ['class' => 'text-muted']);

// Usage cards.
$limitlabel = $max > 0 ? $max : get_string('unlimited', 'local_deviceregistration');
$overcls    = ($max > 0 && $count >= $max) ? 'danger' : 'success';

echo '<div class="d-flex flex-wrap gap-3 mb-4">';
echo '<div class="card text-center border-' . $overcls . '" style="min-width:150px;"><div class="card-body p-3">';
echo '<div class="h4 mb-1 text-' . $overcls . '">' . $count . '</div>';
echo '<small class="text-muted">' . get_string('devices_registered', 'local_deviceregistration') . '</small>';
echo '</div></div>';
echo '<div class="card text-center border-info" style="min-width:150px;"><div class="card-body p-3">';
echo '<div class="h4 mb-1 text-info">' . $limitlabel . '</div>';
echo '<small class="text-muted">' . get_string('devices_allowed', 'local_deviceregistration') . '</small>';
echo '</div></div>';
echo '</div>';

if ($count === 0) {
    echo $OUTPUT->notification(get_string('nodevices', 'local_deviceregistration'), 'info');
} else {
    echo '<div class="table-responsive"><table class="table table-sm table-bordered table-hover generaltable">';
    echo '<thead class="thead-light"><tr>';
    echo '<th>' . get_string('device', 'local_deviceregistration') . '</th>';
    echo '<th>' . get_string('lastip', 'local_deviceregistration') . '</th>';
    echo '<th>' . get_string('firstseen', 'local_deviceregistration') . '</th>';
    echo '<th>' . get_string('lastseen', 'local_deviceregistration') . '</th>';
    echo '<th>' . get_string('actions', 'local_deviceregistration') . '</th>';
    echo '</tr></thead><tbody>';

    $datefmt = get_string('strftimedatetimeshort', 'langconfig');

    foreach ($devices as $d) {
        $iscurrent = ($token !== '' && $d->devicetoken === $token);
        $label     = s($d->useragent ?: get_string('unknowndevice', 'local_deviceregistration'));
        if ($iscurrent) {
            $label .= ' <span class="badge badge-success">' . get_string('thisdevice', 'local_deviceregistration') . '</span>';
        }

        echo '<tr>';
        echo '<td>' . $label . '</td>';
        echo '<td>' . s($d->lastip ?? '—') . '</td>';
        echo '<td>' . userdate($d->timecreated, $datefmt) . '</td>';
        echo '<td>' . userdate($d->timelastseen, $datefmt) . '</td>';
        echo '<td>';
        $removeurl = new moodle_url('/local/deviceregistration/mydevices.php', [
            'delete'  => $d->id,
            'sesskey' => sesskey(),
        ]);
        echo html_writer::link($removeurl, get_string('remove', 'local_deviceregistration'), [
            'class'   => 'btn btn-sm btn-outline-danger',
            'onclick' => 'return confirm(\'' . addslashes(get_string('confirm_remove', 'local_deviceregistration')) . '\')',
        ]);
        echo '</td></tr>';
    }

    echo '</tbody></table></div>';
}

echo $OUTPUT->footer();
