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

$PAGE->set_url('/blocks/oppia_mobile_export/export2print.php', array('id' => $id));
context_helper::preload_course($id);
if (!$context = context_course::instance($course->id)) {
	print_error('nocontext');
}

require_login($course);

$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();


deleteDir("output/".$USER->id."/temp/print/");
deleteDir("output/".$USER->id."/temp");
deleteDir("output/".$USER->id);
if(!is_dir("output")){
	mkdir("output",0777);
}
mkdir("output/".$USER->id,0777);
mkdir("output/".$USER->id."/temp/",0777);
mkdir("output/".$USER->id."/temp/print/",0777);
$course_root = "output/".$USER->id."/temp/print/".strtolower($course->shortname);
mkdir($course_root,0777);
mkdir($course_root."/images",0777);
mkdir($course_root."/resources",0777);
mkdir($course_root."/style_resources",0777);
mkdir($course_root."/js",0777);

$PAGE->set_context($context);
context_helper::preload_course($id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$a = new stdClass();
$a->stepno = 1;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export2print_title','block_oppia_mobile_export', $a)."</h2>";

if (!copy("styles/".$stylesheet, $course_root."/style.css")) {
	echo "<p>".get_string('error_style_copy','block_oppia_mobile_export')."</p>";
}

echo "<p>".get_string('export_style_resources','block_oppia_mobile_export')."</p>";
list($filename, $extn) = explode('.', $stylesheet);
recurse_copy("styles/".$filename."-style-resources/", $course_root."/style_resources/");

recurse_copy("js/", $course_root."/js/");

$orderno = 1;
$course_index = "<ol>";
foreach($sections as $sect) {
	
	$sectionmods = explode(",", $sect->sequence);
	
	if(strip_tags($sect->summary) != "" && count($sectionmods)>0){
		$webpage =  "<html><head>";
		$webpage .= "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
		$webpage .= "<link href='style.css' rel='stylesheet' type='text/css'/>";
		$webpage .= "<script src='js/jquery-1.11.0.min.js'></script>";
		$webpage .= "<script src='js/jquery-ui-1.10.3.custom.min.js'></script>";
		$webpage .= "<script src='js/oppia.js'></script>";
		$webpage .= "<style>div.page {width:400px; border: 1px solid #000; margin: 10px auto;}</style>";
		$webpage .= "</head>";
		$webpage .= "<body onload='init();'>";
		$webpage .= "<h1>".strip_tags($sect->summary,'<span>')."</h1>";
		$empty = false;
		foreach ($sectionmods as $modnumber) {
				
			if (empty($modinfo->sections[$orderno])) {
				$empty = true;
				continue;
			}
			$mod = $mods[$modnumber];
				
			if($mod->modname == 'page'){
				$webpage .= "<h2>".$mod->name."</h2>";
				$webpage .= "<div class='page'>";
				$page = new mobile_activity_page();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $orderno;
				$webpage .= $page->export2print();
				$webpage .= "</div>";
			}
			if($mod->modname == 'quiz'){
				$quiz = new mobile_activity_quiz();
				$quiz->courseroot = $course_root;
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid()){
					$webpage .= "<h2>".$mod->name."</h2>";
					$webpage .= "<div class='quiz'>";
					$webpage .= $quiz->export2print();
					$webpage .= "</div>";
				}
			}
			if($mod->modname == 'resource'){
				$resource = new mobile_activity_resource();
				$resource->courseroot = $course_root;
				$resource->id = $mod->id;
				$resource->section = $orderno;
				$resource->process();
				$webpage .= "<h2>".$mod->name."</h2>";
				$webpage .= "<div class='resource'>";
				$webpage .= $resource->export2print();
				$webpage .= "</div>";
			}

		}
		$webpage .= "</body></html>";
			
		if (!$empty){
			$index = $course_root."/".sprintf('%02d',$orderno)."_index.html";
			$fh = fopen($index, 'w');
			fwrite($fh, $webpage);
			fclose($fh);
			$course_index .= "<li><a href='".$index."'>".strip_tags($sect->summary,'<span>')."</a></li>";
		}
		$orderno++;
	}
}

$course_index .= "</ol>";
echo $course_index;
$course_index = str_replace($course_root."/","", $course_index);
$webpage =  "<html><head>";
$webpage .= "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
$webpage .= "<link href='style.css' rel='stylesheet' type='text/css'/>";
$webpage .= "</head>";
$webpage .= "<body>";
$webpage .= "<h1>".strip_tags($course->fullname)."</h1>";
$webpage .= $course_index;
$webpage .= "</body></html>";
$index = $course_root."/index.html";
$fh = fopen($index, 'w');
fwrite($fh, $webpage);
fclose($fh);

$versionid = date("YmdHis");
$dir2zip = "output/".$USER->id."/temp/print/";
$outputzip = "output/".$USER->id."/".strtolower($course->shortname)."-preview-".$versionid.".zip";
Zip($dir2zip,$outputzip);

$a = new stdClass();
$a->zip = $outputzip;
$a->coursename = strip_tags($course->fullname);
echo "<p>".get_string('export_preview_download','block_oppia_mobile_export', $a )."</p>";

echo $OUTPUT->footer();
?>