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
$server = required_param('serverid', PARAM_TEXT);
$courseexportstatus = required_param('courseexportstatus', PARAM_TEXT);
$courseroot = required_param('courseroot', PARAM_TEXT);
$isdraft = ($courseexportstatus == 'draft');
$DEFAULTLANG = get_oppiaconfig($id, 'default_lang', $CFG->block_oppia_mobile_export_default_lang, true, $server);
$activitysummaries = json_decode(required_param('activity_summaries', PARAM_TEXT), true);

$course = $DB->get_record('course', array('id' => $id));
$PAGE->set_url(PLUGINPATH.'export/step5.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
    throw new moodle_exception('nocontext');
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
$keephtml = get_oppiaconfig($course->id, 'keephtml', '', true, $server);
$videooverlay = get_oppiaconfig($id, 'videooverlay', '', true, $server);

$processor = new ActivityProcessor(array(
    'courseroot' => $courseroot,
    'serverid' => $server,
    'courseid' => $course->id,
    'courseshortname' => $course->shortname,
    'versionid' => '0',
    'keephtml' => $keephtml,
    'videooverlay' => $videooverlay,
    'printlogs' => false
));

$configsections = array();
$unmodifiedactivities = array();
$sectorderno = 1;
foreach ($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest.
    if ($sect->section == 0) {
        continue;
    }

    $secttitle = get_section_title($sect);

    $modifiedactivitiescount = 0;
    $modifiedactivities = array();
    $actorderno = 1;
    $processor->set_current_section($sectorderno);

    $sectionmods = explode(",", $sect->sequence);
    foreach ($sectionmods as $modnumber) {

        if (!array_key_exists($modnumber, $mods)) {
            continue;
        }

        $mod = $mods[$modnumber];
        if ($mod != null) {
            $lastpublisheddigestentry = $DB->get_record(OPPIA_DIGEST_TABLE,
                array(
                    'courseid' => $course->id,
                    'modid' => $mod->id,
                    'serverid' => $server,
                ),
                'moodleactivitymd5, oppiaserverdigest, nquestions',
            );

            if ($lastpublisheddigestentry) {
                $activitysummary = $activitysummaries[$mod->id];
                if ($activitysummary != null) {
                    $moodleactivitymd5 = $lastpublisheddigestentry->moodleactivitymd5;
                    $currentdigest = $activitysummary['digest'];

                    if (strcmp($moodleactivitymd5, $currentdigest) !== 0) { // The activity was modified.

                        /* For 'quiz' and 'feedback' activities, don't show option to preserve digest
                         * if the number of questions has changed.
                         */
                        if (($mod->modname == 'quiz' || $mod->modname == 'feedback') &&
                            $lastpublisheddigestentry->nquestions != $activitysummary['no_questions']) {
                            continue;
                        }

                        $modifiedactivitiescount++;
                        array_push($modifiedactivities, array(
                            'name' => format_string($mod->name),
                            'act_id' => $mod->id,
                            'current_digest' => $currentdigest,
                            'last_published_digest' => $lastpublisheddigestentry->oppiaserverdigest,
                            'icon' => $mod->get_icon_url()->out(),
                        ));
                    } else { // The activity wasn't modified.
                        // Include a parameter preserving the value of the digest that is currently in use in the Oppia Server.
                        $unmodifiedactivities['digest_' . $currentdigest] = $lastpublisheddigestentry->oppiaserverdigest;
                    }
                }
            }
        }
    }

    if ($actorderno > 1) {
        $sectorderno++;
    }

    if ($modifiedactivitiescount > 0) {
        array_push($configsections, array(
            'title' => $secttitle['display_title'],
            'activities' => $modifiedactivities,
        ));
    }
}

$formvalues = array_merge(
    $unmodifiedactivities,
    array(
        'id' => $id,
        'serverid' => $server,
        'mediafiles' => json_decode($mediafiles, true),
        'stylesheet' => $stylesheet,
        'coursetags' => $tags,
        'courseexportstatus' => $courseexportstatus,
        'courseroot' => $courseroot,
        'has_modified_sections' => count($configsections) > 0,
        'sections' => $configsections,
        'wwwroot' => $CFG->wwwroot,
        'resolve' => resolve(),
    )
);

// The next step expect in the form parameters the mediaurl and the media_length for every media file.
foreach ($formvalues['mediafiles'] as $mediafile) {
    $digest = $mediafile['digest'];

    $mediaurl = optional_param($digest, null, PARAM_TEXT);
    $medialength = optional_param($digest.'_length', null, PARAM_INT);

    $formvalues[$digest] = $mediaurl;
    $formvalues[$digest.'_length'] = $medialength;
}

echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step5_form', $formvalues);

echo $OUTPUT->footer();

// This function allows to resolve the value of a mustache variable when the variable name depends on another variable.
// Is equivalent as doing {{{{variable_name}}}}, where we first resolve the value of {{variable_name}},
// which gives us variable_value, and then we do {{variable_value}}.
function resolve() {
    return function ($text, $render) {
        return $render->render("{{" . $render->render($text) . "}}");
    };
}
