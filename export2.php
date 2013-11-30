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
$stylesheet = required_param('stylesheet',PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url('/blocks/oppia_mobile_export/export.php', array('id' => $id));
preload_course_contexts($id);
if (!$context = get_context_instance(CONTEXT_COURSE, $course->id)) {
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

$DEFAULT_LANG = "en";

global $MOBILE_LANGS;
$MOBILE_LANGS = array();

global $MEDIA;
$MEDIA = array();

$advice = array();

//make course dir etc for output

deleteDir("output/".$USER->id."/temp");
deleteDir("output/".$USER->id);
if(!is_dir("output")){
	mkdir("output",0777);
}
mkdir("output/".$USER->id,0777);
mkdir("output/".$USER->id."/temp/",0777);
$course_root = "output/".$USER->id."/temp/".strtolower($course->shortname);
mkdir($course_root,0777);
mkdir($course_root."/images",0777);
mkdir($course_root."/resources",0777);

$context = get_context_instance(CONTEXT_COURSE, $course->id);
$PAGE->set_context($context);
preload_course_contexts($course->id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$versionid = date("YmdHis");
$xmlDoc = new DOMDocument();
$root = $xmlDoc->appendChild($xmlDoc->createElement("module"));
$meta = $root->appendChild($xmlDoc->createElement("meta"));
$meta->appendChild($xmlDoc->createElement("versionid",$versionid));

echo "<pre>";

echo "Exporting Course: ".strip_tags($course->fullname)."\n";
$title = extractLangs($course->fullname);
if(is_array($title) && count($title)>0){
	foreach($title as $l=>$t){
		$temp = $xmlDoc->createElement("title");
		$temp->appendChild($xmlDoc->createTextNode(strip_tags($t)));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
		$meta->appendChild($temp);
	}
} else {;
	$temp = $xmlDoc->createElement("title");
	$temp->appendChild($xmlDoc->createTextNode(strip_tags($course->fullname)));
	$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
	$meta->appendChild($temp);
}

$meta->appendChild($xmlDoc->createElement("shortname",strtolower($course->shortname)));

/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
$i = 1;
foreach ($sectionmods as $modnumber) {
		
	if (empty($modinfo->sections[0])) {
		continue;
	}
	$mod = $mods[$modnumber];
		
	if($mod->modname == 'page'){
		print_r($mod->name);
		echo "\n";
		$page = new mobile_activity_page();
		$page->courseroot = $course_root;
		$page->id = $mod->id;
		$page->section = 0;
		$page->process();
		$page->getXML($mod,$i,false,$meta,$xmlDoc);
	}
	if($mod->modname == 'quiz'){
		echo "\tExporting quiz: ".$mod->name."\n";

		$quiz = new mobile_activity_quiz();
		$random = optional_param('quiz_'.$mod->id,0,PARAM_INT);
		add_or_update_oppiaconfig($mod->id, 'randomselect', $random);
		$quiz->init($course->shortname,"Pre-test",$random);
		$quiz->courseroot = $course_root;
		$quiz->id = $mod->id;
		$quiz->section = 0;
		$quiz->preprocess();
		if ($quiz->get_is_valid()){
			$quiz->process();
			$quiz->getXML($mod,$i,true,$meta,$xmlDoc);
		}
	}
	$i++;
}

/*-----------------------------*/

// get module image (from course summary)
$filename = extractImageFile($course->summary,
								$context->id,
								'course/summary',
								'0',
								$course_root );
if($filename){
	resizeImage($course_root."/".$filename,
				$course_root."/images/".$context->id,
						$CFG->block_oppia_mobile_export_course_icon_width,
						$CFG->block_oppia_mobile_export_course_icon_height,
						true);
	unlink($course_root."/".$filename) or die('Unable to delete the file');
	$temp = $xmlDoc->createElement("image");
	$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode("/images/".$context->id));
	$meta->appendChild($temp);
}
$index = Array();

$structure = $xmlDoc->createElement("structure");
$orderno = 1;
foreach($sections as $sect) {
	flush_buffers();
	$sectionmods = explode(",", $sect->sequence);
	if($sect->summary && count($sectionmods)>0){
		
		echo "\nExporting Section: ".strip_tags($sect->summary,'<span>')."\n";
		
		$section = $xmlDoc->createElement("section");
		$section->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($orderno));
		$title = extractLangs($sect->summary);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement("title",strip_tags($t));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$section->appendChild($temp);;
				
			}
		} else {
			$temp = $xmlDoc->createElement("title",strip_tags($sect->summary));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$section->appendChild($temp);
		}
		// get image for this section
		$filename = extractImageFile($thissection->summary, $context->id, 'course/section', $sect->id, $course_root);
		
		if($filename){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($filename));
			$section->appendChild($temp);
		}
		
		
		$i=1;
		$no_activities = 0;
		$activities = $xmlDoc->createElement("activities");
		foreach ($sectionmods as $modnumber) {
			
			if (empty($modinfo->sections[$orderno])) {																																																									
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->modname == 'page'){
				echo "\tExporting page: ".$mod->name."\n";
				
				$page = new mobile_activity_page();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $orderno;
				$page->process();
				$page->getXML($mod,$i,true,$activities,$xmlDoc);
				$no_activities++;
			}
			
			if($mod->modname == 'quiz'){
				echo "\tExporting quiz: ".$mod->name."\n";
				
				$quiz = new mobile_activity_quiz();
				$random = optional_param('quiz_'.$mod->id,0,PARAM_INT);
				add_or_update_oppiaconfig($mod->id, 'randomselect', $random);
				$quiz->init($course->shortname,$sect->summary,$random);
				$quiz->courseroot = $course_root;
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid()){
					$quiz->process();
					$quiz->getXML($mod,$i,true,$activities,$xmlDoc);
					$no_activities++;
				}
			}
			
			if($mod->modname == 'resource'){
				echo "\tExporting resource: ".$mod->name."\n";
				$resource = new mobile_activity_resource();
				$resource->courseroot = $course_root;
				$resource->id = $mod->id;
				$resource->section = $orderno;
				$resource->process();
				$resource->getXML($mod,$i,true,$activities,$xmlDoc);
				$no_activities++;
			}
			flush_buffers();
			$i++;
		}
		if ($no_activities>0){
			$section->appendChild($activities);
			$structure->appendChild($section);
		} else {
			echo "\t\tNot exporting section as doesn't contain any activities\n";
		}
		$orderno++;
	}
}
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

