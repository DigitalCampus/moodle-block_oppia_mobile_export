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
 * Step 4: Activities export and local media management
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
require_once($pluginroot . 'activity/processor.php');

require_once($CFG->libdir.'/componentlib.class.php');

// We get all the params from the previous step form.
$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$server = required_param('server_id', PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);

$tags = get_oppiaconfig($id, 'coursetags', '', $server);
$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', $server);
$sequencing = get_oppiaconfig($id, 'coursesequencing', '', $server);
$keep_html = get_oppiaconfig($id, 'keep_html', '', $server);
$video_overlay = get_oppiaconfig($id, 'video_overlay', '', $server);
$defaultlang = get_oppiaconfig($id, 'defaultlang', $CFG->block_oppia_mobile_export_defaultlang, $server);
$thumb_height = get_oppiaconfig($id, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, $server);
$thumb_width = get_oppiaconfig($id, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, $server);
$section_height = get_oppiaconfig($id, 'section_height', $CFG->block_oppia_mobile_export_section_icon_height, $server);
$section_width = get_oppiaconfig($id, 'section_width', $CFG->block_oppia_mobile_export_section_icon_width, $server);

$local_media_files = array();
$course = $DB->get_record('course', array('id' => $id));
// We clean the shortname of the course (the change doesn't get saved in Moodle).
$course->shortname = cleanShortname($course->shortname);

$is_draft = ($course_export_status == 'draft');
if ($is_draft) {
    $course->shortname = $course->shortname."-draft";
}

$PAGE->set_url(PLUGINPATH.'export/step4.php', array('id' => $id));
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

echo "<h2>".get_string('export_step4_title', PLUGINNAME)."</h2>";

$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid' => $USER->id, 'id' => $server));
if (!$server_connection && $server != "default") {
    echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}
if ($server == "default") {
    $server_connection = new stdClass();
    $server_connection->url = $CFG->block_oppia_mobile_export_default_server;
}
$api_helper = new ApiHelper();
$api_helper->fetch_server_info($server_connection->url);

echo '<p>';
if ($api_helper->version == null || $api_helper->version == '') {
    echo '<span class="export-error">'. get_string('export_server_error', PLUGINNAME).OPPIA_HTML_BR;
    add_publishing_log($server_connection->url, $USER->id, $id, "server_unavailable", "Unable to get server info");
}
else{
    echo $OUTPUT->render_from_template(
        PLUGINNAME.'/server_info', array('server_info' => $api_helper)
    );
}

// Make course dir etc for output.
$dataroot = $CFG->dataroot . "/";
deleteDir($dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp");
deleteDir($dataroot.OPPIA_OUTPUT_DIR.$USER->id);
if (!is_dir($dataroot."output")) {
    if (!mkdir($dataroot."output", 0777)) {
        echo "<h3>Failed to create the output directory, please check your server permissions to allow the webserver user to create the output directory under " . __DIR__ . "</h3>";
        die;
    }
}
mkdir($dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/", 0777, true);
$course_root = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/".strtolower($course->shortname);
mkdir($course_root, 0777);
mkdir($course_root."/images", 0777);
$fh = fopen($course_root."/images/.nomedia", 'w');
fclose($fh);
mkdir($course_root."/resources", 0777);
$fh = fopen($course_root."/resources/.nomedia", 'w');
fclose($fh);

mkdir($course_root."/style_resources", 0777);
mkdir($course_root."/js", 0777);

$PAGE->set_context($context);
context_helper::preload_course($id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$plugin_version = get_config(PLUGINNAME, 'version');
$versionid = date("YmdHis");
$xmldoc = new DOMDocument( "1.0", "UTF-8" );
$root = $xmldoc->appendChild($xmldoc->createElement("module"));
$meta = $root->appendChild($xmldoc->createElement("meta"));
$meta->appendChild($xmldoc->createElement("versionid", $versionid));
$meta->appendChild($xmldoc->createElement("priority", $priority));

$meta->appendChild($xmldoc->createElement("server", $server_connection->url));
$meta->appendChild($xmldoc->createElement("sequencing", $sequencing));
$meta->appendChild($xmldoc->createElement("tags", $tags));
$meta->appendChild($xmldoc->createElement("exportversion", $plugin_version));

add_publishing_log($server_connection->url, $USER->id, $id, "export_start", "Export process starting");

$title = extractLangs($course->fullname);
if (is_array($title) && count($title) > 0) {
    foreach ($title as $l => $t) {
        $temp = $xmldoc->createElement("title");
        $temp->appendChild($xmldoc->createCDATASection(strip_tags($t)));
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($l));
        $meta->appendChild($temp);
    }
} else {
    $temp = $xmldoc->createElement("title");
    $temp->appendChild($xmldoc->createCDATASection(strip_tags($course->fullname)));
    $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($defaultlang));
    $meta->appendChild($temp);
}
$temp = $xmldoc->createElement("shortname");
$temp->appendChild($xmldoc->createCDATASection(strtolower($course->shortname)));
$meta->appendChild($temp);

$summary = extractLangs($course->summary);
if (is_array($summary) && count($summary) > 0) {
    foreach ($summary as $l => $s) {
        $temp = $xmldoc->createElement("description");
        $temp->appendChild($xmldoc->createCDATASection(trim(strip_tags($s))));
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($l));
        $meta->appendChild($temp);
    }
} else {
    $temp = $xmldoc->createElement("description");
    $temp->appendChild($xmldoc->createCDATASection(trim(strip_tags($course->summary))));
    $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($defaultlang));
    $meta->appendChild($temp);
}

