<?php 

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;
require_once($pluginroot . 'lib.php');

require_once($CFG->libdir.'/componentlib.class.php');

$temp_media = $pluginroot.OPPIA_OUTPUT_DIR.$USER->id."/temp_media/";
mkdir($temp_media, 0777, true);

$server = required_param('server', PARAM_TEXT);
$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
	die();
}
if ($server == "default"){
	$server_connection = new stdClass();
	$server_connection->url = $CFG->block_oppia_mobile_export_default_server;
}
if (substr($server_connection->url, -strlen('/'))!=='/'){
	$server_connection->url .= '/';
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$digest = required_param('digest', PARAM_TEXT);
	$media_info = get_media_info($server_connection->url, $digest);
}
else{
	$file = required_param('moodlefile', PARAM_TEXT);
	$username = required_param('username', PARAM_TEXT);
	$password = required_param('password', PARAM_TEXT);
	$media_info = publish_media($server_connection->url, $file, $username, $password, $temp_media);
}

header('Content-Type: application/json');
if (!$media_info){
	echo json_encode(array('error'=>'not_valid_json'));
} else{
	echo json_encode($media_info);
}


function get_media_info($server, $digest){
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $server."api/media/".$digest );
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($curl);
	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close ($curl);

	return process_response($http_status, $response);
}


function publish_media($server, $moodlefile, $username, $password, $temp_media){

	list($contextid, $component, $filearea, $itemid, $path, $filename) = explode(";", $moodlefile);
    $file = get_file_storage()->get_file($contextid, $component, $filearea, $itemid, $path, $filename);
    
    if (!$file){
    	http_response_code(500);
    	return false;
    }

    $temppath = $temp_media . $filename;
    $success = $file->copy_content_to($temppath);
    if (!$success){
    	http_response_code(500);
    	return false;
    }
    $curlfile = new CurlFile($temppath, 'video/mp4', $filename);

	$post = array(
			'username' => $username,
			'password' => $password,
			'media_file' => $curlfile);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL,  $server."api/media/" );
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_VERBOSE, true);

	$response = curl_exec($curl);
	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close ($curl);

	//We remove the temporary copied file
	unlink($temppath);

	return process_response($http_status, $response);

}

function process_response($http_status, $response){
	
	$json_response = json_decode($response, true);
	http_response_code($http_status);

	if (!$http_status || $http_status == 404 || is_null($json_response)){
		if (!$http_status || !$response){
			http_response_code(400);
		}
		return false;
	}

	return get_mediainfo_from_response($json_response);
}


function get_mediainfo_from_response($json_response){
	$media_info = array();
	if (array_key_exists('download_url', $json_response)){
		$media_info['download_url'] = $json_response['download_url'];
	}
	if (array_key_exists('filesize', $json_response)){
		$media_info['filesize'] = $json_response['filesize'];
	}
	if (array_key_exists('length', $json_response)){
		$media_info['length'] = $json_response['length'];
	}
	return $media_info;

}

?>
