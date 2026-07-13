<?php
require_once('../config.php');
require_once('../vimeo/vendor/autoload.php');

use Vimeo\Vimeo;

if (empty($_FILES) || $_FILES['file']['error']) {
    die('{"OK": 0, "info": "Failed to move uploaded file."}');
}

$chunk  = isset($_REQUEST["chunk"])  ? intval($_REQUEST["chunk"])  : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

// ── Always use absolute paths ─────────────────────────────────────────────
// fopen() with a relative path resolves against PHP-FPM's CWD (often /),
// not the script directory. Use __DIR__ everywhere.
$chunks_dir = __DIR__ . '/chunks';
if (!is_dir($chunks_dir)) {
    mkdir($chunks_dir, 0775, true);
}

$fileName  = $_REQUEST['did'] . '_' . (isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"]);
$filePath  = $chunks_dir . '/' . $fileName;        // absolute
$filePathRel = 'chunks/' . $fileName;              // kept for the JSON response only

// Open temp file
$out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
if ($out) {
    // Read binary input stream and append it to temp file
    $in = @fopen($_FILES['file']['tmp_name'], "rb");

    if ($in) {
        while ($buff = fread($in, 4096))
            fwrite($out, $buff);
    } else
        die('{"OK": 0, "info": "Failed to open input stream."}');

    @fclose($in);
    @fclose($out);

    @unlink($_FILES['file']['tmp_name']);
} else
    die('{"OK": 0, "info": "Failed to open output stream."}');


// ── Helper: find PHP CLI binary ───────────────────────────────────────────
// PHP_BINARY under PHP-FPM is the FPM binary, not the CLI binary.
function find_php_cli_uc() {
    $php = trim(shell_exec('which php') ?: '');
    if ($php && is_executable($php)) return $php;
    foreach ([
        '/usr/bin/php',
        '/usr/bin/php8.2', '/usr/bin/php8.1', '/usr/bin/php8.0',
        '/usr/bin/php7.4', '/usr/local/bin/php',
    ] as $try) {
        if (is_executable($try)) return $try;
    }
    return '/usr/bin/php';
}

// ── Helper: spawn vimeo_bg.php with params JSON ───────────────────────────
function spawn_vimeo_bg_uc(array $params) {
    $chunks_dir  = __DIR__ . '/chunks';
    $suffix      = ($params['mode'] === 'replace' ? 'replace_' : '') . $params['record_id'] . '_' . time();
    $params_file = $chunks_dir . '/vimeo_params_' . $suffix . '.json';
    file_put_contents($params_file, json_encode($params));

    $php     = escapeshellarg(find_php_cli_uc());
    $bg      = escapeshellarg(__DIR__ . '/vimeo_bg.php');
    $pf      = escapeshellarg($params_file);
    $logfile = escapeshellarg($chunks_dir . '/vimeo_bg.log');
    exec("$php $bg $pf >> $logfile 2>&1 &");
}


// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
    // Strip the temp .part suffix off
    rename("{$filePath}.part", $filePath);

    $id          = $_POST['did'];
    $name        = $_POST['dname'];
    $description = $_POST['ddescription'];
    $type        = $_POST['dtype'];

    if (!isset($_POST['update_form'])) {
        // ── NEW UPLOAD ────────────────────────────────────────────────────
        try {
            $data               = new stdClass();
            $data->resource2_id = $id;
            $data->type         = $type;
            $DB->insert_record('reda_video_type2', $data);

            $ins               = new stdClass();
            $ins->name         = $name;
            $ins->description  = $description;
            $ins->resource2_id = $id;
            $ins->url          = ''; // filled in by vimeo_bg.php after upload
            $vimeo_record_id   = $DB->insert_record('vimeo_files2', $ins);

            spawn_vimeo_bg_uc([
                'mode'        => 'upload',
                'file'        => $filePath,   // already absolute
                'id'          => $id,
                'name'        => $name,
                'description' => $description,
                'record_id'   => $vimeo_record_id,
            ]);

        } catch (Exception $e) {
            error_log('upload_chunked.php new-upload DB error: ' . $e->getMessage());
            @unlink($filePath);
        }

        die('{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePathRel . '"}');

    } else {
        // ── UPDATE (REPLACE) EXISTING VIDEO ──────────────────────────────
        try {
            if (isset($_POST['files_count']) && $_POST['files_count'] == 0) {
                // ── Metadata-only update — no file upload, fast PATCH ─────
                $client   = new Vimeo(
                    "4dad588b7f47a44426afc26f398fe2367ea49c92",
                    "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s",
                    "195c95a4e775fca8d6e70cb8db4aca73"
                );
                $record   = $DB->get_record('vimeo_files2', ['resource2_id' => $id]);
                $video_id = str_replace('videos/', '', $record->url);
                $uri      = '/videos/' . $video_id;

                $client->request($uri, ['name' => $name, 'description' => $description], 'PATCH');

                $ins              = new stdClass();
                $ins->id          = $record->id;
                $ins->name        = $name;
                $ins->description = $description;
                $ins->url         = $video_id;
                $DB->update_record('vimeo_files2', $ins);

                $typeData         = $DB->get_record('reda_video_type2', ['resource2_id' => $id]);
                $video_type       = new stdClass();
                $video_type->id   = $typeData->id;
                $video_type->type = $type;
                $DB->update_record('reda_video_type2', $video_type);

                @unlink($filePath);

            } else {
                // ── Full video replace — background process ───────────────
                $record    = $DB->get_record('vimeo_files2', ['resource2_id' => $id]);
                $video_id  = str_replace('videos/', '', $record->url);
                $vimeo_uri = '/videos/' . $video_id;

                $typeData  = $DB->get_record('reda_video_type2', ['resource2_id' => $id]);

                spawn_vimeo_bg_uc([
                    'mode'           => 'replace',
                    'file'           => $filePath,   // already absolute
                    'id'             => $id,
                    'name'           => $name,
                    'description'    => $description,
                    'record_id'      => (int)$record->id,
                    'vimeo_uri'      => $vimeo_uri,
                    'type'           => $type,
                    'type_record_id' => $typeData ? (int)$typeData->id : 0,
                ]);
            }

        } catch (Exception $e) {
            error_log('upload_chunked.php update error: ' . $e->getMessage());
            @unlink($filePath);
        }

        die('{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePathRel . '"}');
    }
}

die('{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePathRel . '"}');
