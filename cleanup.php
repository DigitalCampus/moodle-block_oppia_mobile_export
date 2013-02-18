<?php 
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/blocks/export_mobile_package/lib.php');

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

echo "<p>cleaning up now...</p>";
deleteDir("output/".$USER->id);
echo "<p>cleaned output files</p>";

echo $OUTPUT->footer();