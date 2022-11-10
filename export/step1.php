<?php 
/**
 * Oppia Mobile Export
 * Step 1: Main course export configuration
 */

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


const PRIORITY_LEVELS = 10;
const MAX_ATTEMPTS = 10;
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('course_stylesheet', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);
$course_status = required_param('course_status', PARAM_TEXT);
$course = $DB->get_record_select('course', "id=$id");

$PAGE->set_url(PLUGINPATH.'export/step1.php', array('id' => $id));
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

echo $OUTPUT->header();

// Check specified server belongs to current user
$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
	echo $OUTPUT->footer();
	die();
}

$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', $server);
$priorities = [];
for ($i=0; $i<=PRIORITY_LEVELS; $i++){
	$priorities[$i] = array ("idx" => $i, "selected" => $i == $priority );
}

$sequencing = get_oppiaconfig($id, 'coursesequencing', '', $server);
$keep_html = get_oppiaconfig($id, 'keep_html', '', $server);
$thumb_height = get_oppiaconfig($id, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, $server);
$thumb_width = get_oppiaconfig($id, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, $server);
$section_height = get_oppiaconfig($id, 'section_height', $CFG->block_oppia_mobile_export_section_icon_height, $server);
$section_width = get_oppiaconfig($id, 'section_width', $CFG->block_oppia_mobile_export_section_icon_width, $server);

$base_settings = array(
	'priorities' 	=> $priorities,
	'tags' 			=> get_oppiaconfig($id,'coursetags','', $server),
	'default_lang' 	=> get_oppiaconfig($id,'default_lang', $CFG->block_oppia_mobile_export_default_lang, $server),
	'keep_html'		=> $keep_html,
	'thumb_height'	=> $thumb_height,
	'thumb_width'	=> $thumb_width,
	'section_height'=> $section_height,
	'section_width'	=> $section_width,
	'sequencing_none' 	 => $sequencing == '' || $sequencing == 'none',
	'sequencing_section' => $sequencing == 'section',
	'sequencing_course' => $sequencing == 'course',
);

echo $OUTPUT->render_from_template(
	PLUGINNAME.'/export_step1_form',
	array(
		'id' => $id,
		'stylesheet' => $stylesheet,
		'server' => $server,
		'course_export_status' => $course_status,
		'wwwroot' => $CFG->wwwroot,
		'base_settings' => $base_settings
	)
);

echo $OUTPUT->footer();

?>
