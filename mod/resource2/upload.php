<?php
/**
 * Pre-save Vimeo upload endpoint for mod_resource2.
 *
 * Called by the upload UI embedded in mod_form.php (new module creation).
 * Accepts a native-XHR chunked upload, enforces quotas, assembles the file,
 * inserts a placeholder vimeo_files2 row (resource2_id=0), spawns the
 * background vimeo_bg.php worker, and returns the new row's ID so the form
 * can carry it into resource2_add_instance() for atomic linking.
 *
 * POST params (multipart/form-data per chunk):
 *   file        — the binary chunk
 *   chunk       — 0-based chunk index
 *   chunks      — total number of chunks
 *   temp_key    — unique upload token (e.g. "u{userid}_{uniqid}")
 *   total_size  — total file size in bytes (sent on every chunk for quota check)
 *   vname       — Vimeo video title
 *   description — Vimeo video description
 *   video_type  — 1=Quiz 2=Lecture 3=Homework 4=Summary 5=Revision
 *   sesskey     — Moodle sesskey (CSRF protection)
 *   courseid    — course ID for capability check
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

// Capability check: user must be able to add course activities.
$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// ── Quota checks (always run, even mid-upload, so first chunk enforces them) ─
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

// Per-file size check (browser sends total_size on every chunk).
$total_size_bytes = (int)($_POST['total_size'] ?? 0);
if ($total_size_bytes > 0) {
    $total_size_mb = $total_size_bytes / (1024 * 1024);
    if ($total_size_mb > $max_size_mb) {
        die(json_encode(['OK' => 0,
            'info' => get_string('quota_error_size', 'resource2',
                (object)['size' => round($total_size_mb, 1), 'max' => $max_size_mb])]));
    }
}

// ── Validate incoming chunk ────────────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error']) {
    die(json_encode(['OK' => 0, 'info' => 'No file chunk received.']));
}

$chunk     = max(0, (int)($_REQUEST['chunk']  ?? 0));
$chunks    = max(1, (int)($_REQUEST['chunks'] ?? 1));
$temp_key  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['temp_key'] ?? 'u' . $USER->id);

// ── Temp directory for assembled chunks ───────────────────────────────────
$upload_dir = $CFG->dataroot . '/resource2_tmp_uploads';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0775, true)) {
        die(json_encode(['OK' => 0, 'info' => 'Cannot create temp directory.']));
    }
}

$file_name = $temp_key . '_video.part_assembled';
$file_path = $upload_dir . '/' . $file_name;

// Append this chunk to the assembled file.
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

// ── Intermediate chunks — just ACK ────────────────────────────────────────
if ($chunks > 1 && $chunk < $chunks - 1) {
    die(json_encode(['OK' => 1, 'chunk' => $chunk]));
}

// ── Final chunk received — finalize and spawn background upload ───────────
rename("{$file_path}.part", $file_path);

$vname       = trim($_POST['vname']       ?? '');
$description = trim($_POST['description'] ?? $vname);
$video_type  = (int)($_POST['video_type'] ?? 2);

if ($vname === '') {
    $vname = 'video_' . time();
}

// Create (or reuse) a placeholder vimeo_files2 row with resource2_id = 0.
// resource2_add_instance() will link it to the real ID after form save.
//
// IMPORTANT: vimeo_files2 has a UNIQUE constraint on resource2_id.
// If a previous upload was abandoned (browser closed before saving), a row
// with resource2_id = 0 already exists. We handle this by:
//   1. Trying a normal INSERT.
//   2. If that fails (duplicate key), look for the orphan row.
//      a. If its url is empty (no Vimeo video yet) → UPDATE it and reuse.
//      b. If it already has a Vimeo ID (bg upload completed) → delete that
//         row first, then INSERT fresh (the video was orphaned anyway).
$vf_columns = $DB->get_columns('vimeo_files2');

$ins               = new stdClass();
$ins->name         = $vname;
$ins->description  = $description;
$ins->resource2_id = 0;
$ins->url          = '';
if (isset($vf_columns['timecreated'])) {
    $ins->timecreated = time();
}

try {
    $record_id = $DB->insert_record('vimeo_files2', $ins);
} catch (dml_write_exception $e) {
    // Likely duplicate resource2_id = 0 from an abandoned upload.
    $orphan = $DB->get_record('vimeo_files2', ['resource2_id' => 0]);
    if ($orphan) {
        if (empty(trim((string)$orphan->url))) {
            // No Vimeo video was ever uploaded — safe to reuse this row.
            $upd              = new stdClass();
            $upd->id          = $orphan->id;
            $upd->name        = $vname;
            $upd->description = $description;
            $upd->url         = '';
            if (isset($vf_columns['timecreated'])) {
                $upd->timecreated = time();
            }
            $DB->update_record('vimeo_files2', $upd);
            $record_id = $orphan->id;
        } else {
            // Orphan already has a Vimeo ID (bg upload finished but form never
            // saved). Delete it — the cleanup cron would have removed it anyway.
            $DB->delete_records('vimeo_files2', ['id' => $orphan->id]);
            $record_id = $DB->insert_record('vimeo_files2', $ins);
        }
    } else {
        die(json_encode(['OK' => 0, 'info' => 'Database error: ' . $e->getMessage()]));
    }
}

// Write params JSON for vimeo_bg.php.
$params_file = $upload_dir . '/vimeo_params_' . $record_id . '_' . time() . '.json';
file_put_contents($params_file, json_encode([
    'mode'        => 'upload',
    'file'        => $file_path,   // absolute path
    'id'          => 0,            // no resource2_id yet
    'name'        => $vname,
    'description' => $description,
    'record_id'   => $record_id,
    'video_type'  => $video_type,
]));

// Spawn vimeo_bg.php in background.
$php     = _resource2_upload_find_php();
$bg      = escapeshellarg($CFG->dirroot . '/test2/vimeo_bg.php');
$pf      = escapeshellarg($params_file);
$logfile = escapeshellarg($upload_dir . '/vimeo_bg.log');
exec("$php $bg $pf >> $logfile 2>&1 &");

die(json_encode(['OK' => 1, 'record_id' => $record_id, 'video_type' => $video_type]));

// ── Helper ────────────────────────────────────────────────────────────────
function _resource2_upload_find_php(): string {
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
