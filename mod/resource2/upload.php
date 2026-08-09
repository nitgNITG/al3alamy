<?php
/**
 * Pre-save video upload endpoint for mod_resource2.
 *
 * Accepts native-XHR chunked upload, enforces quotas, and assembles the
 * chunks into a single file on disk. No database writes happen here.
 *
 * The assembled file is identified by a file_token (= temp_key) that the
 * form carries as a hidden field (vimeo_pending_file_token). When the form
 * is saved, resource2_add_instance() uses the token to find the assembled
 * file, creates the vimeo_files2 and reda_video_type2 rows with the REAL
 * resource2_id, and spawns test2/vimeo_bg.php in the background.
 *
 * POST params (multipart/form-data per chunk):
 *   file        — the binary chunk
 *   chunk       — 0-based chunk index
 *   chunks      — total number of chunks
 *   temp_key    — unique upload token (e.g. "u{userid}_{timestamp}")
 *   total_size  — total file size in bytes (for quota check)
 *   sesskey     — Moodle sesskey (CSRF protection)
 *   courseid    — course ID for capability check
 *
 * Responses:
 *   Intermediate chunk: {"OK":1,"chunk":N}
 *   Final chunk:        {"OK":1,"file_token":"<temp_key>"}
 *   Error:              {"OK":0,"info":"<message>"}
 *
 * @package    mod_resource2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// ── Quota checks ───────────────────────────────────────────────────────────
$max_count      = (int)(get_config('resource2', 'max_video_count')      ?: 500);
$cur_count      = (int)(get_config('resource2', 'video_count')          ?: 0);
$max_size_mb    = (int)(get_config('resource2', 'max_video_size_mb')    ?: 500);
$max_storage_gb = (int)(get_config('resource2', 'max_total_storage_gb') ?: 50);
$cur_bytes      = (int)(get_config('resource2', 'total_storage_bytes')  ?: 0);

if ($cur_count >= $max_count) {
    die(json_encode(['OK' => 0,
        'info' => get_string('quota_error_count', 'resource2', $max_count)]));
}
$cur_gb = $cur_bytes / (1024 * 1024 * 1024);
if ($cur_gb >= $max_storage_gb) {
    die(json_encode(['OK' => 0,
        'info' => get_string('quota_error_storage', 'resource2')]));
}
$total_size_bytes = (int)($_POST['total_size'] ?? 0);
if ($total_size_bytes > 0 && $max_size_mb > 0) {
    $total_size_mb = $total_size_bytes / (1024 * 1024);
    if ($total_size_mb > $max_size_mb) {
        die(json_encode(['OK' => 0,
            'info' => get_string('quota_error_size', 'resource2',
                (object)['size' => round($total_size_mb, 1), 'max' => $max_size_mb])]));
    }
}

// ── Validate chunk ─────────────────────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error']) {
    die(json_encode(['OK' => 0, 'info' => 'No file chunk received.']));
}

$chunk    = max(0, (int)($_REQUEST['chunk']  ?? 0));
$chunks   = max(1, (int)($_REQUEST['chunks'] ?? 1));
$temp_key = preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['temp_key'] ?? 'u' . $USER->id);

// ── Temp directory ─────────────────────────────────────────────────────────
$upload_dir = $CFG->dataroot . '/resource2_tmp_uploads';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0775, true)) {
        die(json_encode(['OK' => 0, 'info' => 'Cannot create temp directory.']));
    }
}

$file_path = $upload_dir . '/' . $temp_key . '_video.part_assembled';

// ── Append chunk ───────────────────────────────────────────────────────────
$out = @fopen("{$file_path}.part", $chunk === 0 ? 'wb' : 'ab');
if (!$out) {
    die(json_encode(['OK' => 0, 'info' => 'Cannot open temp file for writing.']));
}
$in = @fopen($_FILES['file']['tmp_name'], 'rb');
if (!$in) {
    @fclose($out);
    die(json_encode(['OK' => 0, 'info' => 'Cannot open uploaded chunk.']));
}
while ($buff = fread($in, 65536)) {
    fwrite($out, $buff);
}
@fclose($in);
@fclose($out);
@unlink($_FILES['file']['tmp_name']);

// ── Intermediate chunks — ACK only ─────────────────────────────────────────
if ($chunks > 1 && $chunk < $chunks - 1) {
    die(json_encode(['OK' => 1, 'chunk' => $chunk]));
}

// ── Final chunk — rename and return token ─────────────────────────────────
// No DB writes here. vimeo_files2 is created in resource2_add_instance()
// once the form is saved and we have a real resource2_id to use.
rename("{$file_path}.part", $file_path);

die(json_encode(['OK' => 1, 'file_token' => $temp_key]));
