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

require_once($CFG->libdir.'/componentlib.class.php');


$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$priority = required_param('coursepriority', PARAM_INT);
$sequencing = required_param('coursesequencing', PARAM_TEXT);
$DEFAULT_LANG = required_param('default_lang', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);
$tags = required_param('coursetags', PARAM_TEXT);
$tags = cleanTagList($tags);

$course = $DB->get_record('course', array('id'=>$id));
//we clean the shortname of the course (the change doesn't get saved in Moodle)
$course->shortname = cleanShortname($course->shortname);

$is_draft = ($course_export_status == 'draft');
if ($is_draft){
    $course->shortname = $course->shortname."-draft";
}

$PAGE->set_url(PLUGINPATH.'export/step2.php', array('id' => $id));
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

global $QUIZ_CACHE;
$QUIZ_CACHE = array();

global $MOBILE_LANGS;
$MOBILE_LANGS = array();

global $MEDIA;
$MEDIA = array();

$advice = array();

$QUIZ_EXPORT_METHOD = 'server';

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

add_or_update_oppiaconfig($id, 'coursepriority', $priority, $server);
add_or_update_oppiaconfig($id, 'coursetags', $tags, $server);
add_or_update_oppiaconfig($id, 'coursesequencing', $sequencing, $server);
add_or_update_oppiaconfig($id, 'default_lang', $DEFAULT_LANG, $server);

add_publishing_log($server_connection->url, $USER->id, $id, "export_start", "Export process starting");

$a = new stdClass();
$a->stepno = 2;
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

$meta->appendChild($xmlDoc->createElement("shortname",strtolower($course->shortname)));

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

$apiHelper = new ApiHelper();
$apiHelper->fetchServerVersion($server_connection);
$method = $apiHelper->getExportMethod();

echo '<p>';
if ($method == false){
	echo '<span class="export-error">'. get_string('export_server_error', PLUGINNAME).OPPIA_HTML_BR;
	add_publishing_log($server_connection->url, $USER->id, $id, "server_unavailable", "Unable to get server info");
}
else{
	$QUIZ_EXPORT_METHOD = $method;

	echo get_string('export_server_version', PLUGINNAME, $apiHelper->version).OPPIA_HTML_BR;
}
echo '<strong>'.get_string('export_method', PLUGINNAME).':</strong> '.$QUIZ_EXPORT_METHOD.'</p>';

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

		$quiz = new MobileActivityQuiz();
		
		$random = optional_param('quiz_'.$mod->id.'_randomselect',0,PARAM_INT);
		add_or_update_oppiaconfig($mod->id, 'randomselect', $random);
		
		$showfeedback = optional_param('quiz_'.$mod->id.'_showfeedback',2,PARAM_INT);
		add_or_update_oppiaconfig($mod->id, 'showfeedback', $showfeedback);
		
		$passthreshold = optional_param('quiz_'.$mod->id.'_passthreshold',0,PARAM_INT);
		add_or_update_oppiaconfig($mod->id, 'passthreshold', $passthreshold);

		$maxattempts = optional_param('quiz_'.$mod->id.'_maxattempts','unlimited',PARAM_INT);
		add_or_update_oppiaconfig($mod->id, 'maxattempts', $maxattempts);
		
		$configArray = Array('randomselect'=>$random, 
								'showfeedback'=>$showfeedback,
								'passthreshold'=>$passthreshold,
								'maxattempts'=>$maxattempts);
		$quiz->init($course->shortname,"Pre-test",$configArray,$versionid);
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
		$feedback = new MobileActivityFeedback();
		$configArray = Array(
		    'showfeedback'=>false,
		    'passthreshold'=>0,
		    'maxattempts'=>0);
		
		$feedback->init($server_connection, $course->shortname,$mod->name,$versionid, $configArray);
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
$index = Array();
$structure = $xmlDoc->createElement("structure");
$local_media_files = array();

