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
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');

$id = required_param('id', PARAM_INT);
$file = required_param('file', PARAM_TEXT);
$tags = cleanTagList(required_param('tags', PARAM_TEXT));
$server = required_param('server_id', PARAM_TEXT);
$username = required_param('username', PARAM_TEXT);
$password = required_param('password', PARAM_TEXT);
$course_status = required_param('course_export_status', PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url(PLUGINPATH.'publish/course.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
	print_error('nocontext');
}

require_login($course);

$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);

$PAGE->set_context($context);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));

add_or_update_oppiaconfig($id, 'is_draft', $is_draft);
add_publishing_log($server_connection->url, $USER->id, $id, "api_publish_start", "API publish process started");

echo $OUTPUT->header();

flush_buffers();

echo "<h2>";
if ($course_status == 'draft'){
    echo get_string('publishing_header_draft', PLUGINNAME);
} else {
    echo get_string('publishing_header_live', PLUGINNAME);
}
echo "</h2>";

flush_buffers();

if (trim($username) == ''){
	echo "<p>".get_string('publish_error_username', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
	die();
}

if (trim($password) == ''){
	echo "<p>".get_string('publish_error_password', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
	die();
}
if (trim($tags) == ''){
	echo "<p>".get_string('publish_error_tags', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
	die();
}

if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
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

if ($course_status == 'draft'){
    $is_draft = "true";
} else {
    $is_draft = "false";
}

$filepath = $pluginroot.OPPIA_OUTPUT_DIR.$USER->id."/".$file;
$curlfile = new CurlFile($filepath, 'application/zip', $file);

$post = array(
		'username' => $username,
		'password' => $password,
		'tags' 	   => $tags,
		'is_draft' => $is_draft,
		'course_file' => $curlfile);

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $server_connection->url."api/publish/" );
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_VERBOSE, true);

$result = curl_exec($curl);
$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close ($curl);

add_publishing_log($server_connection->url, $USER->id, $id, "api_publishing_user", $username);
add_publishing_log($server_connection->url, $USER->id, $id, "api_file_posted", $file);

switch ($http_status){
	case "405":
		$msgtext = get_string('publish_message_405', PLUGINNAME);
		show_and_log_message($msgtext, false, "api_publish_invalid_request", false);
		break;
	case "400":
		$msgtext = get_string('publish_message_400', PLUGINNAME);
		show_and_log_message($msgtext, false, "api_publish_bad_request", false);
		break;
	case "401":
		$msgtext = get_string('publish_message_401', PLUGINNAME);
		show_and_log_message($msgtext, false, "api_publish_unauthorised", false);
		break;
	case "500":
		$msgtext = get_string('publish_message_500', PLUGINNAME);
		show_and_log_message($msgtext, false, "api_publish_server_error", false);
		break;
	case "201":
		$msgtext = get_string('publish_message_201', PLUGINNAME);
		show_and_log_message($msgtext, false, "api_publish_success", false);
		break;
	default:
		
}
$json_response = json_decode($result, true);

if (is_null($json_response)){
	echo $result;
}
else{

	if (array_key_exists('message', $json_response)){
		show_and_log_message($json_response['message'], false, "api_publish_response", false);
	}
	if (array_key_exists('messages', $json_response)){
		$messages = $json_response['messages'];
		foreach($messages as $msg){
			show_and_log_message($msg['message'], $msg['tags'], "api_publish_bad_request", true);
		}
	}
	if (array_key_exists('errors', $json_response)){
		$errors = $json_response['errors'];
		foreach($errors as $err){
			show_and_log_message($err, 'warning', "api_publish_response_message", true);
		}
	}
	
}



add_publishing_log($server_connection->url, $USER->id, $id, "api_publish_end", "API publish process ended");

echo $OUTPUT->footer();


// Function to show on screen the message and save it in the publishing log
function show_and_log_message($message, $tags, $log_action, $show_dialog=false){
	echo '<div class="' . ($show_dialog ? 'export-results' : '') . ' ' .$tags.'">'.$message.'</div>';
	add_publishing_log($server_connection->url, $USER->id, $id, $log_action, ($tags ? $tags.':' : '').$message);
}

?>
