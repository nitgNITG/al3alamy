<?php
/**
 * Background Vimeo upload/replace worker.
 *
 * Called by upload_chunked.php / script.php via exec() after the browser has
 * already been redirected. Uploads (or replaces) the saved file on Vimeo and
 * updates the DB record.
 *
 * Usage: php vimeo_bg.php /path/to/params.json
 *
 * Params JSON keys:
 *   mode          — "upload" (default) or "replace"
 *   file          — absolute path to assembled video file
 *   id            — resource2_id
 *   name          — video title
 *   description   — video description
 *   record_id     — vimeo_files2.id to update
 *   -- replace mode only --
 *   vimeo_uri     — existing Vimeo URI, e.g. "/videos/1234567890"
 *   type          — new video type string
 *   type_record_id — reda_video_type2.id to update
 */

define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/config.php');
require_once(dirname(__DIR__) . '/vimeo/vendor/autoload.php');

use Vimeo\Vimeo;

set_time_limit(0);

// ── Read params ───────────────────────────────────────────────────────────
$params_file = $argv[1] ?? null;
if (!$params_file || !file_exists($params_file)) {
    error_log('vimeo_bg.php: params file not found: ' . $params_file);
    exit(1);
}

$params = json_decode(file_get_contents($params_file), true);
@unlink($params_file);

$mode        = $params['mode']        ?? 'upload';
$perm_file   = $params['file']        ?? '';
$id          = $params['id']          ?? 0;
$name        = $params['name']        ?? '';
$description = $params['description'] ?? '';
$record_id   = $params['record_id']   ?? 0;

if (!$perm_file || !file_exists($perm_file) || !$record_id) {
    error_log('vimeo_bg.php: invalid params — file=' . $perm_file . ' record_id=' . $record_id);
    exit(1);
}

// ── Point TUS file cache to a per-upload writable directory ──────────────
// The default TUS cache dir (vendor/.cache/) is owned by root and not
// writable by www-data. Without a writable cache, TUS can't store the
// upload URL and falls back to a broken upload → 404-not-found.
//
// We use a unique sub-dir per record_id so concurrent uploads never share
// or clobber each other's TUS state. The chunks/ parent is never removed.
$tus_cache_dir = dirname($perm_file) . '/tus_cache_' . $record_id;
if (!is_dir($tus_cache_dir)) {
    mkdir($tus_cache_dir, 0775, true);
}
putenv('TUS_CACHE_HOME=' . $tus_cache_dir);

error_log('vimeo_bg.php: mode=' . $mode . ' starting for resource_id=' . $id . ' file=' . $perm_file);

// ── Helper: clean up this upload's temp files only ───────────────────────
// Deletes ONLY:
//   • the assembled video file for this specific upload
//   • the per-upload TUS cache sub-dir (tus_cache_{record_id}/)
// Never touches the chunks/ parent directory — other uploads may be running.
function cleanup_files($perm_file, $tus_cache_dir) {
    // Delete the assembled video file.
    @unlink($perm_file);

    // Delete files inside the per-upload TUS cache dir, then the dir itself.
    // rmdir only removes empty dirs, so this is safe even if glob returns nothing.
    $tus_files = glob($tus_cache_dir . '/*');
    if ($tus_files) {
        foreach ($tus_files as $f) { @unlink($f); }
    }
    @rmdir($tus_cache_dir); // removes only this upload's sub-dir, not chunks/
}

