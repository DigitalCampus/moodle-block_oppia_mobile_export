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
 * Step 2: Quizzes and feeback setup
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
// We get all the params from the previous step form
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$priority = required_param('coursepriority', PARAM_INT);
$sequencing = required_param('coursesequencing', PARAM_TEXT);
$defaultlang = required_param('defaultlang', PARAM_TEXT);
$keep_html = optional_param('keep_html', false, PARAM_BOOL);
$video_overlay = optional_param('video_overlay', false, PARAM_BOOL);
$server = required_param('server', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);
$thumb_height = required_param('thumb_height', PARAM_INT);
$thumb_width = required_param('thumb_width', PARAM_INT);
$section_height = required_param('section_height', PARAM_INT);
$section_width = required_param('section_width', PARAM_INT);
$tags = required_param('coursetags', PARAM_TEXT);
$tags = cleanTagList($tags);

// Save new export configurations for this course and server
add_or_update_oppiaconfig($id, 'coursepriority', $priority, $server);
add_or_update_oppiaconfig($id, 'coursetags', $tags, $server);
add_or_update_oppiaconfig($id, 'coursesequencing', $sequencing, $server);
add_or_update_oppiaconfig($id, 'defaultlang', $defaultlang, $server);
add_or_update_oppiaconfig($id, 'keep_html', $keep_html, $server);
add_or_update_oppiaconfig($id, 'video_overlay', $video_overlay, $server);
add_or_update_oppiaconfig($id, 'thumb_height', $thumb_height, $server);
add_or_update_oppiaconfig($id, 'thumb_width', $thumb_width, $server);
add_or_update_oppiaconfig($id, 'section_height', $section_height, $server);
add_or_update_oppiaconfig($id, 'section_width', $section_width, $server);

$course = $DB->get_record_select('course', "id=$id");

$PAGE->set_url(PLUGINPATH.'export/step2.php', array('id' => $id));
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

$grades = array();
for($i=0; $i<19; $i++) {
    array_push($grades,
        array('grade' => 95 - $i * 5)
    );
}

$quizzes = array();
$feedback_activities = array();
$orderno = 1;
foreach ($sections as $sect) {
    $sectionmods = explode(",", $sect->sequence);
    $sectTitle = get_section_title($sect);

    if (count($sectionmods)>0) {
        foreach ($sectionmods as $modnumber) {

            if (!$modnumber) {
                continue;
            }
            $mod = $mods[$modnumber];

            if ($mod->modname == 'quiz' && $mod->visible == 1) {
                $quiz = new MobileActivityQuiz(array(
                    'id' => $mod->id,
                    'section' =>  $orderno,
                    'server_id' => $server,
                    'course_id' => $id,
                    'shortname' => $course->shortname,
                    'summary' => $sect->summary,
                    'versionid' => 0
                ));
                $quiz->preprocess();
                if ($quiz->get_is_valid() && $quiz->get_no_questions() > 0) {
                    array_push($quizzes, array(
                        'section' => $sectTitle['display_title'],
                        'name' => format_string($mod->name),
                        'noquestions' => $quiz->get_no_questions(),
                        'id' => $mod->id,
                        'password' => $quiz->has_password()
                    ));
                }
            }

            if ($mod->modname == 'feedback' && $mod->visible == 1) {
                $feedback = new MobileActivityFeedback(array(
                    'id' => $mod->id,
                    'section' =>  $orderno,
                    'server_id' => $server,
                    'course_id' => $id,
                    'shortname' => $course->shortname,
                    'summary' => $sect->summary,
                    'versionid' => 0
                ));
                $feedback->preprocess();
                if ($feedback->get_is_valid() && $feedback->get_no_rated_questions() > 0) {
                    $grade_0_message = '';
                    $grade_100_message = '';
                    $grade_boundaries = array();
                    $gb = get_grade_boundaries($mod->id, $server);
                    usort($gb, 'sort_grade_boundaries_descending');
                    foreach ($gb as $grade_boundary) {
                        switch($grade_boundary->grade) {
                            case 0:   $grade_0_message = $grade_boundary->message; break;
                            case 100: $grade_100_message = $grade_boundary->message; break;
                            default: {
                                $selected_index = array_search(array('grade' => $grade_boundary->grade), $grades);
                                $grades[$selected_index]['selected'] = True;
                                array_push($grade_boundaries, array(
                                    'feedback_id' => $mod->id,
                                    'id' => $grade_boundary->id,
                                    'grade' => $grade_boundary->grade,
                                    'grades' => $grades,
                                    'message' => $grade_boundary->message
                                ));
                                unset($grades[$selected_index]['selected']);
                                break;
                            }
                        }
                    }

                    array_push($feedback_activities, array(
                        'section' => $sectTitle['display_title'],
                        'name' => format_string($mod->name),
                        'noquestions' => $feedback->get_no_questions(),
                        'id' => $mod->id,
                        'grades' => json_encode($grades),
                        'gradeBoundaries' => $grade_boundaries,
                        'grade_100_message' => $grade_100_message,
                        'grade_0_message' => $grade_0_message,
                    ));
                }
            }
        }
        $orderno++;
    }
}

for ($qid=0; $qid<count($quizzes); $qid++) {
    $quiz = $quizzes[$qid];

    $current_random = get_oppiaconfig($quiz['id'],'randomselect', 0);
    $quiz['random_all'] = $current_random == 0;
    $quiz['randomselect'] = [];
    if ($quiz['noquestions']>1) {
        for ($i=0; $i<$quiz['noquestions']; $i++) {
            $quiz['randomselect'][$i] = array ("idx" => $i+1, "selected" => $current_random == $i+1);
        }
    }

    $showfeedback = get_oppiaconfig($quiz['id'], 'showfeedback', 2);
    $quiz['feedback_never'] = $showfeedback == 0;
    $quiz['feedback_always'] = $showfeedback == 1;
    $quiz['feedback_endonly'] = $showfeedback == 2;

    $current_threshold = get_oppiaconfig($quiz['id'], 'passthreshold', 80);
    $quiz['passthreshold'] = [];
    for ($t=0; $t<21; $t++) {
        $quiz['passthreshold'][$t] = array ("threshold" => $t*5, "selected" => $current_threshold == $t*5);
    }

    $current_maxattempts = get_oppiaconfig($quiz['id'], 'maxattempts', 'unlimited');
    $quiz['attempts_unlimited'] = 'unlimited';
    $quiz['max_attempts'] = [];
    for ($i=0; $i<MAX_ATTEMPTS; $i++) {
        $quiz['max_attempts'][$i] = array ("num" => $i+1, "selected" => $current_maxattempts == $i+1);
    }

    $quizzes[$qid] = $quiz;
}

$form_data = array(
    'id' => $id,
    'stylesheet' => $stylesheet,
    'server' => $server,
    'course_export_status' => $course_export_status,
    'wwwroot' => $CFG->wwwroot,
    'display_quizzes_section' => !empty($quizzes),
    'quizzes' => $quizzes,
    'display_feedback_section' => !empty($feedback_activities),
    'feedback_activities' => $feedback_activities,
);

echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step2_form', $form_data);

echo $OUTPUT->footer();
