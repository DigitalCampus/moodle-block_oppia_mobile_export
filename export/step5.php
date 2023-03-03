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
 * Step 5: Configure preserving digests for each activity
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'activity/processor.php');

$id = required_param('id', PARAM_INT);
$mediafiles = required_param('mediafiles', PARAM_TEXT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$tags = required_param('coursetags', PARAM_TEXT);
$server = required_param('server_id', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);
$courseroot = required_param('courseroot', PARAM_TEXT);
$isdraft = ($course_export_status == 'draft');
$defaultlang = get_oppiaconfig($id, 'defaultlang', $CFG->block_oppia_mobile_export_default_lang, $server);
$activity_summaries = json_decode(required_param('activity_summaries', PARAM_TEXT), true);

$course = $DB->get_record('course', array('id' => $id));
$PAGE->set_url(PLUGINPATH.'export/step5.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
    print_error('nocontext');
}
$PAGE->set_context($context);
context_helper::preload_course($id);
require_login($course);

$PAGE->set_pagelayout('course');
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

echo "<h2>".get_string('export_step5_title', PLUGINNAME)."</h2>";

$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();
$keephtml = get_oppiaconfig($course->id, 'keephtml', '', $server);
$videooverlay = get_oppiaconfig($id, 'videooverlay', '', $server);

$processor = new ActivityProcessor(array(
    'courseroot' => $courseroot,
    'server_id' => $server,
    'course_id' => $course->id,
    'course_shortname' => $course->shortname,
    'versionid' => '0',
    'keephtml' => $keephtml,
    'videooverlay' => $videooverlay,
    'printlogs' => false
));

$config_sections = array();
$unmodified_activities = array();
$sect_orderno = 1;
foreach ($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest.
    if ($sect->section == 0) {
        continue;
    }

    $sectTitle = get_section_title($sect);

    $modified_activities_count = 0;
    $modified_activities = array();
    $act_orderno = 1;
    $processor->set_current_section($sect_orderno);

    $sectionmods = explode(",", $sect->sequence);
    foreach ($sectionmods as $modnumber) {

        if (!array_key_exists($modnumber, $mods)) {
            continue;
        }

        $mod = $mods[$modnumber];
        if ($mod != null) {
            $last_published_digest_entry = $DB->get_record(OPPIA_DIGEST_TABLE,
                array(
                    'courseid' => $course->id,
                    'modid' => $mod->id,
                    'serverid' => $server,
                ),
                'moodleactivitymd5, oppiaserverdigest, nquestions',
            );

            if ($last_published_digest_entry) {
                $activity_summary = $activity_summaries[$mod->id];
                if ($activity_summary != null) {
                    $moodle_activity_md5 = $last_published_digest_entry->moodleactivitymd5;
                    $current_digest = $activity_summary['digest'];

                    if (strcmp($moodle_activity_md5, $current_digest) !== 0) { // The activity was modified.

                        /* For 'quiz' and 'feedback' activities, don't show option to preserve digest
                         * if the number of questions has changed.
                         */
                        if (($mod->modname == 'quiz' || $mod->modname == 'feedback') &&
                            $last_published_digest_entry->nquestions != $activity_summary['no_questions']) {
                            continue;
                        }

                        $modified_activities_count++;
                        array_push($modified_activities, array(
                            'name' => format_string($mod->name),
                            'act_id' => $mod->id,
                            'current_digest' => $current_digest,
                            'last_published_digest' => $last_published_digest_entry->oppiaserverdigest,
                            'icon' => $mod->get_icon_url()->out(),
                        ));
                    } else { // The activity wasn't modified.
                        // Include a parameter preserving the value of the digest that is currently in use in the Oppia Server.
                        $unmodified_activities['digest_' . $current_digest] = $last_published_digest_entry->oppiaserverdigest;
                    }
                }
            }
        }
    }

    if ($act_orderno > 1) {
        $sect_orderno++;
    }

    if ($modified_activities_count > 0) {
        array_push($config_sections, array(
            'title' => $sectTitle['display_title'],
            'activities' => $modified_activities,
        ));
    }
}

$form_values = array_merge(
    $unmodified_activities,
    array(
        'id' => $id,
        'server_id' => $server,
        'mediafiles' => json_decode($mediafiles, true),
        'stylesheet' => $stylesheet,
        'coursetags' => $tags,
        'course_export_status' => $course_export_status,
        'courseroot' => $courseroot,
        'has_modified_sections' => count($config_sections) > 0,
        'sections' => $config_sections,
        'wwwroot' => $CFG->wwwroot,
        'resolve' => resolve(),
    )
);

// The next step expect in the form parameters the media_url and the media_length for every media file.
foreach ($form_values['mediafiles'] as $media_file) {
    $digest = $media_file['digest'];

    $media_url = optional_param($digest, null, PARAM_TEXT);
    $media_length = optional_param($digest.'_length', null, PARAM_INT);

    $form_values[$digest] = $media_url;
    $form_values[$digest.'_length'] = $media_length;
}

echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step5_form', $form_values);

echo $OUTPUT->footer();

// This function allows to resolve the value of a mustache variable when the variable name depends on another variable.
// Is equivalent as doing {{{{variable_name}}}}, where we first resolve the value of {{variable_name}},
// which gives us variable_value, and then we do {{variable_value}}.
function resolve() {
    return function ($text, $render) {
        return $render->render("{{" . $render->render($text) . "}}");
    };
}