echo "<h3>".get_string('export_sections_start', PLUGINNAME)."</h3>";

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
	// If the course has no summary, we try to use the section name
	if ($sectionTitle == "") {
		$sectionTitle = strip_tags($sect->name);
	}
	// If the course has neither summary nor name, use the default topic title
	if ($sectionTitle == "") {
		$sectionTitle = get_string('sectionname', 'format_topics') . ' ' . $sect->section;
		$defaultSectionTitle = true;
	}

	if(count($sectionmods)>0){
		echo '<hr>';
		echo '<div class="oppia_export_section">';
		echo "<h4>".get_string('export_section_title', PLUGINNAME, $sectionTitle)."</h4>";
		
		$section = $xmlDoc->createElement("section");
		$section->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($sect_orderno));
		$title = extractLangs($sect->summary);
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
		$act_orderno = 1;
		$activities = $xmlDoc->createElement("activities");
		foreach ($sectionmods as $modnumber) {
			
			if ($modnumber == "" || $modnumber === false){
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->visible != 1){
				continue;
			}
			
			echo '<div class="step"><strong>' . $mod->name . '</strong>'.OPPIA_HTML_BR;

			if($mod->modname == 'page'){
			    $page = new MobileActivityPage();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $sect_orderno;
				$page->process();
				$page->getXML($mod, $act_orderno, $activities, $xmlDoc, true);
				$local_media_files = array_merge($local_media_files, $page->getLocalMedia());

				$act_orderno++;
			}
			else if($mod->modname == 'quiz'){
			    $quiz = new MobileActivityQuiz();
				$random = optional_param('quiz_'.$mod->id.'_randomselect',0,PARAM_INT);
				add_or_update_oppiaconfig($mod->id, 'randomselect', $random);
				
				$showfeedback = optional_param('quiz_'.$mod->id.'_showfeedback',1,PARAM_INT);
				add_or_update_oppiaconfig($mod->id, 'showfeedback', $showfeedback);
				
				$passthreshold = optional_param('quiz_'.$mod->id.'_passthreshold',0,PARAM_INT);
				add_or_update_oppiaconfig($mod->id, 'passthreshold', $passthreshold);

				$maxattempts = optional_param('quiz_'.$mod->id.'_maxattempts','unlimited',PARAM_INT);
				add_or_update_oppiaconfig($mod->id, 'maxattempts', $maxattempts);
				
				$configArray = Array('randomselect'=>$random, 
									'showfeedback'=>$showfeedback, 
									'passthreshold'=>$passthreshold,
									'maxattempts'=>$maxattempts);
				
				$quiz->init($course->shortname,$sect->summary,$configArray,$versionid);
				$quiz->courseroot = $course_root;
				$quiz->id = $mod->id;
				$quiz->section = $sect_orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid()){
					$quiz->process();
					$quiz->getXML($mod, $act_orderno, $activities, $xmlDoc, true);
					$act_orderno++;
				} else {
				    echo get_string('error_quiz_no_questions', PLUGINNAME).OPPIA_HTML_BR;
				}
			}
			else if($mod->modname == 'resource'){
			    $resource = new MobileActivityResource();
				$resource->courseroot = $course_root;
				$resource->id = $mod->id;
				$resource->section = $sect_orderno;
				$resource->process();
				$resource->getXML($mod, $act_orderno, $activities, $xmlDoc, true);
				$act_orderno++;
			}
			else if($mod->modname == 'url'){
			    $url = new MobileActivityUrl();
				$url->courseroot = $course_root;
				$url->id = $mod->id;
				$url->section = $sect_orderno;
				$url->process();
				$url->getXML($mod, $act_orderno, $activities, $xmlDoc, true);
				$act_orderno++;
			}
			else if($mod->modname == 'feedback'){
			    $feedback = new MobileActivityFeedback();
				$configArray = Array(
				    'showfeedback'=>false,
				    'passthreshold'=>0,
				    'maxattempts'=>0);
				$feedback->init($course->shortname,$sect->summary,$versionid, $configArray);
				$feedback->courseroot = $course_root;
				$feedback->id = $mod->id;
				$feedback->section = $sect_orderno;
				$feedback->preprocess();
				if ($feedback->get_is_valid()){
					$feedback->process();
					$feedback->getXML($mod, $act_orderno, $activities, $xmlDoc, true);
					$act_orderno++;
				} else {
				    echo get_string('error_feedback_no_questions', PLUGINNAME).OPPIA_HTML_BR;
				}
			}
			else {
				echo get_string('error_not_supported', PLUGINNAME);
			}
			echo '</div>';

			flush_buffers();
		}

		if ($act_orderno>1){
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
	PLUGINNAME.'/export_step2_form', 
	array(
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
