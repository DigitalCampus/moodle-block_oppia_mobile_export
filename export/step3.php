<?php 
require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'oppia_api_helper.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');
require_once($pluginroot . 'activity/processor.php');

require_once($CFG->libdir.'/componentlib.class.php');

// We get all the params from the previous step form
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$server = required_param('server_id', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);

$tags = get_oppiaconfig($id,'coursetags','', $server);
$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', $server);
$sequencing = get_oppiaconfig($id, 'coursesequencing', '', $server);
$keep_html = get_oppiaconfig($id, 'keep_html', '', $server);
$DEFAULT_LANG = get_oppiaconfig($id,'default_lang', $CFG->block_oppia_mobile_export_default_lang, $server);
$thumb_height = get_oppiaconfig($id, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, $server);
$thumb_width = get_oppiaconfig($id, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, $server);
$section_height = get_oppiaconfig($id, 'section_height', $CFG->block_oppia_mobile_export_section_icon_height, $server);
$section_width = get_oppiaconfig($id, 'section_width', $CFG->block_oppia_mobile_export_section_icon_width, $server);

$course = $DB->get_record('course', array('id'=>$id));
//we clean the shortname of the course (the change doesn't get saved in Moodle)
$course->shortname = cleanShortname($course->shortname);

$is_draft = ($course_export_status == 'draft');
if ($is_draft){
    $course->shortname = $course->shortname."-draft";
}

$PAGE->set_url(PLUGINPATH.'export/step3.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
	print_error('nocontext');
}

require_login($course);

$CFG->cachejs = false;

$PAGE->requires->jquery();
$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$PAGE->requires->js(PLUGINPATH.'publish/publish_media.js');

global $MOBILE_LANGS;
$MOBILE_LANGS = array();

global $MEDIA;
$MEDIA = array();

$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));
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
$apiHelper = new ApiHelper();
$apiHelper->fetchServerVersion($server_connection);

echo '<p>';
if ($apiHelper->version == null || $apiHelper->version==''){
	echo '<span class="export-error">'. get_string('export_server_error', PLUGINNAME).OPPIA_HTML_BR;
	add_publishing_log($server_connection->url, $USER->id, $id, "server_unavailable", "Unable to get server info");
}
else{
	echo get_string('export_server_version', PLUGINNAME, $apiHelper->version).OPPIA_HTML_BR;
}

