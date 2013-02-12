<?php 
require_once(dirname(__FILE__) . '/../../config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/blocks/export_mobile_package/lib.php');
require_once($CFG->dirroot . '/blocks/export_mobile_package/langfilter.php');

require_once($CFG->dirroot . '/blocks/export_mobile_package/activity/activity.class.php');
require_once($CFG->dirroot . '/blocks/export_mobile_package/activity/page.php');
require_once($CFG->dirroot . '/blocks/export_mobile_package/activity/quiz.php');

require_once($CFG->libdir.'/componentlib.class.php');

$id = required_param('id',PARAM_INT);
$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url('/blocks/export_mobile_package/export.php', array('id' => $id));
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

//make course dir etc for output

deleteDir("output/".$USER->id."/temp");
// TODO - check if dir exists first
deleteDir("output/".$USER->id);
mkdir("output/".$USER->id,0777);
mkdir("output/".$USER->id."/temp/",0777);
$course_root = "output/".$USER->id."/temp/".strtolower($course->shortname);
mkdir($course_root,0777);
mkdir($course_root."/images",0777);

$context = get_context_instance(CONTEXT_COURSE, $course->id);
$PAGE->set_context($context);
preload_course_contexts($course->id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$versionid = date("YmdHis");
$module_xml = '<?xml version="1.0" encoding="utf-8"?>';
$module_xml .= "<module>";
$module_xml .= "<meta>";
$module_xml .= "<versionid>".$versionid."</versionid>";

echo "<pre>";
echo "Exporting Course: ".strip_tags($course->fullname)."\n";
$title = extractLangs($course->fullname);
if(is_array($title) && count($title)>0){
	foreach($title as $l=>$t){
		$module_xml .= "<title lang='".$l."'>".strip_tags($t)."</title>";
	}
} else {
	$module_xml .= "<title lang='".$DEFAULT_LANG."'>".strip_tags($course->fullname)."</title>";
}

$module_xml .= "<shortname>".strtolower($course->shortname)."</shortname>";

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
		$structure_xml = $page->getXML($mod,$i,false);
		$module_xml .= $structure_xml;
	}
	$i++;
}

/*-----------------------------*/

// get module image (from course summary)
$filename = extractImageFile($course->summary,$context->id,'course/summary','0',$course_root );
if($filename){
	$module_xml .= "<image filename='".$filename."'/>";
}
$index = Array();

$structure_xml = "<structure>";
$orderno = 1;
foreach($sections as $thissection) {
	flush_buffers();
	if($thissection->summary){
		
		echo "\nExporting Section: ".strip_tags($thissection->summary,'<span>')."\n";
		
		$structure_xml .= "<section order='".$orderno."'>";
		$title = extractLangs($thissection->summary);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$structure_xml .= "<title lang='".$l."'>".strip_tags($t)."</title>";
			}
		} else {
			$structure_xml .= "<title lang='".$DEFAULT_LANG."'>".strip_tags($thissection->summary)."</title>";
		}
		// get image for this section
		$filename = extractImageFile($thissection->summary, $context->id, 'course/section', $thissection->id, $course_root);
		
		if($filename){
			$structure_xml .= "<image filename='".$filename."'/>";
		}
		
		$sectionmods = explode(",", $thissection->sequence);
		$i=1;
		$structure_xml .= "<activities>";
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
				$structure_xml .= $page->getXML($mod,$i);

			}
			
			if($mod->modname == 'quiz'){
				echo "\tExporting quiz: ".$mod->name."\n";
				
				$quiz = new mobile_activity_quiz();
				$quiz->init($course->shortname,$thissection->summary);
				$quiz->courseroot = $course_root;
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->process();
				$structure_xml .= $quiz->getXML($mod,$i);
			}
			flush_buffers();
			$i++;
		}
		$structure_xml .= "</activities>";
		
		$structure_xml .= "</section>";
		$orderno++;
	}
}
$structure_xml .= "</structure>";

// add in the langs available here
$module_xml .="<langs>";
foreach($MOBILE_LANGS as $k=>$v){
	$module_xml .= "<lang>".$k."</lang>";
}
if(count($MOBILE_LANGS) == 0){
	$module_xml .= "<lang>".$DEFAULT_LANG."</lang>";
}
$module_xml .="</langs>";
$module_xml .= "</meta>";
$module_xml .= $structure_xml;

// add media includes
if(count($MEDIA) > 0){
	$module_xml .= "<media>";
	foreach ($MEDIA as $m){
		$module_xml .= "<file filename='".$m->filename."' download_url='".$m->download_url."' digest='".$m->digest."'/>";
	}
	$module_xml .= "</media>";
}

$module_xml .= "</module>";
$index = $course_root."/module.xml";
$fh = fopen($index, 'w');
fwrite($fh, $module_xml);
fclose($fh);

// output module xml file separately - just for debugging
$output = "output/module.xml";
$fh = fopen($output, 'w');
fwrite($fh, $module_xml);
fclose($fh); 

echo "\nCreated module xml file\n";


echo "\nCourse Export Complete\n";

$dir2zip = "output/".$USER->id."/temp";
$outputzip = "output/".$USER->id."/".strtolower($course->shortname)."-".$versionid.".zip";
//echo $dir2zip."\n";
//echo $outputzip."\n";
Zip($dir2zip,$outputzip);
echo "\nCompressed file\n";
deleteDir("output/temp");
echo "</pre>";
echo "Download exported course at <a href='".$outputzip."'>".$course->fullname."</a>";

echo $OUTPUT->footer();



?>
