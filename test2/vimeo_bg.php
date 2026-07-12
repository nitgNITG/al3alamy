<?php
/**
 * Background Vimeo upload worker.
 *
 * Called by script.php via exec() after the browser has already been
 * redirected. Uploads the saved file to Vimeo and updates the DB record.
 *
 * Usage: php vimeo_bg.php /path/to/params.json
 */

define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/config.php');
require_once(dirname(__DIR__) . '/vimeo/vendor/autoload.php');

use Vimeo\Vimeo;

set_time_limit(0);

// ── Read params ──────────��────────────────────────────────────────────────
$params_file = $argv[1] ?? null;
if (!$params_file || !file_exists($params_file)) {
    error_log('vimeo_bg.php: params file not found: ' . $params_file);
    exit(1);
}

$params = json_decode(file_get_contents($params_file), true);
@unlink($params_file);

$perm_file  = $params['file']        ?? '';
$id         = $params['id']          ?? 0;
$name       = $params['name']        ?? '';
$description = $params['description'] ?? '';
$record_id  = $params['record_id']   ?? 0;

if (!$perm_file || !file_exists($perm_file) || !$record_id) {
    error_log('vimeo_bg.php: invalid params — file=' . $perm_file . ' record_id=' . $record_id);
    exit(1);
}

error_log('vimeo_bg.php: starting upload for resource_id=' . $id . ' file=' . $perm_file);

// ── Upload to Vimeo ───────────────────────────────────────────────────────
try {
    $client = new Vimeo(
        "4dad588b7f47a44426afc26f398fe2367ea49c92",
        "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s",
        "195c95a4e775fca8d6e70cb8db4aca73"
    );

    $uri = $client->upload($perm_file, [
        'name'        => $name,
        'description' => $description,
    ]);

    error_log('vimeo_bg.php: uploaded, uri=' . $uri);

    $response = $client->request($uri . '?fields=link');
    $output   = $response['body']['link'] ?? '';

    // Extract just the numeric video ID from the Vimeo URL.
    $video_id = basename(rtrim($output, '/'));

    // ── Update DB record with real video ID ───────────────────────────────
    $upd = new stdClass();
    $upd->id  = $record_id;
    $upd->url = $video_id;
    $DB->update_record('vimeo_files2', $upd);

    error_log('vimeo_bg.php: DB updated, video_id=' . $video_id);

    @unlink($perm_file);

} catch (Exception $e) {
    error_log('vimeo_bg.php: upload failed — ' . $e->getMessage());
    @unlink($perm_file);
    exit(1);
}

error_log('vimeo_bg.php: done');
exit(0);
