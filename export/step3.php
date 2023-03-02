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
 * Step 3: Configure password protection (for sections and feedback activities)
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'oppia_api_helper.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');

// We get all the params from the previous step form.
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$server = required_param('server', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);

$course = $DB->get_record('course', array('id' => $id));

$PAGE->set_url(PLUGINPATH.'export/step3.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
    print_error('nocontext');
}

require_login($course);

$CFG->cachejs = false;

$PAGE->requires->jquery();
$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$PAGE->requires->js(PLUGINPATH.'publish/publish_media.js');

global $MOBILE_LANGS;
$MOBILE_LANGS = array();

global $MEDIA;
$MEDIA = array();

$PAGE->set_context($context);
context_helper::preload_course($id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

add_publishing_log($server, $USER->id, $id, "export_start", "Export process starting");


echo "<h2>".get_string('export_step3_title', PLUGINNAME)."</h2>";
echo '<div class="oppia_export_section py-3">';

$config_sections = array();
$sect_orderno = 1;
foreach ($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest.
    if ($sect->section == 0) {
        continue;
    }

    $sectionmods = explode(",", $sect->sequence);
    $sectTitle = get_section_title($sect);

    if (count($sectionmods) > 0) {
        $activity_count = 0;
        $activities = [];

        foreach ($sectionmods as $modnumber) {
            if ($modnumber == "" || $modnumber === false) {
                continue;
            }
            $mod = $mods[$modnumber];

            if ($mod->visible != 1) {
                continue;
            }
            if ( ($mod->modname == 'page') ||
                    ($mod->modname == 'resource') ||
                    ($mod->modname == 'url')) {
                $activity_count++;
            } else if ($mod->modname == 'feedback') {
                $activity_count++;

                $password = get_oppiaconfig($mod->id, 'password', '', $server, false);

                array_push($activities, array(
                    'modid' => $mod->id,
                    'title' => format_string($mod->name),
                    'password' => $password
                ));

                $grades = optional_param_array('grade_'.$mod->id, array(), PARAM_INT);
                $messages = optional_param_array('message_'.$mod->id, array(), PARAM_TEXT);

                for ($i = 0; $i < 21; $i++) {
                    $value = $i * 5;
                    if (in_array($value, $grades, false)) {
                        $index = array_search($value, $grades);
                        $message = $messages[$index];
                        if ($message) {
                            add_or_update_grade_boundary($mod->id, $value, $message, $server);
                        } else {
                            delete_grade_boundary($mod->id, $value, $server);
                        }
                    } else {
                        delete_grade_boundary($mod->id, $value, $server);
                    }

                }

            } else if ($mod->modname == 'quiz') {
                $activity_count++;
                // For the quizzes, we save the configuration entered.
                $random = optional_param('quiz_'.$mod->id.'_randomselect', 0, PARAM_INT);
                $showfeedback = optional_param('quiz_'.$mod->id.'_showfeedback', 1, PARAM_INT);
                $passthreshold = optional_param('quiz_'.$mod->id.'_passthreshold', 0, PARAM_INT);
                $maxattempts = optional_param('quiz_'.$mod->id.'_maxattempts', 'unlimited', PARAM_INT);

                if ($maxattempts == 0) {
                    $maxattempts = 'unlimited';
                }
                add_or_update_oppiaconfig($mod->id, 'randomselect', $random);
                add_or_update_oppiaconfig($mod->id, 'showfeedback', $showfeedback);
                add_or_update_oppiaconfig($mod->id, 'passthreshold', $passthreshold);
                add_or_update_oppiaconfig($mod->id, 'maxattempts', $maxattempts);
            }
        }

        if ($activity_count > 0) {

            $password = get_oppiaconfig($sect->id, 'password', '', $server, false);

            array_push($config_sections, array(
                'sect_orderno' => $sect_orderno,
                'sect_id' => $sect->id,
                'password' => $password,
                'activity_count' => $activity_count,
                'title' => $sectTitle['display_title'],
                'activities' => $activities
            ));
            $sect_orderno++;
        } else {
            echo '<div class="step">'.get_string('section_password_invalid', PLUGINNAME, $sectTitle['display_title']).'</div>';
        }
        flush_buffers();
    }
}
echo '</div>';

if ($sect_orderno <= 1) {
    echo '<h3>'.get_string('error_exporting', PLUGINNAME).'</h3>';
    echo '<p>'.get_string('error_exporting_no_sections', PLUGINNAME).'</p>';
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->render_from_template(
    PLUGINNAME.'/export_step3_form',
    array(
        'id' => $id,
        'server_id' => $server,
        'stylesheet' => $stylesheet,
        'course_export_status' => $course_export_status,
        'sections' => $config_sections,
        'wwwroot' => $CFG->wwwroot));

echo $OUTPUT->footer();