// ── Helper: find or create the Vimeo folder for this domain ──────────────
// Folder name = hostname of $CFG->wwwroot (e.g. "al3alamy.com").
// The resolved folder URI is cached in Moodle config so we never search
// more than once. If the cached folder was deleted on Vimeo, we re-search
// and re-cache automatically.
function get_or_create_vimeo_folder($client) {
    global $CFG;

    $folder_name = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'academy';

    // 1. Try cached URI first.
    $cached_uri = get_config('mod_resource2', 'vimeo_folder_uri');
    if ($cached_uri) {
        $check = $client->request($cached_uri, [], 'GET');
        if (($check['status'] ?? 0) === 200) {
            error_log('vimeo_bg.php: using cached folder ' . $cached_uri);
            return $cached_uri;
        }
        // Folder was deleted on Vimeo — clear cache and fall through.
        error_log('vimeo_bg.php: cached folder gone, re-searching...');
        set_config('vimeo_folder_uri', '', 'mod_resource2');
    }

    // 2. Search existing projects for a matching name (handles pagination).
    $page = 1;
    do {
        $resp = $client->request('/me/projects', [
            'fields'   => 'uri,name',
            'per_page' => 100,
            'page'     => $page,
        ], 'GET');

        if (($resp['status'] ?? 0) !== 200) break;

        foreach (($resp['body']['data'] ?? []) as $project) {
            if ($project['name'] === $folder_name) {
                $uri = $project['uri'];
                set_config('vimeo_folder_uri', $uri, 'mod_resource2');
                error_log('vimeo_bg.php: found existing folder ' . $uri);
                return $uri;
            }
        }

        $total   = (int)($resp['body']['total']    ?? 0);
        $fetched = $page * 100;
        $page++;
    } while ($fetched < $total);

    // 3. Folder not found — create it.
    $create = $client->request('/me/projects', ['name' => $folder_name], 'POST');
    if (($create['status'] ?? 0) === 201) {
        $uri = $create['body']['uri'];
        set_config('vimeo_folder_uri', $uri, 'mod_resource2');
        error_log('vimeo_bg.php: created new folder "' . $folder_name . '" → ' . $uri);
        return $uri;
    }

    error_log('vimeo_bg.php: could not create folder — status ' . ($create['status'] ?? '?'));
    return null;
}

// ── Vimeo credentials ─────────────────────────────────────────────────────
try {
    $client = new Vimeo(
        "4dad588b7f47a44426afc26f398fe2367ea49c92",
        "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s",
        "195c95a4e775fca8d6e70cb8db4aca73"
    );

    if ($mode === 'replace') {
        // ── Replace existing Vimeo video ──────────────────────────────────
        $vimeo_uri      = $params['vimeo_uri']      ?? '';
        $type           = $params['type']           ?? '';
        $type_record_id = (int)($params['type_record_id'] ?? 0);

        if (!$vimeo_uri) {
            error_log('vimeo_bg.php: replace mode requires vimeo_uri');
            cleanup_files($perm_file, $tus_cache_dir);
            exit(1);
        }

        error_log('vimeo_bg.php: replacing video at ' . $vimeo_uri);
        $client->replace($vimeo_uri, $perm_file, []);

        // Update title/description via PATCH.
        $client->request($vimeo_uri, [
            'name'        => $name,
            'description' => $description,
        ], 'PATCH');

        // Video ID stays the same after a replace.
        $video_id = basename($vimeo_uri); // e.g. "1209351976"

        // Update vimeo_files2 record.
        $upd = new stdClass();
        $upd->id          = $record_id;
        $upd->url         = $video_id;
        $upd->name        = $name;
        $upd->description = $description;
        $DB->update_record('vimeo_files2', $upd);

        // Update reda_video_type2 record if we have its ID.
        if ($type_record_id) {
            $video_type       = new stdClass();
            $video_type->id   = $type_record_id;
            $video_type->type = $type;
            $DB->update_record('reda_video_type2', $video_type);
        }

        error_log('vimeo_bg.php: replace done, video_id=' . $video_id);

    } else {
        // ── Upload new video to Vimeo ─────────────────────────────────────
        $uri = $client->upload($perm_file, [
            'name'        => $name,
            'description' => $description,
        ]);

        error_log('vimeo_bg.php: uploaded, uri=' . $uri);

        // $uri is "/videos/1209351976" — extract the numeric ID directly.
        // Do NOT use the link URL: Vimeo may append a privacy hash like
        // "https://vimeo.com/1209351976/9108e2cca5" making basename() return
        // the hash instead of the ID.
        $video_id = basename($uri); // = "1209351976"

        // Update DB record with real video ID.
        $upd      = new stdClass();
        $upd->id  = $record_id;
        $upd->url = $video_id;
        $DB->update_record('vimeo_files2', $upd);

        error_log('vimeo_bg.php: DB updated, video_id=' . $video_id);
    }

    // ── Clean up temp files ───────────────────────────────────────────────
    cleanup_files($perm_file, $tus_cache_dir);

} catch (Exception $e) {
    error_log('vimeo_bg.php: failed (' . $mode . ') — ' . $e->getMessage());
    cleanup_files($perm_file, $tus_cache_dir);
    exit(1);
}

error_log('vimeo_bg.php: done');
exit(0);
