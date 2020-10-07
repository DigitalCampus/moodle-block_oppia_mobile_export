<?php 
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/constants.php');

require_once($CFG->dirroot . PLUGINPATH . 'lib.php');

$id = required_param('id',PARAM_INT);
$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url(PLUGINPATH . 'cleanup.php', array('id' => $id));
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

echo "<p>".get_string('cleanup_start', PLUGINNAME)."</p>";
deleteDir(OPPIA_OUTPUT_DIR.$USER->id);
echo "<p>".get_string('cleanup_end', PLUGINNAME)."</p>";

echo $OUTPUT->footer();