$xmlDoc->save($course_root."/module.xml");

echo "</pre>";

echo "<p>Validating module XML file...";
libxml_use_internal_errors(true);

$xml = new DOMDocument();
$xml->load($course_root."/module.xml");

if (!$xml->schemaValidate('./oppia-schema.xsd')) {
	print '<b>Errors Found!</b>';
	libxml_display_errors();
} else {
	echo "validated<p/>";
	echo "<p>Created module XMLfile</p>";
	
	echo "<p>Adding style sheet</p>";
	
	if (!copy("styles/".$stylesheet, $course_root."/style.css")) {
		echo "<p>failed to copy stylesheet...</p>";
	}
	
	echo "<p>Course Export Complete</p>";
	$dir2zip = "output/".$USER->id."/temp";
	$outputzip = "output/".$USER->id."/".strtolower($course->shortname)."-".$versionid.".zip";
	Zip($dir2zip,$outputzip);
	echo "<p>Compressed file</p>";
	deleteDir("output/".$USER->id."/temp");
	
	echo "<p>Download exported course at <a href='".$outputzip."'>".$course->fullname."</a></p>";
	
	echo "<p><a href='cleanup.php?id=".$id."'>Cleanup files</a></p>";
	
	if(count($advice)> 0){
		echo "<p>Although your course has been exported you may want to address the following issues to make sure your course is easy to use on mobile devices:</p><ol>";
		foreach($advice as $a){
			echo "<li>".$a."</li>";
		}
	
		echo "</ol>";
	}
}

echo $OUTPUT->footer();



?>
