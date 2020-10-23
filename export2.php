<?php 
require_once(dirname(__FILE__) . '/../../config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/lib.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/langfilter.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/oppia_api_helper.php');

require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/activity.class.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/page.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/quiz.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/resource.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/feedback.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');

$id = required_param('id',PARAM_INT);
$stylesheet = required_param('stylesheet',PARAM_TEXT);
$priority = required_param('coursepriority',PARAM_INT);
$sequencing = required_param('coursesequencing', PARAM_TEXT);
$DEFAULT_LANG = required_param('default_lang', PARAM_TEXT);
$tags = required_param('coursetags',PARAM_TEXT);
$tags = cleanTagList($tags);
$server = required_param('server',PARAM_TEXT);
$course_status = required_param('course_status', PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));
//we clean the shortname of the course (the change doesn't get saved in Moodle)
$course->shortname = cleanShortname($course->shortname);

if ($course_status == 'draft'){
    $course->shortname = $course->shortname."-draft";
}

$PAGE->set_url('/blocks/oppia_mobile_export/export2.php', array('id' => $id));
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
echo $OUTPUT->header();

global $QUIZ_CACHE;
$QUIZ_CACHE = array();

global $MOBILE_LANGS;
$MOBILE_LANGS = array();

global $MEDIA;
$MEDIA = array();

$advice = array();

$QUIZ_EXPORT_MINVERSION_MINOR = 9;
$QUIZ_EXPORT_MINVERSION_SUB = 8;
$QUIZ_EXPORT_METHOD = 'server';

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

//make course dir etc for output
deleteDir("output/".$USER->id."/temp");
deleteDir("output/".$USER->id);
if(!is_dir("output")){
	if (!mkdir("output",0777)){
		echo "<h3>Failed to create the output directory, please check your server permissions to allow the webserver user to create the output directory under " . __DIR__ . "</h3>";
		die;
	}
}
mkdir("output/".$USER->id."/temp/",0777, true);
$course_root = "output/".$USER->id."/temp/".strtolower($course->shortname);
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

$plugin_version = get_config('block_oppia_mobile_export', 'version');
$versionid = date("YmdHis");
$xmlDoc = new DOMDocument( "1.0", "UTF-8" );
$root = $xmlDoc->appendChild($xmlDoc->createElement("module"));
$meta = $root->appendChild($xmlDoc->createElement("meta"));
$meta->appendChild($xmlDoc->createElement("versionid",$versionid));
$meta->appendChild($xmlDoc->createElement("priority",$priority));

$meta->appendChild($xmlDoc->createElement("server",$server_connection->url));
$meta->appendChild($xmlDoc->createElement("sequencing", $sequencing));
$meta->appendChild($xmlDoc->createElement("tags",$tags));
$meta->appendChild($xmlDoc->createElement("exportversion", $plugin_version));

add_or_update_oppiaconfig($id, 'coursepriority', $priority, $server);
add_or_update_oppiaconfig($id, 'coursetags', $tags, $server);
add_or_update_oppiaconfig($id, 'coursesequencing', $sequencing, $server);
add_or_update_oppiaconfig($id, 'default_lang', $DEFAULT_LANG, $server);

add_publishing_log($server_connection->url, $USER->id, $id, "export_start", "Export process starting");

$a = new stdClass();
$a->stepno = 2;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title','block_oppia_mobile_export', $a)."</h2>";
$title = extractLangs($course->fullname);
if(is_array($title) && count($title)>0){
	foreach($title as $l=>$t){
		$temp = $xmlDoc->createElement("title");
		$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
		$meta->appendChild($temp);
	}
} else {;
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
} else {;
	$temp = $xmlDoc->createElement("description");
	$temp->appendChild($xmlDoc->createCDATASection(trim(strip_tags($course->summary))));
	$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
	$meta->appendChild($temp);
}