/*-------Get course info pages/about etc----------------------*/
$thissection = $sections[0];
$sectionmods = explode(",", $thissection->sequence);
$i = 1;
foreach ($sectionmods as $modnumber) {

    if (empty($modinfo->sections[0])) {
        continue;
    }
    $mod = $mods[$modnumber];

    if ($mod->modname == 'page' && $mod->visible == 1) {
        echo "<p>".$mod->name."</p>";
        $page = new MobileActivityPage(array(
            'id' => $mod->id,
            'courseroot' => $course_root,
            'server_id' => $server,
            'section' => 0,
            'keep_html' => $keep_html,
            'video_overlay' => $video_overlay,
            'local_media_files' => $local_media_files,
        ));
        $page->process();
        $page->get_xml($mod, $i, $meta, $xmldoc, false);
    }
    if ($mod->modname == 'quiz' && $mod->visible == 1) {
        echo "<p>".$mod->name."</p>";

        $randomselect = get_oppiaconfig($id, 'randomselect', 0, $server);
        $passthreshold = get_oppiaconfig($id, 'passthreshold', 0, $server);
        $showfeedback = get_oppiaconfig($id, 'showfeedback', 2, $server);
        $maxattempts = get_oppiaconfig($id, 'maxattempts', 'unlimited', $server);

        $quiz = new MobileActivityQuiz(array(
            'id' => $mod->id,
            'courseroot' => $course_root,
            'section' => 0,
            'server_id' => $server,
            'course_id' => $id,
            'shortname' => $course->shortname,
            'summary' => 'Pre-test',
            'courseversion' => $versionid,
            'keep_html' => $keep_html,
            'config_array' => array(
                'randomselect' => $randomselect,
                'showfeedback' => $showfeedback,
                'passthreshold' => $passthreshold,
                'maxattempts' => $maxattempts
            )
        ));

        $quiz->preprocess();
        if ($quiz->get_is_valid()) {
            $quiz->process();
            $quiz->get_xml($mod, $i, $meta, $xmldoc, true);
        }
    }
    if ($mod->modname == 'feedback' && $mod->visible == 1) {
        echo $mod->name.OPPIA_HTML_BR;

        $feedback = new MobileActivityFeedback(array(
            'id' => $mod->id,
            'courseroot' => $course_root,
            'section' => 0,
            'server_id' => $server,
            'course_id' => $id,
            'shortname' => $course->shortname,
            'courseversion' => $versionid,
            'keep_html' => $keep_html,
            'config_array' => array(
                'showfeedback' => false,
                'passthreshold' => 0,
                'maxattempts' => 'unlimited'
            )
        ));

        $feedback->preprocess();
        if ($feedback->get_is_valid()) {
            $feedback->process();
            $feedback->get_xml($mod, $i, $meta, $xmldoc, true);
        } else {
            echo get_string('error_feedback_no_questions', PLUGINNAME).OPPIA_HTML_BR;
        }
    }
    $i++;
}