//make course dir etc for output
deleteDir($pluginroot.OPPIA_OUTPUT_DIR.$USER->id."/temp");
deleteDir($pluginroot.OPPIA_OUTPUT_DIR.$USER->id);
if(!is_dir($pluginroot."output")){
	if (!mkdir($pluginroot."output",0777)){
		echo "<h3>Failed to create the output directory, please check your server permissions to allow the webserver user to create the output directory under " . __DIR__ . "</h3>";
		die;
	}
}
mkdir($pluginroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/",0777, true);
$course_root = $pluginroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/".strtolower($course->shortname);
mkdir($course_root,0777);
mkdir($course_root."/images",0777);
$fh = fopen($course_root."/images/.nomedia", 'w');
fclose($fh);
mkdir($course_root."/resources",0777);
$fh = fopen($course_root."/resources/.nomedia", 'w');
fclose($fh);

mkdir($course_root."/style_resources",0777);
mkdir($course_root."/js",0777);

$PAGE->set_context($context);
context_helper::preload_course($id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$plugin_version = get_config(PLUGINNAME, 'version');
$versionid = date("YmdHis");
$xmlDoc = new DOMDocument( "1.0", "UTF-8" );
$root = $xmlDoc->appendChild($xmlDoc->createElement("module"));
$meta = $root->appendChild($xmlDoc->createElement("meta"));
$meta->appendChild($xmlDoc->createElement("versionid", $versionid));
$meta->appendChild($xmlDoc->createElement("priority", $priority));

$meta->appendChild($xmlDoc->createElement("server", $server_connection->url));
$meta->appendChild($xmlDoc->createElement("sequencing", $sequencing));
$meta->appendChild($xmlDoc->createElement("tags", $tags));
$meta->appendChild($xmlDoc->createElement("exportversion", $plugin_version));

add_publishing_log($server_connection->url, $USER->id, $id, "export_start", "Export process starting");

$a = new stdClass();
$a->stepno = 3;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title', PLUGINNAME, $a)."</h2>";
$title = extractLangs($course->fullname);
if(is_array($title) && count($title)>0){
	foreach($title as $l=>$t){
		$temp = $xmlDoc->createElement("title");
		$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
		$meta->appendChild($temp);
	}
} else {
	$temp = $xmlDoc->createElement("title");
	$temp->appendChild($xmlDoc->createCDATASection(strip_tags($course->fullname)));
	$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
	$meta->appendChild($temp);
}
$temp = $xmlDoc->createElement("shortname");
$temp->appendChild($xmlDoc->createCDATASection(strtolower($course->shortname)));
$meta->appendChild($temp);

$summary = extractLangs($course->summary);
if(is_array($summary) && count($summary)>0){
	foreach($summary as $l=>$s){
		$temp = $xmlDoc->createElement("description");
		$temp->appendChild($xmlDoc->createCDATASection(trim(strip_tags($s))));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
		$meta->appendChild($temp);
	}
} else {
	$temp = $xmlDoc->createElement("description");
	$temp->appendChild($xmlDoc->createCDATASection(trim(strip_tags($course->summary))));
	$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
	$meta->appendChild($temp);
}

/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
$i = 1;
foreach ($sectionmods as $modnumber) {
		
	if (empty($modinfo->sections[0])) {
		continue;
	}
	$mod = $mods[$modnumber];
	
	if($mod->modname == 'page' && $mod->visible == 1){
		echo "<p>".$mod->name."</p>";
		$page = new MobileActivityPage();
		$page->courseroot = $course_root;
		$page->id = $mod->id;
		$page->section = 0;
		$page->process();
		$page->getXML($mod, $i, $meta, $xmlDoc, false);
	}
	if($mod->modname == 'quiz' && $mod->visible == 1){
		echo "<p>".$mod->name."</p>";

		$randomselect = get_oppiaconfig($id, 'randomselect', 0, $server);
		$passthreshold = get_oppiaconfig($id, 'passthreshold', 0, $server);
		$showfeedback = get_oppiaconfig($id, 'showfeedback', 2, $server);
		$maxattempts = get_oppiaconfig($id, 'maxattempts', 'unlimited', $server);

		$quiz = new MobileActivityQuiz(array(
	    	'id' => $mod->id,
	    	'courseroot' => $course_root,
			'section' => $sect_orderno,
			'server_id' => $server,
			'course_id' => $id,
			'shortname' => $course->shortname,
			'summary' => 'Pre-test',
			'courseversion' => $versionid,
			'keep_html' => $keep_html,
			'config_array' => array(
				'randomselect'=>$randomselect, 
				'showfeedback'=>$showfeedback, 
				'passthreshold'=>$passthreshold,
				'maxattempts'=>$maxattempts
			)
	    ));
		
		$quiz->courseroot = $course_root;
		$quiz->id = $mod->id;
		$quiz->section = 0;
		$quiz->preprocess();
		if ($quiz->get_is_valid()){
			$quiz->process();
			$quiz->getXML($mod, $i, $meta, $xmlDoc, true);
		}
	}
	if($mod->modname == 'feedback' && $mod->visible == 1){
	    echo $mod->name.OPPIA_HTML_BR;

		$feedback = new MobileActivityFeedback(array(
	    	'id' => $mod->id,
	    	'courseroot' => $course_root,
			'section' => $sect_orderno,
			'server_id' => $server,
			'course_id' => $id,
			'shortname' => $course->shortname,
			'courseversion' => $versionid,
			'keep_html' => $keep_html,
			'config_array' => array(
				'showfeedback'=>false, 
				'passthreshold'=>0,
				'maxattempts'=>'unlimited'
			)
	    ));
		
		$feedback->courseroot = $course_root;
		$feedback->id = $mod->id;
		$feedback->section = 0;
		$feedback->preprocess();
		if ($feedback->get_is_valid()){
			$feedback->process();
			$feedback->getXML($mod, $i, $meta, $xmlDoc, true);
		} else {
		    echo get_string('error_feedback_no_questions', PLUGINNAME).OPPIA_HTML_BR;
		}
	}
	$i++;
}

/*-----------------------------*/

// get module image (from course summary)
$filename = extractImageFile($course->summary,
							'course',
							'summary',
							'0',
							$context->id,
							$course_root,0);

if($filename){
	$resizedFilename = resizeImage($course_root."/".$filename,
	    $course_root."/images/".$course->id.'_'.$context->id,
						$CFG->block_oppia_mobile_export_course_icon_width,
						$CFG->block_oppia_mobile_export_course_icon_height,
						true);
	unlink($course_root."/".$filename) or die('Unable to delete the file');
	$temp = $xmlDoc->createElement("image");
	$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode("/images/".$resizedFilename));
	$meta->appendChild($temp);
}

$structure = $xmlDoc->createElement("structure");
$local_media_files = array();

echo "<h3>".get_string('export_sections_start', PLUGINNAME)."</h3>";

$processor = new ActivityProcessor(array(
			'course_root' => $course_root,
			'server_id' => $server,
			'course_id' => $id,
			'course_shortname' => $course->shortname,
			'versionid' => $versionid,
			'keep_html' => $keep_html,
			'local_media_files' => $local_media_files
));

$sect_orderno = 1;
foreach($sections as $sect) {
	flush_buffers();
	// We avoid the topic0 as is not a section as the rest
	if ($sect->section == 0) {
	    continue;
	}
	$sectionmods = explode(",", $sect->sequence);

	$defaultSectionTitle = false;
	$sectionTitle = strip_tags($sect->summary);
	$title = extractLangs($sect->summary);
	// If the course has no summary, we try to use the section name
	if ($sectionTitle == "") {
		$sectionTitle = strip_tags($sect->name);
		$title = extractLangs($sect->name);
	}
	// If the course has neither summary nor name, use the default topic title
	if ($sectionTitle == "") {
		$sectionTitle = get_string('sectionname', 'format_topics') . ' ' . $sect->section;
		$title = null;
		$defaultSectionTitle = true;
	}

	if(count($sectionmods)>0){
		echo '<hr>';
		echo '<div class="oppia_export_section">';
		echo "<h4>".get_string('export_section_title', PLUGINNAME, $sectionTitle)."</h4>";
		
		$section = $xmlDoc->createElement("section");
		$section->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($sect_orderno));
		if(!$defaultSectionTitle && is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement("title");
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$section->appendChild($temp);
				
			}
		} else {
			$temp = $xmlDoc->createElement("title");
			$temp->appendChild($xmlDoc->createCDATASection($sectionTitle));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$section->appendChild($temp);
		}

		$sect_password =  optional_param('section_'.$sect->id.'_password', '', PARAM_TEXT);
		if ($sect_password != ''){
			echo '<span class="export-results warning">'. get_string('section_password_added', PLUGINNAME) .'</span>'.OPPIA_HTML_BR;
			$section->appendChild($xmlDoc->createAttribute("password"))->appendChild($xmlDoc->createTextNode($sect_password));
			// We store the section's password for future exports 
			add_or_update_oppiaconfig($sect->id, 'password', $sect_password, $server);
		}

		// get section image (from summary)
		$filename = extractImageFile($sect->summary,
									'course',
									'section',
									$sect->id,
									$context->id,
									$course_root, 0);

		if($filename){
			$resizedFilename = resizeImage($course_root."/".$filename,
			    $course_root."/images/".$sect->id.'_'.$context->id,
								$section_width, $section_height, true);
			unlink($course_root."/".$filename) or die('Unable to delete the file');
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode("/images/".$resizedFilename));
			$section->appendChild($temp);
		}

		$act_orderno = 1;
		$activities = $xmlDoc->createElement("activities");
		$processor->set_current_section($sect_orderno);

		foreach ($sectionmods as $modnumber) {
			
			if ($modnumber == "" || $modnumber === false){
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->visible != 1){
				continue;
			}
			
			echo '<div class="step"><strong>' . $mod->name . '</strong>'.OPPIA_HTML_BR;
			$activity = $processor->process_activity($mod, $sect, $act_orderno, $activities, $xmlDoc);
			if ($activity != null){
				$act_orderno++;
			}
			echo '</div>';

			flush_buffers();
		}

		if ($act_orderno > 1){
			$section->appendChild($activities);
			$structure->appendChild($section);
			$sect_orderno++;
		} else {
		    echo get_string('error_section_no_activities', PLUGINNAME).OPPIA_HTML_BR;
		}

		echo '</div>';
		flush_buffers();
	}
}
echo '<hr><br>';
echo get_string('export_sections_finish', PLUGINNAME).OPPIA_HTML_BR;
$root->appendChild($structure);