$apiHelper = new QuizHelper();
$apiHelper->init($server_connection);
$server_info = $apiHelper->exec('server', array(),'get', false, false);
echo '<p>';
if ($server_info && $server_info->version ){
	echo '<strong>Current server version:</strong> '.$server_info->version.'<br/>';
	$v_regex = '/^v([0-9])+\.([0-9]+)\.([0-9]+)$/';
	preg_match($v_regex, $server_info->version, $version_nums);
	if (count($version_nums)>0 && (
		( (int) $version_nums[1] >= 0) || //major version check (>0.x.x)
		( (int) $version_nums[2] >= $QUIZ_EXPORT_MINVERSION_MINOR) || //minor version check (>=0.9.x)
		( (int) $version_nums[3] >= $QUIZ_EXPORT_MINVERSION_SUB) //sub version check (>=0.9.8)
	)){
		$QUIZ_EXPORT_METHOD = 'local';
	}
}
else{
	echo '<span style="color:red;">Unable to get server info (is it correctly configured and running?)</span><br/>';
	add_publishing_log($server_connection->url, $USER->id, $id, "server_unavailable", "Unable to get server info");
	
}
echo '<strong>Quiz export method:</strong> '.$QUIZ_EXPORT_METHOD.'</p>';

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
		$page = new mobile_activity_page();
		$page->courseroot = $course_root;
		$page->id = $mod->id;
		$page->section = 0;
		$page->process();
		$page->getXML($mod,$i,false,$meta,$xmlDoc);
	}
	if($mod->modname == 'quiz' && $mod->visible == 1){
		echo "<p>".$mod->name."</p>";

		$quiz = new mobile_activity_quiz();
		
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
			$quiz->getXML($mod,$i,true,$meta,$xmlDoc);
		}
	}
	if($mod->modname == 'feedback' && $mod->visible == 1){
		echo $mod->name."<br/>";
		$feedback = new mobile_activity_feedback();
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
			$feedback->getXML($mod,$i,true,$meta,$xmlDoc);
		} else {
			echo get_string('error_feedback_no_questions','block_oppia_mobile_export')."<br/>";
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
		echo "<h3>".get_string('export_section_title','block_oppia_mobile_export', $sectionTitle)."</h3>";
		
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
			
			echo("<pre>");
			// print_r($mod);
			echo("</pre>");
			
			if($mod->modname == 'page' && $mod->visible == 1){
				echo $mod->name."<br/>";
				$page = new mobile_activity_page();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $sect_orderno;
				$page->process();
				$page->getXML($mod,$act_orderno,true,$activities,$xmlDoc);
				$act_orderno++;
			}
			
			if($mod->modname == 'quiz' && $mod->visible == 1){
				echo $mod->name."<br/>";
				
				$quiz = new mobile_activity_quiz();
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
					$quiz->getXML($mod,$act_orderno,true,$activities,$xmlDoc);
					$act_orderno++;
				} else {
					echo get_string('error_quiz_no_questions','block_oppia_mobile_export')."<br/>";
				}
			}
			
			if($mod->modname == 'resource' && $mod->visible == 1){
				echo $mod->name."<br/>";
				$resource = new mobile_activity_resource();
				$resource->courseroot = $course_root;
				$resource->id = $mod->id;
				$resource->section = $sect_orderno;
				$resource->process();
				$resource->getXML($mod,$act_orderno,true,$activities,$xmlDoc);
				$act_orderno++;
			}
			
			if($mod->modname == 'url' && $mod->visible == 1){
				echo $mod->name."<br/>";
				$url = new mobile_activity_url();
				$url->courseroot = $course_root;
				$url->id = $mod->id;
				$url->section = $sect_orderno;
				$url->process();
				$url->getXML($mod,$act_orderno,true,$activities,$xmlDoc);
				$act_orderno++;
			}
			
			if($mod->modname == 'feedback' && $mod->visible == 1){
				echo $mod->name."<br/>";
				$feedback = new mobile_activity_feedback();
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
					$feedback->getXML($mod,$act_orderno,true,$activities,$xmlDoc);
					$act_orderno++;
				} else {
					echo get_string('error_feedback_no_questions','block_oppia_mobile_export')."<br/>";
				}
			}
			
			flush_buffers();
		}
		if ($act_orderno>1){
			$section->appendChild($activities);
			$structure->appendChild($section);
			$sect_orderno++;
		} else {
			echo get_string('error_section_no_activities','block_oppia_mobile_export')."<br/>";
		}
		flush_buffers();
	}
}
echo "Finished exporting activities and sections";
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
if(count($MEDIA) > 0){
	$media = $xmlDoc->createElement("media");
	foreach ($MEDIA as $m){
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
$xmlDoc->save($course_root."/module.xml");

echo "<p>".get_string('export_xml_valid_start','block_oppia_mobile_export');
libxml_use_internal_errors(true);

if ($sect_orderno <= 1){
	echo '<h3>'.get_string('error_exporting','block_oppia_mobile_export').'</h3>';
	echo '<p>'.get_string('error_exporting_no_sections','block_oppia_mobile_export').'</p>';
	echo $OUTPUT->footer();
	die();
}

$xml = new DOMDocument();
$xml->load($course_root."/module.xml");

if (!$xml->schemaValidate('./oppia-schema.xsd')) {
	print '<p><b>'.get_string('error_xml_invalid','block_oppia_mobile_export').'</b></p>';
	libxml_display_errors();
	add_publishing_log($server_connection->url, $USER->id, $id, "error_xml_invalid", "Invalid course XML");
} else {
	echo get_string('export_xml_validated','block_oppia_mobile_export')."<p/>";
	
	echo "<p>".get_string('export_course_xml_created','block_oppia_mobile_export')."</p>";
	
	echo "<p>".get_string('export_style_start','block_oppia_mobile_export')."</p>";
	
	if (!copy("styles/".$stylesheet, $course_root."/style.css")) {
		echo "<p>".get_string('error_style_copy','block_oppia_mobile_export')."</p>";
	}
	
	echo "<p>".get_string('export_style_resources','block_oppia_mobile_export')."</p>";
	list($filename, $extn) = explode('.', $stylesheet);
	recurse_copy("styles/".$filename."-style-resources/", $course_root."/style_resources/");
	
	recurse_copy("js/", $course_root."/js/");
	
	echo "<p>".get_string('export_export_complete','block_oppia_mobile_export')."</p>";
	$dir2zip = "output/".$USER->id."/temp";
	$outputzip = "output/".$USER->id."/".strtolower($course->shortname)."-".$versionid.".zip";
	Zip($dir2zip,$outputzip);
	echo "<p>".get_string('export_export_compressed','block_oppia_mobile_export')."</p>";
	deleteDir("output/".$USER->id."/temp");
	
	$a = new stdClass();
	$a->zip = $outputzip;
	$a->coursename = strip_tags($course->fullname);
	
	// form to publish to OppiaMobile server
	echo "<form style='display:block; border: 1px solid #000; padding:20px; margin:20px 0;' action='".$CFG->wwwroot."/blocks/oppia_mobile_export/publish_course.php' method='POST'>";
	echo "<input type='hidden' name='id' value='".$COURSE->id."'>";
	echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";
	echo "<input type='hidden' name='server' value='".$server."'>";
	echo "<input type='hidden' name='file' value='".$a->zip."'>";
	
	if ($course_status == 'draft'){
    	echo "<h2>".get_string('publish_heading_draft','block_oppia_mobile_export')."</h2>";
    	echo "<p>".get_string('publish_text_draft','block_oppia_mobile_export',$server_connection->url)."</p>";
	} else {
	    echo "<h2>".get_string('publish_heading','block_oppia_mobile_export')."</h2>";
	    echo "<p>".get_string('publish_text','block_oppia_mobile_export',$server_connection->url)."</p>";
	}
	
	echo "<p>".get_string('publish_field_username','block_oppia_mobile_export')."<br/>";
	echo "<input type='text' name='username' value=''></p>";
	echo "<p>".get_string('publish_field_password','block_oppia_mobile_export')."<br/>";
	echo "<input type='password' name='password' value=''></p>";
	
	echo "<p>".get_string('publish_field_tags','block_oppia_mobile_export')."<br/>";
	echo "<input type='text' name='tags' value='".$tags."' size='100'></p>";
	
	echo "<input type='hidden' name='course_status' value='".$course_status."'>";
	
	echo "<p><input type='submit' name='submit' value='Publish'></p>";
	echo "</form>";
	
	echo "<div style='display:block; border: 1px solid #000; padding:20px'>";
	echo get_string('export_download_intro','block_oppia_mobile_export');
	echo "<br/>";
	echo get_string('export_download','block_oppia_mobile_export', $a );
	echo "</div>";
	
	// link to cleanup files
	echo "<p><a href='cleanup.php?id=".$id."'>".get_string('export_cleanup','block_oppia_mobile_export')."</a></p>";
	
	if(count($advice)> 0){
		echo "<p>".get_string('export_advice_desc','block_oppia_mobile_export')."</p><ol>";
		foreach($advice as $a){
			echo "<li>".$a."</li>";
		}
	
		echo "</ol>";
	}
	
	add_publishing_log($server_connection->url, $USER->id, $id,  "export_file_created", strtolower($course->shortname)."-".$versionid.".zip");
	add_publishing_log($server_connection->url, $USER->id, $id,  "export_end", "Export process completed");
}

echo $OUTPUT->footer();

?>
