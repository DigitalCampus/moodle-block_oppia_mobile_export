<?php 

require_once(dirname(__FILE__) . '/../../config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/lib.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/langfilter.php');

require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/activity.class.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/page.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/quiz.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/resource.php');

require_once($CFG->libdir.'/componentlib.class.php');

$digest = required_param('digest', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);

$server_connection = $DB->get_record('block_oppia_mobile_server', array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner','block_oppia_mobile_export')."</p>";
	echo "$OUTPUT->footer();";
	die();
}
if ($server == "default"){
	$server_connection = new stdClass();
	$server_connection->url = $CFG->block_oppia_mobile_export_default_server;
	$server_connection->username = $CFG->block_oppia_mobile_export_default_username;
	$server_connection->apikey = $CFG->block_oppia_mobile_export_default_api_key;
}

if (substr($server_connection->url, -strlen('/'))!=='/'){
	$server_connection->url .= '/';
}

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $server_connection->url ."api/media/".$digest );
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($curl);
$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close ($curl);

$json_response = json_decode($result, true);
http_response_code($http_status);
header('Content-Type: application/json');

if (is_null($json_response)){
	echo json_encode(array('error'=>'not_valid_json'));
}
else{

	header('Content-Type: application/json');
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
	echo json_encode($media_info);
}


?>
