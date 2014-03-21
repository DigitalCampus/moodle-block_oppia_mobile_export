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

$quizzes = array();
/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
foreach ($sectionmods as $modnumber) {
		
	if (empty($modinfo->sections[0])) {
		continue;
	}
	$mod = $mods[$modnumber];
		
	if($mod->modname == 'quiz'){

		$quiz = new mobile_activity_quiz();
		$quiz->init($course->shortname,"Pre-test",0,0);
		$quiz->id = $mod->id;
		$quiz->section = 0;
		$quiz->preprocess();
		if ($quiz->get_is_valid() && $quiz->get_no_questions()> 0){
			$temp = new stdClass;
			$temp->section = "Topic 0";
			$temp->name = $mod->name;
			$temp->noquestions = $quiz->get_no_questions();
			$temp->id = $mod->id;
			array_push($quizzes, $temp);
		}
	}
}

$orderno = 1;
foreach($sections as $sect) {
	$sectionmods = explode(",", $sect->sequence);
	if($sect->summary && count($sectionmods)>0){
		foreach ($sectionmods as $modnumber) {
				
			if (empty($modinfo->sections[$orderno])) {
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->modname == 'quiz'){
			
				$quiz = new mobile_activity_quiz();
				$quiz->init($course->shortname,$sect->summary,0,0);
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid() && $quiz->get_no_questions()> 0){
					$temp = new stdClass;
					$temp->section = strip_tags($sect->summary);
					$temp->name = $mod->name;
					$temp->noquestions = $quiz->get_no_questions();
					$temp->id = $mod->id;
					array_push($quizzes, $temp);
				}
			}
		}
		$orderno++;
	}
}

echo $OUTPUT->header();
echo "<form name='courseconfig' method='post' action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export2.php'>";

echo "<h2>Export - step 1</h2>";
echo "<input type='hidden' name='id' value='".$COURSE->id."'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";
echo "<input type='hidden' name='stylesheet' value='".$stylesheet."'>";

if (count($quizzes)> 0){
	
	
	echo "<p>Since this course contains quizzes, please select which quizzes (if any) should be a random selection of the questions available</p>";
	
	// using table not ideal but works for now
	echo "<table>";
	echo "<tr>";
	echo "<th>Section Name</th>";
	echo "<th>Quiz Title</th>";
	echo "<th>No random questions</th>";
	echo "<th>Show feedback</th>";
	echo "<th>Allow try-again</th>";
	echo "</tr>";
	foreach ($quizzes as $quiz){
		echo "<tr>";
			echo "<td>".$quiz->section."</td>";
			echo "<td>".$quiz->name."</td>";
			
			echo "<td>";
			$current = get_oppiaconfig($quiz->id,'randomselect',0);
			echo "<select name='quiz_".$quiz->id."' id='id_s_quiz_".$quiz->id."'>";
			echo "<option value='0'";
				if ($current == 0){
					echo " selected='selected'";
				}
			echo ">Use all questions (don't randomise)</option>";
			for ($i=1; $i<$quiz->noquestions; $i++){
				echo "<option value='".$i."'";
				if ($current == $i){
					echo " selected='selected'";
				}
				echo ">select ".$i." random questions</option>";
			}
			echo "</select></td>";
			
			echo "<td>";
			$showfeedback = get_oppiaconfig($quiz->id,'showfeedback',1);
			echo "<select name='quiz_".$quiz->id."_showfeedback' id='id_showfeedback_quiz_".$quiz->id."'>";
			echo "<option value='1'";
				if ($showfeedback == 1){
					echo " selected='selected'";
				}
			echo ">True</option>";
			echo "<option value='0'";
			if ($showfeedback == 0){
				echo " selected='selected'";
			}
			echo ">False</option>";
			echo "</select></td>";
			
			echo "<td>";
			$allowtryagain = get_oppiaconfig($quiz->id,'allowtryagain',1);
			echo "<select name='quiz_".$quiz->id."_allowtryagain' id='id_allowtryagain_quiz_".$quiz->id."'>";
			echo "<option value='1'";
				if ($allowtryagain == 1){
					echo " selected='selected'";
				}
			echo ">True</option>";
			echo "<option value='0'";
			if ($allowtryagain == 0){
				echo " selected='selected'";
			}
			echo ">False</option>";
			echo "</select></td>";
		echo "</tr>";
	}
	echo "</table>";
}
echo "<p>Course Priority";
echo "<br/>This is the relative weight given to a course to help determin the ordering in which it will appear on the mobile (10 = highest priority)</p>";
echo "<select name='coursepriority' id='coursepriority'>";
for ($i=0; $i<11; $i++){
	echo "<option value='$i'";
		if ($i == 0){
			echo " selected='selected'";
		}
	echo ">$i</option>";
}
echo "</select>";

echo "<p><input type='submit' name='submit' value='Continue'></p>";
echo "</form>";
echo $OUTPUT->footer();
//redirect($CFG->wwwroot."/blocks/oppia_mobile_export/export2.php?id=".$id."&sesskey=".sesskey()."&stylesheet=".$stylesheet);


?>