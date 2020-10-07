<?php 
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/constants.php');

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

require_once($CFG->libdir.'/componentlib.class.php');

$id = required_param('courseid', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url(PLUGINPATH.'export2print.php', array('id' => $id));
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


deleteDir(OPPIA_OUTPUT_DIR.$USER->id."/temp/print/");
deleteDir(OPPIA_OUTPUT_DIR.$USER->id."/temp");
deleteDir(OPPIA_OUTPUT_DIR.$USER->id);
if(!is_dir("output")){
	mkdir("output",0777);
}
mkdir(OPPIA_OUTPUT_DIR.$USER->id,0777);
mkdir(OPPIA_OUTPUT_DIR.$USER->id."/temp/",0777);
mkdir(OPPIA_OUTPUT_DIR.$USER->id."/temp/print/",0777);
$course_root = OPPIA_OUTPUT_DIR.$USER->id."/temp/print/".strtolower($course->shortname);
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
echo OPPIA_HTML_H2_START.get_string('export2print_title', PLUGINNAME, $a).OPPIA_HTML_H2_END;

if (!copy("styles/".$stylesheet, $course_root."/style.css")) {
	echo "<p>".get_string('error_style_copy', PLUGINNAME)."</p>";
}

echo "<p>".get_string('export_style_resources', PLUGINNAME)."</p>";
list($filename, $extn) = explode('.', $stylesheet);
recurse_copy("styles/".$filename."-style-resources/", $course_root."/style_resources/");

recurse_copy("js/", $course_root."/js/");

$orderno = 1;
$course_index = "<ol>";
$quiz_output = "";


/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
foreach ($sectionmods as $modnumber) {

	if (empty($modinfo->sections[0])) {
		continue;
	}
	$mod = $mods[$modnumber];

	if($mod->modname == 'quiz' && $mod->visible == 1){
	    $quiz = new MobileActivityQuiz();
		$quiz->courseroot = $course_root;
		$quiz->id = $mod->id;
		$quiz->section = $orderno;
		$quiz->preprocess();
		if ($quiz->get_is_valid()){
		    $quiz_output .= OPPIA_HTML_H2_START.$mod->name.OPPIA_HTML_H2_END;
			$quiz_output .= $quiz->export2print();
		}
	}
}


foreach($sections as $sect) {
	
	$sectionmods = explode(",", $sect->sequence);
	
	$sectionTitle = strip_tags($sect->summary);
	if ($sectionTitle == "") {
		$sectionTitle = get_string('sectionname', 'format_topics') . ' ' . $sect->section;
	}

	if(count($sectionmods)>0){
		$webpage =  "<html><head>";
		$webpage .= "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
		$webpage .= "<link href='style.css' rel='stylesheet' type='text/css'/>";
		$webpage .= "<script src='js/jquery-1.11.0.min.js'></script>";
		$webpage .= "<script src='js/jquery-ui-1.10.3.custom.min.js'></script>";
		$webpage .= "<script src='js/oppia.js'></script>";
		$webpage .= "<style>div.page {width:400px; border: 1px solid #000; margin: 10px auto;}</style>";
		$webpage .= "</head>";
		$webpage .= "<body onload='init();'>";
		$webpage .= "<h1>".strip_tags($sectionTitle,'<span>')."</h1>";
		$empty = false;
		foreach ($sectionmods as $modnumber) {
				
			if (empty($modinfo->sections[$orderno])) {
				$empty = true;
				continue;
			}
			$mod = $mods[$modnumber];
				
			if($mod->modname == 'page' && $mod->visible == 1){
			    $webpage .= OPPIA_HTML_H2_START.$mod->name.OPPIA_HTML_H2_END;
				$webpage .= "<div class='page'>";
				$page = new MobileActivityPage();
				$page->courseroot = $course_root;
				$page->id = $mod->id;
				$page->section = $orderno;
				$webpage .= $page->export2print();
				$webpage .= "</div>";
			}
			if($mod->modname == 'quiz' && $mod->visible == 1){
			    $quiz = new MobileActivityQuiz();
				$quiz->courseroot = $course_root;
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid()){
				    $webpage .= OPPIA_HTML_H2_START.$mod->name.OPPIA_HTML_H2_END;
					$webpage .= "<div class='quiz'>";
					$webpage .= $quiz->export2print();
					$quiz_output .= OPPIA_HTML_H2_START.$mod->name.OPPIA_HTML_H2_END;
					$quiz_output .= $quiz->export2print();
					$webpage .= "</div>";
				}
			}
			if($mod->modname == 'resource' && $mod->visible == 1){
			    $resource = new MobileActivityResource();
				$resource->courseroot = $course_root;
				$resource->id = $mod->id;
				$resource->section = $orderno;
				$resource->process();
				$webpage .= OPPIA_HTML_H2_START.$mod->name.OPPIA_HTML_H2_END;
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
			$course_index .= "<li><a href='".$index."'>".$sectionTitle."</a></li>";
		}
		$orderno++;
	}
}

/*
 * Add course index
 */
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

/*
 * Export question bank - for review of all questions
 */
$quizpage =  "<html><head>";
$quizpage .= "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
$quizpage .= "<link href='style.css' rel='stylesheet' type='text/css'/>";
$quizpage .= "</head>";
$quizpage .= "<body>";
$quizpage .= "<h1>".strip_tags($course->fullname)." - Quizzes </h1>";
$quizpage .= $quiz_output;
$quizpage .= "</body></html>";
$quiz = $course_root."/quiz.html";
$fh = fopen($quiz, 'w');
fwrite($fh, $quizpage);
fclose($fh);

$a = new stdClass();
$a->link = $course_root."/quiz.html";
echo "<p>".get_string('export_preview_quiz', PLUGINNAME, $a )."</p>";

/*
 * create download package
 */

$versionid = date("YmdHis");
$dir2zip = OPPIA_OUTPUT_DIR.$USER->id."/temp/print/";
$outputzip = OPPIA_OUTPUT_DIR.$USER->id."/".strtolower($course->shortname)."-preview-".$versionid.".zip";
Zip($dir2zip,$outputzip);

$a = new stdClass();
$a->zip = $outputzip;
$a->coursename = strip_tags($course->fullname);
echo "<p>".get_string('export_preview_download', PLUGINNAME, $a )."</p>";

echo $OUTPUT->footer();
?>