/*-----------------------------*/

// Get module image (from course summary).
$filename = extractImageFile($course->summary,
                            'course',
                            'summary',
                            '0',
                            $context->id,
                            $course_root, 0);

if ($filename) {
    $resized_filename = resizeImage($course_root."/".$filename,
        $course_root."/images/".$course->id.'_'.$context->id,
                        $CFG->block_oppia_mobile_export_course_icon_width,
                        $CFG->block_oppia_mobile_export_course_icon_height,
                        true);
    unlink($course_root."/".$filename) || die('Unable to delete the file');
    $temp = $xmldoc->createElement("image");
    $temp->appendChild($xmldoc->createAttribute("filename"))->appendChild($xmldoc->createTextNode("/images/".$resized_filename));
    $meta->appendChild($temp);
}

$structure = $xmldoc->createElement("structure");

echo "<h3>".get_string('export_sections_start', PLUGINNAME)."</h3>";

$processor = new ActivityProcessor(array(
            'course_root' => $course_root,
            'server_id' => $server,
            'course_id' => $id,
            'course_shortname' => $course->shortname,
            'versionid' => $versionid,
            'keep_html' => $keep_html,
            'video_overlay' => $video_overlay,
            'local_media_files' => $local_media_files
));

$sect_orderno = 1;
$activity_summaries = array();
foreach ($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest.
    if ($sect->section == 0) {
        continue;
    }
    $sectionmods = explode(",", $sect->sequence);
    $sectTitle = get_section_title($sect);

    if (count($sectionmods) > 0) {
        echo '<hr>';
        echo '<div class="oppia_export_section">';
        echo "<h4>".get_string('export_section_title', PLUGINNAME, $sectTitle['display_title'])."</h4>";

        $section = $xmldoc->createElement("section");
        $section->appendChild($xmldoc->createAttribute("order"))->appendChild($xmldoc->createTextNode($sect_orderno));
        if (!$sectTitle['using_default'] && is_array($sectTitle['title']) && count($sectTitle['title']) > 0) {
            foreach ($sectTitle['title'] as $l => $t) {
                $temp = $xmldoc->createElement("title");
                $temp->appendChild($xmldoc->createCDATASection(strip_tags($t)));
                $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($l));
                $section->appendChild($temp);
            }
        } else {
            $temp = $xmldoc->createElement("title");
            $temp->appendChild($xmldoc->createCDATASection(strip_tags($sectTitle['title'])));
            $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($defaultlang));
            $section->appendChild($temp);
        }

        $sect_password =  optional_param('section_'.$sect->id.'_password', '', PARAM_TEXT);
        if ($sect_password != '') {
            echo '<span class="export-results warning">'. get_string('section_password_added', PLUGINNAME) .'</span>'.OPPIA_HTML_BR;
            $section->appendChild($xmldoc->createAttribute("password"))->appendChild($xmldoc->createTextNode($sect_password));
            // We store the section's password for future exports.
            add_or_update_oppiaconfig($sect->id, 'password', $sect_password, $server);
        }
        else{
            // If the password was empty, we remove possible previous ones.
            remove_oppiaconfig_if_exists($sect->id, 'password', $server);
        }

        // Get section image (from summary).
        $filename = extractImageFile($sect->summary,
                                    'course',
                                    'section',
                                    $sect->id,
                                    $context->id,
                                    $course_root, 0);

        if ($filename) {
            $resized_filename = resizeImage(
                $course_root."/".$filename,
                $course_root."/images/".$sect->id.'_'.$context->id,
                $section_width, $section_height, true);
            unlink($course_root."/".$filename) or die('Unable to delete the file');
            $temp = $xmldoc->createElement("image");
            $temp->appendChild($xmldoc->createAttribute("filename"))->appendChild($xmldoc->createTextNode("/images/".$resized_filename));
            $section->appendChild($temp);
        }

        $act_orderno = 1;
        $activities = $xmldoc->createElement("activities");
        $processor->set_current_section($sect_orderno);
        foreach ($sectionmods as $modnumber) {

            if ($modnumber == "" || $modnumber === false) {
                continue;
            }
            $mod = $mods[$modnumber];

            if ($mod->visible != 1) {
                continue;
            }

            echo '<div class="step"><strong>' . format_string($mod->name) . '</strong>'.OPPIA_HTML_BR;
            $password =  optional_param('mod_'.$mod->id.'_password', '', PARAM_TEXT);
            $activity = $processor->process_activity($mod, $sect, $act_orderno, $activities, $xmldoc, $password);
            $activity_summaries[$activity->id] = array(
                'digest' => $activity->md5,
                'no_questions' => $activity->get_no_questions(),
            );
            if ($activity != null) {
                $act_orderno++;
                if ($activity->has_password()) {
                    echo '<span class="export-results info">'. get_string('activity_password_added', PLUGINNAME) .'</span>'.OPPIA_HTML_BR;
                    if ($password !== '') {
                        add_or_update_oppiaconfig($mod->id, 'password', $password, $server);
                    } else {
                        // If the password was empty, we remove possible previous ones.
                        remove_oppiaconfig_if_exists($mod->id, 'password', $server);
                    }
                }
            }
            echo '</div>';

            flush_buffers();
        }

        if ($act_orderno > 1) {
            $section->appendChild($activities);
            $structure->appendChild($section);
            $sect_orderno++;
        } else {
            echo get_string('error_section_no_activities', PLUGINNAME).OPPIA_HTML_BR;
        }

        echo '</div>';
        flush_buffers();
    }
}
echo '<hr><br>';
echo get_string('export_sections_finish', PLUGINNAME).OPPIA_HTML_BR;
$root->appendChild($structure);

