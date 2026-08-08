<?php
/**
 * Video replacement AJAX endpoint for mod_resource2.
 *
 * Accepts a chunked upload for replacing an existing Vimeo video.
 * On the final chunk:
 *   1. Clears vimeo_files2.url (view.php then shows the spinner).
 *   2. Updates vimeo_files2.name with the new title.
 *   3. Updates reda_video_type2.type if a new type is supplied.
 *   4. Spawns test2/vimeo_bg.php with mode=replace in the background.
 *
 * POST params (multipart/form-data per chunk):
 *   file          — binary chunk
 *   chunk         — 0-based chunk index
 *   chunks        — total number of chunks
 *   temp_key      — unique upload token (e.g. "r{userid}_{uniqid}")
 *   total_size    — total file size in bytes (sent on every chunk for quota check)
 *   resource2_id  — ID of the resource2 module being updated
 *   vname         — new Vimeo video title (optional; defaults to current name)
 *   video_type    — new video type (optional; 0 = keep current)
 *   sesskey       — Moodle sesskey (CSRF protection)
 *   courseid      — course ID for capability check
 *
 * @package    mod_resource2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

// ── Auth + CSRF ────────────────────────────────────────────────────────────
require_login();
require_sesskey();

header('Content-Type: application/json');

$courseid     = required_param('courseid',     PARAM_INT);
$resource2_id = required_param('resource2_id', PARAM_INT);
$context      = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// ── Validate the existing vimeo record ────────────────────────────────────
$vimeo_rec = $DB->get_record('vimeo_files2', ['resource2_id' => $resource2_id]);
if (!$vimeo_rec) {
    die(json_encode(['OK' => 0, 'info' => 'No Vimeo record found for this resource.']));
}

// ── Per-file size quota ────────────────────────────────────────────────────
$max_size_mb      = (int)(get_config('resource2', 'max_video_size_mb') ?: 500);
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
$temp_key = preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['temp_key'] ?? 'r' . $USER->id);

// ── Temp directory ─────────────────────────────────────────────────────────
$upload_dir = $CFG->dataroot . '/resource2_tmp_uploads';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0775, true)) {
        die(json_encode(['OK' => 0, 'info' => 'Cannot create temp directory.']));
    }
}

$file_path = $upload_dir . '/' . $temp_key . '_replace.part_assembled';

// ── Append chunk ───────────────────────────────────────────────────────────
$out = @fopen("{$file_path}.part", $chunk === 0 ? 'wb' : 'ab');
if (!$out) {
    die(json_encode(['OK' => 0, 'info' => 'Cannot open temp file for writing.']));
}
$in = @fopen($_FILES['file']['tmp_name'], 'rb');
if (!$in) {
    @fclose($out);
    die(json_encode(['OK' => 0, 'info' => 'Cannot read uploaded chunk.']));
}
while ($buff = fread($in, 65536)) {
    fwrite($out, $buff);
}
@fclose($in);
@fclose($out);
@unlink($_FILES['file']['tmp_name']);

// ── Intermediate chunks — just ACK ─────────────────────────────────────────
if ($chunks > 1 && $chunk < $chunks - 1) {
    die(json_encode(['OK' => 1, 'chunk' => $chunk]));
}

// ── Final chunk — finalize and spawn background replace ────────────────────
rename("{$file_path}.part", $file_path);

$vname      = trim($_POST['vname']      ?? '');
$video_type = (int)($_POST['video_type'] ?? 0);

if ($vname === '') {
    $vname = $vimeo_rec->name ?: 'video_' . time();
}

// The Vimeo URI is "/videos/{numeric_id}" — built from the stored url field.
$existing_url = trim($vimeo_rec->url);
if (!ctype_digit($existing_url)) {
    // Video is still uploading or in an unknown state — cannot replace.
    @unlink($file_path);
    die(json_encode(['OK' => 0, 'info' => 'Cannot replace: current video is still uploading or has no Vimeo ID.']));
}
$vimeo_uri = '/videos/' . $existing_url;

// ── Update DB record immediately ───────────────────────────────────────────
// Clear url → view.php shows the "uploading" spinner while vimeo_bg runs.
$upd       = new stdClass();
$upd->id   = $vimeo_rec->id;
$upd->url  = '';       // cleared; vimeo_bg.php will set it back to the same ID after replace
$upd->name = $vname;
$DB->update_record('vimeo_files2', $upd);

// Update video type in reda_video_type2 if a new one was supplied.
$type_record_id = 0;
if ($video_type > 0) {
    $type_rec = $DB->get_record('reda_video_type2', ['resource2_id' => $resource2_id]);
    if ($type_rec) {
        $type_rec->type = $video_type;
        $DB->update_record('reda_video_type2', $type_rec);
        $type_record_id = $type_rec->id;
    }
}

// ── Write params JSON for vimeo_bg.php ────────────────────────────────────
$params_file = $upload_dir . '/vimeo_params_' . $vimeo_rec->id . '_' . time() . '.json';
file_put_contents($params_file, json_encode([
    'mode'           => 'replace',
    'file'           => $file_path,
    'id'             => $resource2_id,
    'name'           => $vname,
    'description'    => $vimeo_rec->description ?: $vname,
    'record_id'      => $vimeo_rec->id,
    'vimeo_uri'      => $vimeo_uri,
    'type'           => (string)$video_type,
    'type_record_id' => $type_record_id,
]));

// ── Spawn vimeo_bg.php in background ──────────────────────────────────────
$php     = _resource2_replace_find_php();
$bg      = escapeshellarg($CFG->dirroot . '/test2/vimeo_bg.php');
$pf      = escapeshellarg($params_file);
$logfile = escapeshellarg($upload_dir . '/vimeo_bg.log');
exec("$php $bg $pf >> $logfile 2>&1 &");

die(json_encode(['OK' => 1]));

// ── Helper ─────────────────────────────────────────────────────────────────
function _resource2_replace_find_php(): string {
    $php = trim((string)shell_exec('which php'));
    if ($php && is_executable($php)) {
        return $php;
    }
    foreach (['/usr/bin/php', '/usr/bin/php8.2', '/usr/bin/php8.1',
              '/usr/bin/php8.0', '/usr/local/bin/php'] as $try) {
        if (is_executable($try)) {
            return $try;
        }
    }
    return '/usr/bin/php';
}
