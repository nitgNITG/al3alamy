<?php
require_once('../config.php');
require '../vimeo/vendor/autoload.php';

use Vimeo\Vimeo;

function vimeo($url, $name, $description, $id)
{
	$client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");

	$file_name = $url;
	$uri = $client->upload($file_name, array(
		"name" => $name,
		"description" => $description
	));

	$response = $client->request($uri . '?fields=transcode.status');
	if ($response['body']['transcode']['status'] === 'complete') {
		// transcoding done
	} elseif ($response['body']['transcode']['status'] === 'in_progress') {
		// still transcoding
	} else {
		 // error during transcoding
	}

	$response = $client->request($uri . '?fields=link');
	return $response['body']['link'];
}

if (empty($_FILES) || $_FILES['file']['error']) {
	die('{"OK": 0, "info": "Failed to move uploaded file."}');
}

$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

$fileName = $_REQUEST['did'] . '_' . (isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"]);
$filePath = "chunks/$fileName";

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


// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
	// Strip the temp .part suffix off
	rename("{$filePath}.part", $filePath);

	$id = $_POST['did'];
	$name = $_POST['dname'];
	$description = $_POST['ddescription'];
	$type = $_POST['dtype'];
	if (!isset($_POST['update_form'])) {
		try {
			$ins = new stdClass();
			$ins->name = $name;
			$ins->description = $description;
			$ins->resource2_id = $id;

			$data = new stdClass();
			$data->resource2_id = $id;
			$data->type = $type;
			$data->id = $DB->insert_record('reda_video_type2', $data);

			// ── Send success response to browser BEFORE the long Vimeo upload ──
			// This prevents plupload from timing out while we upload to Vimeo.
			$response_json = '{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePath . '"}';
			header('Content-Type: application/json');
			header('Content-Length: ' . strlen($response_json));
			header('Connection: close');
			echo $response_json;
			if (ob_get_level()) ob_end_flush();
			flush();

			// ── Now upload to Vimeo in the background (no timeout risk) ──
			ignore_user_abort(true);
			set_time_limit(0);

			$output = vimeo($filePath, $ins->name, $ins->description, $id);
			$last_string = substr($output, strrpos($output, '/') + 1);
			$first_string = preg_replace('/\s+?(\S+)?$/', '', substr($output, 0, 18));
			$result = str_replace($first_string, '', $output);
			$result = str_replace($last_string, '', $result);
			$ins->url = $result;
			$ins->id = $DB->insert_record('vimeo_files2', $ins);
			unlink($filePath);
		} catch (Exception $e) {
			error_log('upload_chunked.php Vimeo upload error: ' . $e->getMessage());
			@unlink($filePath);
		}
		exit; // response already sent above
	} else {
		try {
			$client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
			$record = $DB->get_record("vimeo_files2", array("resource2_id" => $id));
			$last_string = substr($record->url, strrpos($record->url, '/') + 1);
			$first_string = preg_replace('/\s+?(\S+)?$/', '', substr($record->url, 0, 18));
			$result = str_replace('videos/', '', $record->url);
			$uri = "/videos/" . $result;
			$response = $client->request($uri, [], 'GET');
			$ins = new stdClass();
			$ins->id = $record->id;

			// ── Send success to browser before the long replace upload ──
			$response_json = '{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePath . '"}';
			header('Content-Type: application/json');
			header('Content-Length: ' . strlen($response_json));
			header('Connection: close');
			echo $response_json;
			if (ob_get_level()) ob_end_flush();
			flush();

			ignore_user_abort(true);
			set_time_limit(0);

			if (isset($_POST['files_count']) && $_POST['files_count'] == 0) {
				$request = $client->request($uri, array(
					'name' => $name,
					'description' => $description
				),  'PATCH');
				$ins->name = $name;
				$ins->description = $description;
				$ins->url = $result;
				$ins->id = $DB->update_record('vimeo_files2', $ins);
				$typeData = $DB->get_record('reda_video_type2', array('resource2_id' => $id));

				$video_type = new stdClass();
				$video_type->id = $typeData->id;
				$video_type->type = $type;
				$DB->update_record('reda_video_type2', $video_type);
				unlink($filePath);
			} else {
				$response = $client->replace($uri, $filePath, []);
				$request = $client->request($uri, array(
					'name' => $name,
					'description' => $description
				),  'PATCH');
				$last_word_start = strrpos($response, ' ') + 1;
				$last_word = substr($response, $last_word_start);
				$last_word = str_replace('videos/', '', $last_word);
				$ins->name = $name;
				$ins->description = $description;
				$ins->url = $last_word;
				$ins->id = $DB->update_record('vimeo_files2', $ins);
				$typeData = $DB->get_record('reda_video_type2', array('resource2_id' => $id));

				$video_type = new stdClass();
				$video_type->id = $typeData->id;
				$video_type->type = $type;
				$DB->update_record('reda_video_type2', $video_type);
				unlink($filePath);
			}
		} catch (Exception $e) {
			error_log('upload_chunked.php Vimeo replace error: ' . $e->getMessage());
			@unlink($filePath);
		}
		exit; // response already sent above
	}
}

die('{"OK": 1, "info": "Upload successful.", "filepath":"' . $filePath . '"}');
