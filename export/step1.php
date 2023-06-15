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

/**
 * Oppia Mobile Export
 * Step 1: Main course export configuration
 */

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
$stylesheet = required_param('course_stylesheet', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);
$coursestatus = required_param('course_status', PARAM_TEXT);
$course = $DB->get_record_select('course', "id=$id");

$PAGE->set_url(PLUGINPATH.'export/step1.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
    throw new moodle_exception('nocontext');
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

// Check specified server belongs to current user.
$serverconnection = $DB->get_record(OPPIA_SERVER_TABLE, array('id' => $server));
if (!$serverconnection && $server != "default") {
    echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}

$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', true, $server);
$priorities = [];
for ($i = 0; $i <= PRIORITY_LEVELS; $i++) {
    $priorities[$i] = array ("idx" => $i, "selected" => $i == $priority );
}

$sequencing = get_oppiaconfig($id, 'coursesequencing', '', true, $server);
$keephtml = get_oppiaconfig($id, 'keephtml', '', true, $server);
$videooverlay = get_oppiaconfig($id, 'videooverlay', '', true, $server);
$thumbheight = get_oppiaconfig($id, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, true, $server);
$thumbwidth = get_oppiaconfig($id, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, true, $server);
$sectionheight = get_oppiaconfig($id, 'section_height', $CFG->block_oppia_mobile_export_section_icon_height, true, $server);
$sectionwidth = get_oppiaconfig($id, 'section_width', $CFG->block_oppia_mobile_export_section_icon_width, true, $server);

$basesettings = array(
    'priorities' => $priorities,
    'tags' => get_oppiaconfig($id, 'coursetags', '', true, $server),
    'default_lang' => get_oppiaconfig($id, 'default_lang', $CFG->block_oppia_mobile_export_default_lang, true, $server),
    'keephtml' => $keephtml,
    'videooverlay' => $videooverlay,
    'thumbheight' => $thumbheight,
    'thumbwidth' => $thumbwidth,
    'sectionheight' => $sectionheight,
    'sectionwidth' => $sectionwidth,
    'sequencing_none' => $sequencing == '' || $sequencing == 'none',
    'sequencing_section' => $sequencing == 'section',
    'sequencing_course' => $sequencing == 'course',
);

echo $OUTPUT->render_from_template(
    PLUGINNAME.'/export_step1_form',
    array(
        'id' => $id,
        'stylesheet' => $stylesheet,
        'server' => $server,
        'courseexportstatus' => $coursestatus,
        'wwwroot' => $CFG->wwwroot,
        'base_settings' => $basesettings
    )
);

echo $OUTPUT->footer();