// Add in the langs available here.
$langs = $xmldoc->createElement("langs");
foreach ($MOBILE_LANGS as $k => $v) {
    $temp = $xmldoc->createElement("lang", $k);
    $langs->appendChild($temp);
}
if (count($MOBILE_LANGS) == 0) {
    $temp = $xmldoc->createElement("lang", $defaultlang);
    $langs->appendChild($temp);
}
$meta->appendChild($langs);
$local_media_files = $processor->local_media_files;

// Add media includes.
if (count($MEDIA) > 0 || count($local_media_files) > 0) {
    $media = $xmldoc->createElement("media");
    foreach ($MEDIA as $m) {
        $temp = $xmldoc->createElement("file");
        foreach ($m as $var => $value) {
            $temp->appendChild($xmldoc->createAttribute($var))->appendChild($xmldoc->createTextNode($value));
        }
        $media->appendChild($temp);
    }
    foreach ($local_media_files as $m) {
        $temp = $xmldoc->createElement("file");
        foreach ($m as $var => $value) {
            $temp->appendChild($xmldoc->createAttribute($var))->appendChild($xmldoc->createTextNode($value));
        }
        $media->appendChild($temp);
    }

    $root->appendChild($media);
}
$xmldoc->preserveWhiteSpace = false;
$xmldoc->formatOutput = true;
$xmldoc->save($course_root.OPPIA_MODULE_XML);


if ($sect_orderno <= 1) {
    echo '<h3>'.get_string('error_exporting', PLUGINNAME).'</h3>';
    echo '<p>'.get_string('error_exporting_no_sections', PLUGINNAME).'</p>';
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->render_from_template(
    PLUGINNAME.'/export_step4_form',
    array(
        'id' => $id,
        'server_connection' => $server_connection->url,
        'media_files' => $local_media_files,
        'media_files_str' => json_encode($local_media_files),
        'server_id' => $server,
        'stylesheet' => $stylesheet,
        'coursetags' => $tags,
        'course_export_status' => $course_export_status,
        'course_root' => $course_root,
        'activity_summaries' => json_encode($activity_summaries),
        'wwwroot' => $CFG->wwwroot));

echo $OUTPUT->footer();
