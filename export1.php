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
$orderno = 1;
foreach($sections as $sect) {
	$sectionmods = explode(",", $sect->sequence);
	
	$defaultSectionTitle = false;
	$sectionTitle = strip_tags($sect->summary);
	if ($sectionTitle == "") {
		$sectionTitle = get_string('sectionname', 'format_topics') . ' ' . $sect->section;
		$defaultSectionTitle = true;
	}
	
	if(count($sectionmods)>0){
		foreach ($sectionmods as $modnumber) {

			if(!$modnumber){
				continue;
			}
			$mod = $mods[$modnumber];
			
			if($mod->modname == 'quiz' && $mod->uservisible == 1){
			
				$quiz = new mobile_activity_quiz();
				$quiz->init($server_connection,$course->shortname,$sect->summary,0,0);
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid() && $quiz->get_no_questions()> 0){
					$temp = new stdClass;
					$temp->section = $sectionTitle;
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
	
	echo "<div class=\"quizzes-table\">";
	echo "<div class=\"pure-g titles pure-hidden-sm pure-hidden-xs pure-hidden-md\">";
	echo "<div class=\"pure-u-4-24\">".get_string('export_quiz_sectionname','block_oppia_mobile_export')."</div>";
	echo "<div class=\"pure-u-2-24\">".get_string('export_quiz_title','block_oppia_mobile_export')."</div>";
	echo "<div class=\"pure-u-4-24\">".get_string('export_quiz_norandom','block_oppia_mobile_export')."</div>";
	echo "<div class=\"pure-u-4-24\">".get_string('export_quiz_feedback','block_oppia_mobile_export')."</div>";
	echo "<div class=\"pure-u-2-24\">".get_string('export_quiz_passthreshold','block_oppia_mobile_export')."</div>";
	echo "<div class=\"pure-u-3-24\">".get_string('export_quiz_max_attempts','block_oppia_mobile_export')."</div>";
	echo "</div>";
	foreach ($quizzes as $quiz){
		echo "<div class=\"pure-g\">";
			echo "<div class=\"pure-u-lg-6-24 pure-u-1 quiz-title\">";
			echo "<div class=\"pure-u-lg-2-3 quiz-section\">".$quiz->section."</div>";
			echo "<div class=\"pure-u-lg-1-3\">".$quiz->name."</div></div>";
			
			echo "<div class=\"pure-u-lg-4-24 pure-u-1\">";
			echo "<span class=\"pure-hidden-lg pure-hidden-xl\">".get_string('export_quiz_norandom','block_oppia_mobile_export')."</span>";
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
			echo "</select></div>";
			
			$showfeedback = get_oppiaconfig($quiz->id,'showfeedback',2);
			echo "<div class=\"pure-u-lg-4-24 pure-u-1\">";
			echo "<span class=\"pure-hidden-lg pure-hidden-xl\">".get_string('export_quiz_feedback','block_oppia_mobile_export')."</span>";
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
			echo "</select></div>";
			
			$passthreshold = get_oppiaconfig($quiz->id,'passthreshold',80);
			echo "<div class=\"pure-u-lg-2-24 pure-u-sm-1-2 pure-u-1\">";
			echo "<span class=\"pure-hidden-lg pure-hidden-xl\">".get_string('export_quiz_passthreshold','block_oppia_mobile_export')."</span>";
			echo "<select name='quiz_".$quiz->id."_passthreshold' id='id_passthreshold_quiz_".$quiz->id."'>";
			for ($i=100; $i>0; $i = $i-5){
				echo "<option value='".$i."'";
				if ($passthreshold == $i){
					echo " selected='selected'";
				}
				echo ">".$i."</option>";
			}
			echo "</select></div>";

			$maxattempts = get_oppiaconfig($quiz->id, 'maxattempts', 'unlimited');
			echo "<div class=\"pure-u-lg-3-24 pure-u-md-1-2 pure-u-1\">";
			echo "<span class=\"pure-hidden-lg pure-hidden-xl\">".get_string('export_quiz_max_attempts','block_oppia_mobile_export')."</span>";
			echo "<select name='quiz_".$quiz->id."_maxattempts' id='id_maxattempts_quiz_".$quiz->id."'>";
			echo "<option value='unlimited' ".($maxattempts=='unlimited'?"selected='selected'":"").">";
			echo get_string('export_quiz_maxattempts_unlimited','block_oppia_mobile_export')."</option>";
			for ($i=1; $i<10; $i++){
				echo "<option value='".$i."' ".($maxattempts==$i?"selected='selected'":"").">";
				echo $i."</option>";
			}
			echo "</select></div>";
			
		echo "</div>";
	}
	echo "</div>";
}
echo "<br/>";
echo "<div class='export-section-icon'><img src='".$OUTPUT->pix_url('ic_priority', 'block_oppia_mobile_export')."'/></div>";
echo "<p><b>".get_string('export_priority_title','block_oppia_mobile_export')."</b>";
echo "<br/>".get_string('export_priority_desc','block_oppia_mobile_export')."<br/>";

$priority = get_oppiaconfig($id,'coursepriority','0',$server);
echo get_string('export_priority_label','block_oppia_mobile_export').": ";
echo "<select name='coursepriority' id='coursepriority'>";
for ($i=0; $i<11; $i++){
	echo "<option value='$i'";
		if ($i == $priority){
			echo " selected='selected'";
		}
	echo ">$i</option>";
}
echo "</select></p>";

echo "<div class='export-section-icon'><img src='".$OUTPUT->pix_url('ic_tags', 'block_oppia_mobile_export')."'/></div>";
echo "<p><b>".get_string('export_course_tags_title','block_oppia_mobile_export')."</b>";
echo "<br/>".get_string('export_course_tags_desc','block_oppia_mobile_export')."<br/>";
$tags = get_oppiaconfig($id,'coursetags','',$server);
echo "<div class='pure-g'><input name='coursetags' id='coursetags' value='".$tags."' size='100'/></div><br/></p>";

echo "<div class='export-section-icon'><img src='".$OUTPUT->pix_url('ic_language', 'block_oppia_mobile_export')."'/></div>";
echo "<p><b>".get_string('export_lang_title','block_oppia_mobile_export')."</b>";
echo "<br/>".get_string('export_lang_desc','block_oppia_mobile_export')."<br/>";
$default_lang = get_oppiaconfig($id,'default_lang',$CFG->block_oppia_mobile_export_default_lang, $server);
echo "<div class='pure-g'><input name='default_lang' id='default_lang' value='".$default_lang."' size='10'/></div><br/></p>";

echo "<div class='export-section-icon'><img src='".$OUTPUT->pix_url('ic_sequencing', 'block_oppia_mobile_export')."'/></div>";
echo "<p><b>".get_string('export_sequencing_title','block_oppia_mobile_export')."</b>";
echo "<br/>".get_string('export_sequencing_desc','block_oppia_mobile_export')."<br/>";

$sequencing = get_oppiaconfig($id,'coursesequencing','',$server);
echo "<div class=\"pure-g\"><div class='pure-u-md-7-24 pure-u-1 pure-md-right'>".get_string('export_sequencing_label','block_oppia_mobile_export').":</div><div class='pure-u-1 pure-u-md-17-24'>";
echo "<input type='radio' name='coursesequencing' value='none' ".((($sequencing == '') || ($sequencing == 'none'))?"checked":"")."> ".get_string('export_sequencing_none','block_oppia_mobile_export')."<br>";
echo "<input type='radio' name='coursesequencing' value='section' ".(($sequencing == 'section')?"checked":"")."> ".get_string('export_sequencing_section','block_oppia_mobile_export')."<br>";
echo "<input type='radio' name='coursesequencing' value='course' ".(($sequencing == 'course' )?"checked":"")."> ".get_string('export_sequencing_course','block_oppia_mobile_export')."<br>";
echo "<br></p>";
echo "</div></div>";

echo "<p><input type='submit' name='submit' value='".get_string('continue','block_oppia_mobile_export')."'></p>";
echo "</form>";
echo $OUTPUT->footer();


?>
