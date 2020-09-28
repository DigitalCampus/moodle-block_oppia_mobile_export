<?php 
require_once(dirname(__FILE__) . '/../../../config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginpath = '/blocks/oppia_mobile_export/';
$pluginroot = $CFG->dirroot . $pluginpath;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'oppia_api_helper.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');


$id = required_param('id',PARAM_INT);
$stylesheet = required_param('stylesheet',PARAM_TEXT);
$tags = required_param('coursetags',PARAM_TEXT);
$server = required_param('server',PARAM_TEXT);
$course_status = required_param('course_status', PARAM_TEXT);
$course_root = required_param('course_root', PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url('/blocks/oppia_mobile_export/export/step3.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
	print_error('nocontext');
}
$PAGE->set_context($context);
context_helper::preload_course($id);
require_login($course);


$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$a = new stdClass();
$a->stepno = 3;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title','block_oppia_mobile_export', $a)."</h2>";

$server_connection = $DB->get_record('block_oppia_mobile_server', array('moodleuserid'=>$USER->id,'id'=>$server));

$versionid = date("YmdHis");

echo get_string('export_xml_valid_start','block_oppia_mobile_export');

if (!file_exists($course_root."/module.xml")){
	echo "<p>".get_string('error_xml_notfound','block_oppia_mobile_export')."</p>";
	echo $OUTPUT->footer();
	die();
}


libxml_use_internal_errors(true);
$xml = new DOMDocument();
$xml->load($course_root."/module.xml");

// We update the local media URLs from the results of the previous step
foreach ($xml->getElementsByTagName('file') as $mediafile) {

	if ($mediafile->hasAttribute('download_url')){
		// If it already has the url set, we don't need to do anything
		continue;
	}

	if ($mediafile->hasAttribute('moodlefile')){
		// We remove the moodlefile attribute (it's only a helper to publish media)
		$mediafile->removeAttribute('moodlefile');
	}


	$digest = $mediafile->getAttribute('digest');
	$url = optional_param($digest, null, PARAM_TEXT);
	if ($url !== null){
		$mediafile->setAttribute('download_url', $url);
	}
}

if (!$xml->schemaValidate($pluginroot.'oppia-schema.xsd')) {
	print '<p><b>'.get_string('error_xml_invalid','block_oppia_mobile_export').'</b></p>';
	libxml_display_errors();
	add_publishing_log($server_connection->url, $USER->id, $id, "error_xml_invalid", "Invalid course XML");
} else {
	echo get_string('export_xml_validated','block_oppia_mobile_export') . '<br/>';
	echo get_string('export_course_xml_created','block_oppia_mobile_export') . '<br/>';
	
	$xml->preserveWhiteSpace = false;
	$xml->formatOutput = true;
	$xml->save($course_root."/module.xml");

	echo get_string('export_style_start','block_oppia_mobile_export') . '<br/>';
	
	if (!copy($pluginroot."styles/".$stylesheet, $course_root."/style.css")) {
		echo "<p>".get_string('error_style_copy','block_oppia_mobile_export')."</p>";
	}
	
	echo get_string('export_style_resources','block_oppia_mobile_export') . '<br/>';
	list($filename, $extn) = explode('.', $stylesheet);
	recurse_copy($pluginroot."styles/".$filename."-style-resources/", $course_root."/style_resources/");
	
	recurse_copy($pluginroot."js/", $course_root."/js/");
	
	echo get_string('export_export_complete','block_oppia_mobile_export') . '<br/>';
	$dir2zip = $pluginroot."output/".$USER->id."/temp";

	$ziprelativepath = "output/".$USER->id."/".strtolower($course->shortname)."-".$versionid.".zip";
	$outputzip = $pluginroot.$ziprelativepath;
	Zip($dir2zip, $outputzip);

	$outputpath =  $CFG->wwwroot.$pluginpath.$ziprelativepath;
	

	echo get_string('export_export_compressed','block_oppia_mobile_export') . '<br/>';
	deleteDir($pluginroot."output/".$USER->id."/temp");
	
	$a = new stdClass();
	$a->zip = $outputpath;
	$a->coursename = strip_tags($course->fullname);
	
	$form_values = array(
		'server_connection' =>$server_connection->url,
		'wwwroot' => $CFG->wwwroot,
		'server' => $server,
		'sesskey' => sesskey(),
		'course_id' => $COURSE->id,
		'file' => $outputpath,
		'is_draft' => $course_status == 'draft',
		'tags' => $tags,
		'course_status' => $course_status );

	echo $OUTPUT->render_from_template('block_oppia_mobile_export/publish_form', $form_values);

	echo '<div class="export-results warning" style="display:block;padding:20px">';
	echo get_string('export_download_intro','block_oppia_mobile_export');
	echo "<br/>";
	echo get_string('export_download','block_oppia_mobile_export', $a );
	echo "</div>";
	
	// link to cleanup files
	echo "<p><a href='cleanup.php?id=".$id."'>".get_string('export_cleanup','block_oppia_mobile_export')."</a></p>";
	
	add_publishing_log($server_connection->url, $USER->id, $id,  "export_file_created", strtolower($course->shortname)."-".$versionid.".zip");
	add_publishing_log($server_connection->url, $USER->id, $id,  "export_end", "Export process completed");
}

echo $OUTPUT->footer();


?>
