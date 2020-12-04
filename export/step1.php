<?php 
require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

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
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');


const PRIORITY_LEVELS = 10;
const MAX_ATTEMPTS = 10;
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);
$course_status = required_param('course_status', PARAM_TEXT);
$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url(PLUGINPATH.'export/step1.php', array('id' => $id));
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
$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
	echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
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
			
			if($mod->modname == 'quiz' && $mod->visible == 1){
			    $quiz = new MobileActivityQuiz();
				$quiz->init($server_connection,$course->shortname,$sect->summary,0,0);
				$quiz->id = $mod->id;
				$quiz->section = $orderno;
				$quiz->preprocess();
				if ($quiz->get_is_valid() && $quiz->get_no_questions()> 0){
					array_push($quizzes, array(
						'section' => $sectionTitle,
						'name' => $mod->name,
						'noquestions' => $quiz->get_no_questions(),
						'id' => $mod->id
					));
				}
			}
		}
		$orderno++;
	}
}

for ($qid=0; $qid<count($quizzes); $qid++){
	$quiz = $quizzes[$qid];
		
		$current_random = get_oppiaconfig($quiz['id'],'randomselect', 0);
		$quiz['random_all'] = $current_random == 0;
		$quiz['randomselect'] = [];
		if ($quiz['noquestions']>1){
			for ($i=0; $i<$quiz['noquestions']; $i++){
				$quiz['randomselect'][$i] = array ("idx" => $i+1, "selected" => $current_random == $i+1); 
			}
		}
		
		$showfeedback = get_oppiaconfig($quiz['id'], 'showfeedback', 2);
		$quiz['feedback_never'] = $showfeedback == 0;
		$quiz['feedback_always'] = $showfeedback == 1;
		$quiz['feedback_endonly'] = $showfeedback == 2;

		$current_threshold = get_oppiaconfig($quiz['id'], 'passthreshold', 80);
		$quiz['passthreshold'] = [];
		for ($t=0; $t<21; $t++){
			  $quiz['passthreshold'][$t] = array ("threshold" => $t*5, "selected" => $current_threshold == $t*5);
		}

		$current_maxattempts = get_oppiaconfig($quiz['id'], 'maxattempts', 'unlimited');
		$quiz['attempts_unlimited'] = $maxattempts=='unlimited';
		$quiz['max_attempts'] = [];
		for ($i=0; $i<MAX_ATTEMPTS; $i++){
			$quiz['max_attempts'][$i] = array ("num" => $i+1, "selected" => $current_maxattempts == $i+1); 
		}

	 $quizzes[$qid] = $quiz;
}

$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', $server);
$priorities = [];
for ($i=0; $i<=PRIORITY_LEVELS; $i++){
	$priorities[$i] = array ("idx" => $i, "selected" => $i == $priority );
}
$sequencing = get_oppiaconfig($id,'coursesequencing','',$server);

$base_settings = array(
	'priorities' 	=> $priorities,
	'tags' 			=> get_oppiaconfig($id,'coursetags','', $server),
	'default_lang' 	=> get_oppiaconfig($id,'default_lang', $CFG->block_oppia_mobile_export_default_lang, $server),
	'sequencing_none' 	 => $sequencing == '' || $sequencing == 'none',
	'sequencing_section' => $sequencing == 'section',
	'sequencing_section' => $sequencing == 'course',
);

echo "<form name='courseconfig' method='post' action='".$CFG->wwwroot.PLUGINPATH."export/step2.php'>";

$a = new stdClass();
$a->stepno = 1;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title', PLUGINNAME, $a)."</h2>";
echo "<input type='hidden' name='id' value='".$COURSE->id."'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";
echo "<input type='hidden' name='stylesheet' value='".$stylesheet."'>";
echo "<input type='hidden' name='server' value='".$server."'>";
echo "<input type='hidden' name='course_export_status' value='".$course_status."'>";

if (!empty($quizzes)){
	echo "<p>".get_string('export_contains_quizzes', PLUGINNAME)."</p>";
	echo $OUTPUT->render_from_template(PLUGINNAME.'/quizzes', $quizzes);
}

echo $OUTPUT->render_from_template(PLUGINNAME.'/base_settings', $base_settings);
echo $OUTPUT->render_from_template(PLUGINNAME.'/submit_btn', get_string('continue', PLUGINNAME));

echo "</form>";
echo $OUTPUT->footer();

?>
