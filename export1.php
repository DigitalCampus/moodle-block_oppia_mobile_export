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
$server = required_param('server',PARAM_TEXT);

$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url('/blocks/oppia_mobile_export/export1.php', array('id' => $id));
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

echo $OUTPUT->header();

// Check specified server belongs to current user
$server_connection = $DB->get_record('block_oppia_mobile_server', array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner','block_oppia_mobile_export')."</p>";
	echo $OUTPUT->footer();
	die();
}


$quizzes = array();
/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
foreach ($sectionmods as $modnumber) {
		
	if (empty($modinfo->sections[0])) {
		continue;
	}
	$mod = $mods[$modnumber];
		
	if($mod->modname == 'quiz' && $mod->visible == 1){

		$quiz = new mobile_activity_quiz();
		$quiz->init($server_connection,$course->shortname,"Pre-test",0,0);
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
			
			if($mod->modname == 'quiz' && $mod->visible == 1){
			
				$quiz = new mobile_activity_quiz();
				$quiz->init($server_connection,$course->shortname,$sect->summary,0,0);
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


echo "<form name='courseconfig' method='post' action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export2.php'>";

$a = new stdClass();
$a->stepno = 1;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title','block_oppia_mobile_export', $a)."</h2>";
echo "<input type='hidden' name='id' value='".$COURSE->id."'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";
echo "<input type='hidden' name='stylesheet' value='".$stylesheet."'>";
echo "<input type='hidden' name='server' value='".$server."'>";

if (count($quizzes)> 0){
	
	echo "<p>".get_string('export_contains_quizzes','block_oppia_mobile_export')."</p>";
	
	// using table not ideal but works for now
	echo "<table>";
	echo "<tr>";
	echo "<th>".get_string('export_quiz_sectionname','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_title','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_norandom','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_feedback','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_tryagain','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_passthreshold','block_oppia_mobile_export')."</th>";
	echo "<th>".get_string('export_quiz_availability','block_oppia_mobile_export')."</th>";
	echo "</tr>";
	foreach ($quizzes as $quiz){
		echo "<tr>";
			echo "<td>".$quiz->section."</td>";
			echo "<td>".$quiz->name."</td>";
			
			echo "<td>";
			$current = get_oppiaconfig($quiz->id,'randomselect',0);
			echo "<select name='quiz_".$quiz->id."_randomselect' id='id_s_quiz_".$quiz->id."'>";
			echo "<option value='0'";
				if ($current == 0){
					echo " selected='selected'";
				}
			echo ">".get_string('export_quiz_norandom_all','block_oppia_mobile_export')."</option>";
			for ($i=1; $i<$quiz->noquestions; $i++){
				echo "<option value='".$i."'";
				if ($current == $i){
					echo " selected='selected'";
				}
				echo ">".get_string('export_quiz_norandom_selectx','block_oppia_mobile_export',$i)."</option>";
			}
			echo "</select></td>";
			
			$showfeedback = get_oppiaconfig($quiz->id,'showfeedback',2);
			echo "<td>";
			echo "<select name='quiz_".$quiz->id."_showfeedback' id='id_showfeedback_quiz_".$quiz->id."'>";
			
			echo "<option value='1'";
				if ($showfeedback == 1){
					echo " selected='selected'";
				}
			echo ">".get_string('feedback_always','block_oppia_mobile_export')."</option>";
			
			echo "<option value='0'";
			if ($showfeedback == 0){
				echo " selected='selected'";
			}
			echo ">".get_string('feedback_never','block_oppia_mobile_export')."</option>";
			
			echo "<option value='2'";
			if ($showfeedback == 2){
				echo " selected='selected'";
			}
			echo ">".get_string('feedback_endonly','block_oppia_mobile_export')."</option>";
			echo "</select></td>";
			
			$allowtryagain = get_oppiaconfig($quiz->id,'allowtryagain',1);
			echo "<td>";
			echo "<select name='quiz_".$quiz->id."_allowtryagain' id='id_allowtryagain_quiz_".$quiz->id."'>";
			echo "<option value='1'";
				if ($allowtryagain == 1){
					echo " selected='selected'";
				}
			echo ">".get_string('true','block_oppia_mobile_export')."</option>";
			echo "<option value='0'";
			if ($allowtryagain == 0){
				echo " selected='selected'";
			}
			echo ">".get_string('false','block_oppia_mobile_export')."</option>";
			echo "</select></td>";
			
			$passthreshold = get_oppiaconfig($quiz->id,'passthreshold',80);
			echo "<td>";
			echo "<select name='quiz_".$quiz->id."_passthreshold' id='id_passthreshold_quiz_".$quiz->id."'>";
			for ($i=100; $i>0; $i = $i-5){
				echo "<option value='".$i."'";
				if ($passthreshold == $i){
					echo " selected='selected'";
				}
				echo ">".$i."</option>";
			}
			echo "</select></td>";
			
			$availability = get_oppiaconfig($quiz->id,'availability','0');
			echo "<td>";
			echo "<select name='quiz_".$quiz->id."_availability' id='id_availability_quiz_".$quiz->id."'>";
			echo "<option value='0'";
				if ($availability == 0){
					echo " selected='selected'";
				}
			echo ">".get_string('availability_always','block_oppia_mobile_export')."</option>";
			
			echo "<option value='1'";
			if ($availability == 1){
				echo " selected='selected'";
			}
			echo ">".get_string('availability_section','block_oppia_mobile_export')."</option>";
			
			echo "<option value='2'";
			if ($availability == 2){
				echo " selected='selected'";
			}
			echo ">".get_string('availability_course','block_oppia_mobile_export')."</option>";
			echo "</select></td>";
			
		echo "</tr>";
	}
	echo "</table>";
}
echo "<p>".get_string('export_priority_title','block_oppia_mobile_export');
echo "<br/>".get_string('export_priority_desc','block_oppia_mobile_export')."</p>";
$priority = get_oppiaconfig($id,'coursepriority','0');
echo "<select name='coursepriority' id='coursepriority'>";
for ($i=0; $i<11; $i++){
	echo "<option value='$i'";
		if ($i == $priority){
			echo " selected='selected'";
		}
	echo ">$i</option>";
}
echo "</select>";

echo "<p><input type='submit' name='submit' value='".get_string('continue','block_oppia_mobile_export')."'></p>";
echo "</form>";
echo $OUTPUT->footer();


?>
