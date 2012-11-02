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

// check mquizusername and password entered
$mquizuser = required_param('mquizuser',PARAM_TEXT);
$mquizpass = required_param('mquizpass',PARAM_TEXT);

//make course dir etc for output

deleteDir("output/temp");
mkdir("output/temp/",0777);
$course_root = "output/temp/".strtolower($course->shortname);
mkdir($course_root,0777);
mkdir($course_root."/images",0777);

$context = get_context_instance(CONTEXT_COURSE, $course->id);
$PAGE->set_context($context);
preload_course_contexts($course->id);
$modinfo = get_fast_modinfo($course);
get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

$sections = get_all_sections($course->id);

$versionid = date("YmdHis");
$module_xml = '<?xml version="1.0" encoding="utf-8"?>';
$module_xml .= "<module>";
$module_xml .= "<meta>";
$module_xml .= "<versionid>".$versionid."</versionid>";

$title = extractLangs($course->fullname);
if(is_array($title) && count($title)>0){
	foreach($title as $l=>$t){
		$module_xml .= "<title lang='".$l."'>".strip_tags($t)."</title>";
	}
} else {
	$module_xml .= "<title lang='".$DEFAULT_LANG."'>".strip_tags($course->fullname)."</title>";
}

$module_xml .= "<shortname>".$course->shortname."</shortname>";
$module_xml .= "<sourceurl>"."</sourceurl>";
$module_xml .= "<updateurl>"."</updateurl>";
$module_xml .= "<license>"."</license>";


$index = Array();

echo "<pre>";
$section = 1;
$structure_xml = "<structure>";
while ($section <= $course->numsections) {
	
	$thissection = $sections[$section];
	if($thissection->summary){
		
		echo "\nExporting Section: ".strip_tags($thissection->summary,'<span>')."\n";
		
		$structure_xml .= "<section id='".$section."'>";
		$title = extractLangs($thissection->summary);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$structure_xml .= "<title lang='".$l."'>".strip_tags($t)."</title>";
			}
		} else {
			$structure_xml .= "<title lang='".$DEFAULT_LANG."'>".strip_tags($thissection->summary)."</title>";
		}
		
		$sectionmods = explode(",", $thissection->sequence);
		$i=1;
		$structure_xml .= "<activities>";
		foreach ($sectionmods as $modnumber) {
			if (empty($modinfo->sections[$section])) {
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->modname == 'page'){
				echo "\tExporting page: ".$mod->name."\n";
				
				$page = new mobile_activity_page();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $section;
				$page->process();
				$structure_xml .= $page->getXML($mod,$i);

			}
			
			/*if($mod->modname == 'quiz'){
				echo "\tExporting quiz: ".$mod->name."\n";
				//TODO add alternate langs
				$content = toMobileQuiz($course_root, 
										$mod->id, 
										$course->shortname, 
										$thissection->summary,
										$section,
										$mquizuser,
										$mquizpass);
				$digest = md5($content);
				$structure_xml .= "<activity type='".$mod->modname."' id='".$i."' digest='".$digest."'>";
				$structure_xml .= "<title lang='en'>".$mod->name."</title>";
				$structure_xml .= "<content lang='en'>".$content."</content>";
			}
			$structure_xml .= "</activity>";*/
			$i++;
		}
		$structure_xml .= "</activities>";
		
		$structure_xml .= "</section>";
	}
	
	$section++;
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

// TODO add media includes


$module_xml .= "</module>";
$index = $course_root."/module.xml";
$fh = fopen($index, 'w');
fwrite($fh, $module_xml);
fclose($fh);

$output = "output/module.xml";
$fh = fopen($output, 'w');
fwrite($fh, $module_xml);
fclose($fh); 

echo "\nCreated module xml file\n";


echo "\nCourse Export Complete\n";

$dir2zip = "output/temp";
$outputzip = "output/".strtolower($course->shortname)."-".$versionid.".zip";
//echo $dir2zip."\n";
//echo $outputzip."\n";
Zip($dir2zip,$outputzip);
echo "\nCompressed file\n";
//deleteDir("output/temp");
echo "</pre>";
echo "Download exported course at <a href='".$outputzip."'>".$course->fullname."</a>";

echo $OUTPUT->footer();



?>
