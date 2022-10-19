<?php
/**
 * Oppia Mobile Export
 * Step 4: Configure preserving digests for each activity
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'activity/processor.php');

$id = required_param('id', PARAM_INT);
$media_files = required_param('media_files', PARAM_TEXT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$tags = required_param('coursetags', PARAM_TEXT);
$server = required_param('server_id',PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);
$course_root = required_param('course_root', PARAM_TEXT);
$is_draft = ($course_export_status == 'draft');
$DEFAULT_LANG = get_oppiaconfig($id,'default_lang', $CFG->block_oppia_mobile_export_default_lang, $server);

$course = $DB->get_record('course', array('id'=>$id));
$PAGE->set_url(PLUGINPATH.'export/step4.php', array('id' => $id));
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

$a = new stdClass();
$a->stepno = 4;
$a->coursename = strip_tags($course->fullname);
echo "<h2>".get_string('export_title', PLUGINNAME, $a)."</h2>";

$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();
$keep_html = get_oppiaconfig($course->id, 'keep_html', '', $server);

$processor = new ActivityProcessor(array(
    'course_root' => $course_root,
    'server_id' => $server,
    'course_id' => $course->id,
    'course_shortname' => $course->shortname,
    'versionid' => '0',
    'keep_html' => $keep_html,
    'print_logs' => false
));

$config_sections = array();
$unmodified_activities = array();
$sect_orderno = 1;
foreach($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest
    if ($sect->section == 0) {
        continue;
    }

    $section_title = strip_tags(format_string($sect->summary));
    // If the course has no summary, we try to use the section name
    if ($section_title == "") {
        $section_title = strip_tags(format_string($sect->name));
    }
    // If the course has neither summary nor name, use the default topic title
    if ($section_title == "") {
        $section_title = get_string('sectionname', 'format_topics') . ' ' . $sect->section;
    }

    $modified_activities_count = 0;
    $modified_activities = array();
    $act_orderno = 1;
    $processor->set_current_section($sect_orderno);

    $sectionmods = explode(",", $sect->sequence);
    foreach ($sectionmods as $modnumber) {

        $mod = $mods[$modnumber];
        if ($mod != null) {
            $activity = $processor->process_activity($mod, $sect, $act_orderno);
            if ($activity != null){
                $act_orderno++;
            }
            $last_published_digest_entry = $DB->get_record(OPPIA_DIGEST_TABLE,
                array(
                    'courseid' => $course->id,
                    'modid' => $mod->id,
                    'serverid' => $server,
                ),
                'moodleactivitymd5, oppiaserverdigest, nquestions',
            );

            if($last_published_digest_entry) {
                $moodle_activity_md5 = $last_published_digest_entry->moodleactivitymd5;
                $current_digest = $activity->md5;

                if (strcmp($moodle_activity_md5, $current_digest) !== 0) { // The activity was modified

                    // For 'quiz' and 'feedback' activities, don't show option to preserve digest if the number of questions has changed
                    if (($mod->modname == 'quiz' or $mod->modname == 'feedback') and
                        $last_published_digest_entry->nquestions != $activity->get_no_questions()) {
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
                } else { // The activity wasn't modified
                    // Include a parameter preserving the value of the digest that is currently in use in the Oppia Server
                    $unmodified_activities['digest_'.$current_digest] = $last_published_digest_entry->oppiaserverdigest;
                }
            }
        }
    }

    if ($act_orderno > 1){
        $sect_orderno++;
    }

    if ($modified_activities_count > 0) {
        array_push($config_sections, array(
            'title' => $section_title,
            'activities' => $modified_activities,
        ));
    }
}

$form_values = array_merge(
    $unmodified_activities,
    array(
        'id' => $id,
        'server_id' => $server,
        'media_files' => json_decode($media_files, true),
        'stylesheet' => $stylesheet,
        'coursetags' => $tags,
        'course_export_status' => $course_export_status,
        'course_root' => $course_root,
        'sections' => $config_sections,
        'wwwroot' => $CFG->wwwroot,
        'resolve' => resolve(),
    )
);

// The next step expect in the form parameters the media_url and the media_length for every media file.
foreach($form_values['media_files'] as $media_file){
    $digest = $media_file['digest'];

    $media_url = optional_param($digest, null, PARAM_TEXT);
    $media_length = optional_param($digest.'_length', null, PARAM_INT);

    $form_values[$digest] = $media_url;
    $form_values[$digest.'_length'] = $media_length;
}

// If there are no activities for preserving the ids, redirect to the following step.
if (count($config_sections) == 0) {
    unset($form_values['sections']);
    unset($form_values['media_files']);
    unset($form_values['resolve']);
    $step5_url = new moodle_url(PLUGINPATH . 'export/step5.php', $form_values);
    $redirect_message = get_string('export_no_content_changes_message', PLUGINNAME);
    redirect($step5_url, $redirect_message);
}

echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step4_form', $form_values);

echo $OUTPUT->footer();

// This function allows to resolve the value of a mustache variable when the variable name depends on another variable.
// Is equivalent as doing {{{{variable_name}}}}, where we first resolve the value of {{variable_name}},
// which gives us variable_value, and then we do {{variable_value}}.
function resolve() {
    return function ($text, $render) {
        return $render->render("{{" . $render->render($text) . "}}");
    };
}

?>

