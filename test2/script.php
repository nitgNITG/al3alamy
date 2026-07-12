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
		"description" => "$description"
	));

	$response = $client->request($uri . '?fields=transcode.status');
	if ($response['body']['transcode']['status'] === 'complete') {
		//       print '
		//       <div class="alert alert-success" role="alert">
		//       Your video finished transcoding.
		//  </div>
		//       ';
		// redirect($CFG->wwwroot.'/teacherprofile/profile.php?id='.$id.'');
	} elseif ($response['body']['transcode']['status'] === 'in_progress') {
		// print '<div class="spinner-border text-warning"></div>
		//   <span>Your video is still transcoding Refresh again after a while.</span>
		//   ';
	} else {
		// print 'Your video encountered an error during transcoding.';
	}

	$response = $client->request($uri . '?fields=link');
	return $response['body']['link'];
}
//Add video on an external server
//upload on NIT server
if (isset($_FILES['file']['name'])) {
	$size = $_POST['size'];
	$state = $_POST['state'];
	$update = $_POST['update'];
	$cm = $_POST['cm'];
	$cmc = $_POST['cmc'];
	$id = $_POST['resource2_id'];
	$type = $_POST['type'];
	$getSize = $DB->get_record('control_max_size', array('userid' => $USER->id));

	$enrolled_students = $DB->get_records_sql("SELECT COUNT(u.id) As total
FROM mo_course c LEFT OUTER JOIN mo_context cx ON c.id = cx.instanceid 
LEFT OUTER JOIN mo_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 LEFT OUTER JOIN mo_user u ON ra.userid = u.id 
 WHERE cx.contextlevel = '50' AND c.id=$cmc");
	$getQuota = $DB->get_record('student_quota', array('teacher_id' => $USER->id));

	$total = 0;
	foreach ($enrolled_students as $enrolled_student) {
		$total = $enrolled_student->total;
	}
	if ($state == "still") {
		$ins = new stdClass();
		if (empty($getSize)) {

			$ins = new stdClass();
			$ins->userid = $USER->id;
			$ins->size = $_POST['size'];
			$ins->max_size =  1048576 * $getQuota->quota * $total; //in bytes
			date_default_timezone_set("Africa/Egypt");
			$date = date('Y-m-d H:i:s');
			$date = strtotime($date);
			$date = strtotime("+7 day", $date);
			$ins->empty = $date;
			$DB->insert_record('control_max_size', $ins);
		} else {


			$up = new stdClass();
			$up->id = $getSize->id;
			$up->size = $_POST['size'];
			$DB->update_record('control_max_size', $up);
		}
	}
	$ftp_server = "ftp.nitg-eg.com";
	// name file in serverA that you want to store file in serverB
	$file = $_FILES['file']['tmp_name'];
	date_default_timezone_set("Africa/Egypt");
	$server_root = "public_html/academyvideos/";
	$file_name = date("Y-m-d h:i:sa") . $_FILES['file']['name'];
	$file_name = preg_replace('/\s+/', '', $file_name);

	$remote_file = '/' . $server_root . '' . $file_name;
	$ftp_user_name = "nitgegco";
	$ftp_user_pass = "Nitg@2019AHmed";
	try {
		$con = ftp_connect($ftp_server);
		if (false === $con) {
			throw new Exception('Unable to connect');
		}

		$loggedIn = ftp_login($con,  $ftp_user_name,  $ftp_user_pass);
		ftp_set_option($con, FTP_USEPASVADDRESS, false);
		ftp_pasv($con, true);
		if (true === $loggedIn) {
			$upload = ftp_put($con, $remote_file, $file, FTP_BINARY);
			if ($upload) {
				if ($update == 1) {
					$resource2 = $DB->get_record('vimeo_files2', array('resource2_id' => $id));
					$delfile = '/' . $server_root . '' . $resource2->name;
					$ftp_old_size = ftp_size($con, $delfile);
					if (ftp_delete($con, $delfile)) {
						$up = new stdClass();
						$up->id = $resource2->id;
						$up->name = $file_name;
						$up->description = $file_name;
						// $ins->url ='<iframe src="https://nitg-eg.com/academyvideos/' . $file_name . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';

						$up->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "' width='640' height='360'frameborder='0' allow='autoplay; fullscreen; picture-in-picture' allowfullscreen></iframe>";
						$DB->update_record('vimeo_files2', $up);

						$up = new stdClass();
						$up->id = $getSize->id;
						$newSize = $size  - $ftp_old_size;
						$up->size = $newSize;
						$DB->update_record('control_max_size', $up);
					} else {
						echo "Error deleting file";
					}
				} else {
					$ins = new stdClass();

					$ins->name =  $file_name;
					$ins->description =  $file_name;
					$ins->resource2_id = $id;
					$ins->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "' width='640' height='360'frameborder='0' allow='autoplay; fullscreen; picture-in-picture' allowfullscreen></iframe>";
					$ins->id = $DB->insert_record('vimeo_files2', $ins);
					$data = new stdClass();
					$data->resource2_id = $id;
					$data->type = $type;
					$data->id = $DB->insert_record('reda_video_type2', $data);
				}
			} else {
				echo "error : There was a problem while uploading $file\n";
			}
		} else {
			throw new Exception('Unable to log in');
		}
	} catch (Exception $e) {
		echo "Failure: " . $e->getMessage();
	}
}
//update video on an external server
if (isset($_FILES['fileA']['name'])) {
	$size = $_POST['sizeA'];
	$state = $_POST['stateA'];
	$update = $_POST['updateA'];
	$cm = $_POST['cmA'];
	$cmc = $_POST['cmcA'];
	$id = $_POST['resource2_idA'];
	$type = $_POST['type'];

	$enrolled_students = $DB->get_records_sql("SELECT COUNT(u.id) As total
FROM mo_course c LEFT OUTER JOIN mo_context cx ON c.id = cx.instanceid 
LEFT OUTER JOIN mo_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
 LEFT OUTER JOIN mo_user u ON ra.userid = u.id 
 WHERE cx.contextlevel = '50' AND c.id=$cmc");
	$total = 0;
	foreach ($enrolled_students as $enrolled_student) {
		$total = $enrolled_student->total;
	}
	if ($state == "still") {
		$ins = new stdClass();
		$getSize = $DB->get_record('control_max_size', array('userid' => $USER->id));
		if (empty($getSize)) {

			$ins = new stdClass();
			$ins->userid = $USER->id;
			$ins->size = $size;
			$ins->max_size =  2097152 * $total; //in bytes
			date_default_timezone_set("Africa/Egypt");
			$date = date('Y-m-d H:i:s');
			$date = strtotime($date);
			$date = strtotime("+7 day", $date);
			$ins->empty = $date;
			$DB->insert_record('control_max_size', $ins);
		} else {


			$up = new stdClass();
			$up->id = $getSize->id;
			$up->size = $size;
			$DB->update_record('control_max_size', $up);
		}
	}
	$ftp_server = "ftp.nitg-eg.com";
	// name file in serverA that you want to store file in serverB
	$file = $_FILES['fileA']['tmp_name'];
	date_default_timezone_set("Africa/Egypt");
	$server_root = "public_html/academyvideos/";
	$file_name = date("Y-m-d h:i:sa") . $_FILES['fileA']['name'];
	$file_name = preg_replace('/\s+/', '', $file_name);

	$remote_file = '/' . $server_root . '' . $file_name;
	$ftp_user_name = "nitgegco";
	$ftp_user_pass = "Nitg@2019AHmed";
	try {
		$con = ftp_connect($ftp_server);
		if (false === $con) {
			throw new Exception('Unable to connect');
		}

		$loggedIn = ftp_login($con,  $ftp_user_name,  $ftp_user_pass);
		ftp_set_option($con, FTP_USEPASVADDRESS, false);
		ftp_pasv($con, true);
		if (true === $loggedIn) {
			$upload = ftp_put($con, $remote_file, $file, FTP_BINARY);
			if ($upload) {
				if ($update == 1) {
					$resource2 = $DB->get_record('vimeo_files2', array('resource2_id' => $id));
					$delfile = '/' . $server_root . '' . $resource2->name;
					$ftp_old_size = ftp_size($con, $delfile);

					if (ftp_delete($con, $delfile)) {
						$up = new stdClass();
						$up->id = $resource2->id;
						$up->name = $file_name;
						$up->description = $file_name;
						$up->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "' width='640' height='360'frameborder='0' allow='autoplay; fullscreen; picture-in-picture' allowfullscreen></iframe>";
						$DB->update_record('vimeo_files2', $up);
					} else {
						echo "Error deleting file";
					}
				} else {
					$ins = new stdClass();

					$ins->name = $file_name;
					$ins->description = $file_name;
					$ins->resource2_id = $id;
					$ins->url = "<iframe src='https://nitg-eg.com/academyvideos/" . $file_name . "' width='640' height='360'frameborder='0' allow='autoplay; fullscreen; picture-in-picture' allowfullscreen></iframe>";
					$ins->id = $DB->insert_record('vimeo_files2', $ins);
					$data = new stdClass();
					$data->resource2_id = $id;
					$data->type = $type;
					$data->id = $DB->insert_record('reda_video_type2', $data);
				}
			} else {
				echo "error : There was a problem while uploading $file\n";
			}
		} else {
			throw new Exception('Unable to log in');
		}
	} catch (Exception $e) {
		echo "Failure: " . $e->getMessage();
	}
}
//add to vimeo
if (isset($_FILES['url']['name'])) {
	$id          = $_POST['id'];
	$name        = $_POST['name'];
	$description = $_POST['description'];
	$type        = $_POST['type'];

	// ── 1. Move uploaded file to a permanent location ─────────────────────
	// PHP tmp files are deleted when the request ends — we must move it first.
	$chunks_dir = __DIR__ . '/chunks';
	if (!is_dir($chunks_dir)) {
		mkdir($chunks_dir, 0755, true);
	}
	$perm_file = $chunks_dir . '/vimeo_' . $id . '_' . time() . '.mp4';
	move_uploaded_file($_FILES['url']['tmp_name'], $perm_file);

	// ── 2. Insert DB records now (with placeholder URL) ───────────────────
	$ins = new stdClass();
	$ins->name        = $name;
	$ins->description = $description;
	$ins->resource2_id = $id;
	$ins->url         = '';
	$vimeo_record_id  = $DB->insert_record('vimeo_files2', $ins);

	$data = new stdClass();
	$data->resource2_id = $id;
	$data->type = $type;
	$DB->insert_record('reda_video_type2', $data);

	// ── 3. Spawn background process to upload to Vimeo ────────────────────
	// This decouples the Vimeo upload from the HTTP request completely.
	// Apache/PHP-FPM timeouts no longer affect the upload.
	$params = json_encode([
		'file'      => $perm_file,
		'id'        => $id,
		'name'      => $name,
		'description' => $description,
		'record_id' => $vimeo_record_id,
	]);
	$params_file = $chunks_dir . '/vimeo_params_' . $vimeo_record_id . '.json';
	file_put_contents($params_file, $params);

	$bg     = escapeshellarg($CFG->dirroot . '/test2/vimeo_bg.php');
	$pf     = escapeshellarg($params_file);
	$php    = escapeshellarg(PHP_BINARY); // full path — avoids PATH issues under PHP-FPM
	$logfile = escapeshellarg($chunks_dir . '/vimeo_bg.log');
	exec("$php $bg $pf >> $logfile 2>&1 &");

	// ── 4. Return empty response — JS redirect fires immediately ──────────
	exit;
}
//edit to vimeo

elseif (isset($_POST['update_form'])) {
	$id = $_POST['id'];
	$name = $_POST['name'];
	$description = $_POST['description'];
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

		// ── Prevent PHP timeout during long Vimeo replace upload ──────────
		set_time_limit(0);
		ignore_user_abort(true);

		if ($_FILES["url_update"]["name"] == "") {
			$request = $client->request($uri, array(
				'name' => $_POST['name'],
				'description' => $_POST['description']
			), 'PATCH');
			$ins->name = $_POST['name'];
			$ins->description = $_POST['description'];
			$ins->url = $result;
			$ins->id = $DB->update_record('vimeo_files2', $ins);
			$typeData=$DB->get_record('reda_video_type2',array('resource2_id'=>$id));

			$video_type=new stdClass();
			$video_type->id=$typeData->id;
			$video_type->type=$_POST['type'];
			$DB->update_record('reda_video_type2',$video_type);
		} else {
			$response = $client->replace($uri, $_FILES["url_update"]["tmp_name"], []);
			$request = $client->request($uri, array(
				'name' => $_POST['name'],
				'description' => $_POST['description']
			), 'PATCH');
			$last_word_start = strrpos($response, ' ') + 1;
			$last_word = substr($response, $last_word_start);
			$last_word = str_replace('videos/', '', $last_word);
			$ins->name = $_POST['name'];
			$ins->description = $_POST['description'];
			$ins->url = $last_word;
			$ins->id = $DB->update_record('vimeo_files2', $ins);
			$typeData=$DB->get_record('reda_video_type2',array('resource2_id'=>$id));

			$video_type=new stdClass();
			$video_type->id=$typeData->id;
			$video_type->type=$_POST['type'];
			$DB->update_record('reda_video_type2',$video_type);
		}
		// Output nothing on success — JS redirect fires when responseText is empty.
	} catch (Exception $e) {
		error_log('script.php Vimeo update error: ' . $e->getMessage());
		// Output nothing so redirect still fires; error is in PHP error log.
	}
}