// add in the langs available here
$langs = $xmlDoc->createElement("langs");
foreach($MOBILE_LANGS as $k=>$v){
	$temp = $xmlDoc->createElement("lang",$k);
	$langs->appendChild($temp);
}
if(count($MOBILE_LANGS) == 0){
	$temp = $xmlDoc->createElement("lang",$DEFAULT_LANG);
	$langs->appendChild($temp);
}
$meta->appendChild($langs);
$local_media_files = $processor->local_media_files;

// add media includes
if(count($MEDIA) > 0 || count($local_media_files) > 0){
	$media = $xmlDoc->createElement("media");
	foreach ($MEDIA as $m){
		$temp = $xmlDoc->createElement("file");
		foreach($m as $var => $value) {
			$temp->appendChild($xmlDoc->createAttribute($var))->appendChild($xmlDoc->createTextNode($value));
		}
		$media->appendChild($temp);
	}
	foreach ($local_media_files as $m){
		$temp = $xmlDoc->createElement("file");
		foreach($m as $var => $value) {
			$temp->appendChild($xmlDoc->createAttribute($var))->appendChild($xmlDoc->createTextNode($value));
		}
		$media->appendChild($temp);
	}

	$root->appendChild($media);
}
$xmlDoc->preserveWhiteSpace = false;
$xmlDoc->formatOutput = true;
$xmlDoc->save($course_root.OPPIA_MODULE_XML);


if ($sect_orderno <= 1){
	echo '<h3>'.get_string('error_exporting', PLUGINNAME).'</h3>';
	echo '<p>'.get_string('error_exporting_no_sections', PLUGINNAME).'</p>';
	echo $OUTPUT->footer();
	die();
}

echo $OUTPUT->render_from_template(
	PLUGINNAME.'/export_step3_form', 
	array(
		'id' => $id,
		'server_connection' =>$server_connection->url,
		'media_files' => $local_media_files,
		'server_id' => $server,
		'stylesheet' => $stylesheet,
		'coursetags' => $tags,
		'course_export_status' => $course_export_status,
		'course_root' => $course_root,
		'wwwroot' => $CFG->wwwroot));

echo $OUTPUT->footer();


?>
