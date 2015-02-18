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

$id = required_param('id',PARAM_INT);
$file = required_param('file',PARAM_TEXT);
$tags = required_param('tags',PARAM_TEXT);
$server = required_param('server',PARAM_TEXT);
$username = required_param('username',PARAM_TEXT);
$password = required_param('password',PARAM_TEXT);
$is_draft = optional_param('is_draft','False', PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url('/blocks/oppia_mobile_export/publish_course.php', array('id' => $id));
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


add_or_update_oppiaconfig($id, 'is_draft', $is_draft);

echo $OUTPUT->header();

echo "<h2>Publishing course</h2>";
flush_buffers();

$server_connection = $DB->get_record('block_oppia_mobile_server', array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner','block_oppia_mobile_export')."</p>";
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

$post =  array('username' => $username,
				'password' => $password,
				'is_draft' => $is_draft,
				'tags' => $tags,
				'course_file' => new CurlFile($file, 'application/zip') 
);
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt($curl, CURLOPT_URL, $server_connection->url ."api/publish/" );
curl_setopt($curl, CURLOPT_POST,1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
$result = curl_exec($curl);
$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

switch ($http_status){
	case "405":
		echo "<p>".get_string('publish_message_405','block_oppia_mobile_export')."</p>";
		break;
	case "400":
		echo "<p>".get_string('publish_message_400','block_oppia_mobile_export')."</p>";
		break;
	case "401":
		echo "<p>".get_string('publish_message_401','block_oppia_mobile_export')."</p>";
		break;
	case "500":
		echo "<p>".get_string('publish_message_500','block_oppia_mobile_export')."</p>";
		break;
	case "201":
		echo "<p>".get_string('publish_message_201','block_oppia_mobile_export')."</p>";
		break;
	default:
		
}

curl_close ($curl);


echo $OUTPUT->footer();


?>
