<?php
/**
 * Pre-save upload endpoint for mod_resource2.
 *
 * Receives chunked XHR upload, assembles chunks into test2/chunks/ (same
 * directory used by script.php so vimeo_bg.php can always find the file).
 * NO database writes here — DB records are created in resource2_add_instance()
 * once the activity has a real ID (avoids FK constraint violations).
 *
 * POST params:
 *   file       — binary chunk data
 *   chunk      — 0-based chunk index
 *   chunks     — total chunks
 *   temp_key   — unique token, e.g. "pre_{userid}_{timestamp}"
 *   total_size — total file size in bytes
 *   sesskey    — Moodle CSRF token
 *   courseid   — course ID for capability check
 *
 * Success (final chunk): {"OK":1,"file_token":"<token>"}
 * Intermediate chunk:    {"OK":1,"chunk":N}
 * Error:                 {"OK":0,"info":"<message>"}
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// ── Quota: file size check ─────────────────────────────────────────────────
$max_size_mb  = (int)(get_config('resource2', 'max_video_size_mb')    ?: 500);
$max_count    = (int)(get_config('resource2', 'max_video_count')      ?: 500);
$cur_count    = (int)(get_config('resource2', 'video_count')          ?: 0);
$max_stor_gb  = (int)(get_config('resource2', 'max_total_storage_gb') ?: 50);
$cur_bytes    = (int)(get_config('resource2', 'total_storage_bytes')  ?: 0);

if ($cur_count >= $max_count) {
    die(json_encode(['OK' => 0, 'info' =>
        get_string('quota_error_count', 'resource2', $max_count)]));
}
if (($cur_bytes / (1024 * 1024 * 1024)) >= $max_stor_gb) {
    die(json_encode(['OK' => 0, 'info' =>
        get_string('quota_error_storage', 'resource2')]));
}
$total_size = (int)($_POST['total_size'] ?? 0);
if ($total_size > 0 && $max_size_mb > 0) {
    $total_mb = $total_size / (1024 * 1024);
    if ($total_mb > $max_size_mb) {
        die(json_encode(['OK' => 0, 'info' =>
            get_string('quota_error_size', 'resource2',
                (object)['size' => round($total_mb, 1), 'max' => $max_size_mb])]));
    }
}

// ── Validate chunk ─────────────────────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error']) {
    die(json_encode(['OK' => 0, 'info' => 'No file chunk received.']));
}

$chunk    = max(0, (int)($_POST['chunk']    ?? 0));
$chunks   = max(1, (int)($_POST['chunks']   ?? 1));
$token    = preg_replace('/[^a-zA-Z0-9_-]/', '',
                $_POST['temp_key'] ?? ('pre_' . $USER->id . '_' . time()));

// ── Save to test2/chunks/ — same dir as script.php ────────────────────────
$chunks_dir = $CFG->dirroot . '/test2/chunks';
if (!is_dir($chunks_dir)) {
    mkdir($chunks_dir, 0755, true);
}

$part_file = $chunks_dir . '/' . $token . '.part';
$out = @fopen($part_file, $chunk === 0 ? 'wb' : 'ab');
if (!$out) {
    die(json_encode(['OK' => 0, 'info' => 'Cannot write to upload directory.']));
}
$in = @fopen($_FILES['file']['tmp_name'], 'rb');
while ($buf = fread($in, 65536)) {
    fwrite($out, $buf);
}
fclose($in);
fclose($out);

// ── Intermediate chunk — ACK only ─────────────────────────────────────────
if ($chunk < $chunks - 1) {
    die(json_encode(['OK' => 1, 'chunk' => $chunk]));
}

// ── Final chunk — rename .part → .mp4 and return token ───────────────────
$final_file = $chunks_dir . '/' . $token . '.mp4';
rename($part_file, $final_file);

die(json_encode(['OK' => 1, 'file_token' => $token]));
