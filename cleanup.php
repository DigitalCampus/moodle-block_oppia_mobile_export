<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
deleteDir($CFG->dataroot."/".OPPIA_OUTPUT_DIR.$USER->id);
cleanUpExportedFiles($context, $USER->id);
echo "<p>".get_string('cleanup_end', PLUGINNAME)."</p>";

echo $OUTPUT->footer();
