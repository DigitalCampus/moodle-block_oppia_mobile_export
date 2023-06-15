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
$server = required_param('serverid', PARAM_TEXT);
$courseexportstatus = required_param('courseexportstatus', PARAM_TEXT);

$tags = get_oppiaconfig($id, 'coursetags', '', true, $server);
$priority = (int) get_oppiaconfig($id, 'coursepriority', '0', true, $server);
$sequencing = get_oppiaconfig($id, 'coursesequencing', '', true, $server);
$keephtml = get_oppiaconfig($id, 'keephtml', '', true, $server);
$videooverlay = get_oppiaconfig($id, 'videooverlay', '', true, $server);
$DEFAULTLANG = get_oppiaconfig($id, 'default_lang', $CFG->block_oppia_mobile_export_default_lang, true, $server);
$thumbheight = get_oppiaconfig($id, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, true, $server);
$thumbwidth = get_oppiaconfig($id, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, true, $server);
$sectionheight = get_oppiaconfig($id, 'section_height', $CFG->block_oppia_mobile_export_section_icon_height, true, $server);
$sectionwidth = get_oppiaconfig($id, 'section_width', $CFG->block_oppia_mobile_export_section_icon_width, true, $server);

$localmediafiles = array();
$course = $DB->get_record('course', array('id' => $id));
// We clean the shortname of the course (the change doesn't get saved in Moodle).
$course->shortname = clean_shortname($course->shortname);

$isdraft = ($courseexportstatus == 'draft');
if ($isdraft) {
    $course->shortname = $course->shortname."-draft";
}

$PAGE->set_url(PLUGINPATH.'export/step4.php', array('id' => $id));
context_helper::preload_course($id);
$context = context_course::instance($course->id);
if (!$context) {
    throw new moodle_exception('nocontext');
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

$globals['mobilelangs'] = array();

global $MEDIA;
$MEDIA = array();

echo "<h2>".get_string('export_step4_title', PLUGINNAME)."</h2>";

$serverconnection = $DB->get_record(OPPIA_SERVER_TABLE, array('id' => $server));
if (!$serverconnection && $server != "default") {
    echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}
if ($server == "default") {
    $serverconnection = new stdClass();
    $serverconnection->url = $CFG->block_oppia_mobile_export_default_server;
}
$apihelper = new ApiHelper();
$apihelper->fetch_server_info($serverconnection->url);

echo '<p>';
if ($apihelper->version == null || $apihelper->version == '') {
    echo '<span class="export-error">'. get_string('export_server_error', PLUGINNAME).OPPIA_HTML_BR;
    add_publishing_log($serverconnection->url, $USER->id, $id, "server_unavailable", "Unable to get server info");
} else {
    echo $OUTPUT->render_from_template(
        PLUGINNAME.'/server_info', array('server_info' => $apihelper)
    );
}

// Make course dir etc for output.
$dataroot = $CFG->dataroot . "/";
delete_dir($dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp");
delete_dir($dataroot.OPPIA_OUTPUT_DIR.$USER->id);
if (!is_dir($dataroot."output")) {
    if (!mkdir($dataroot."output", 0777)) {
        echo "<h3>Failed to create the output directory, please check your server permissions to allow the webserver user to create the output directory under " . __DIR__ . "</h3>";
        die;
    }
}
mkdir($dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/", 0777, true);
$courseroot = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp/".strtolower($course->shortname);
mkdir($courseroot, 0777);
mkdir($courseroot."/images", 0777);
$fh = fopen($courseroot."/images/.nomedia", 'w');
fclose($fh);
mkdir($courseroot."/resources", 0777);
$fh = fopen($courseroot."/resources/.nomedia", 'w');
fclose($fh);

mkdir($courseroot."/style_resources", 0777);
mkdir($courseroot."/js", 0777);

$PAGE->set_context($context);
context_helper::preload_course($id);
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$mods = $modinfo->get_cms();

$pluginversion = get_config(PLUGINNAME, 'version');
$versionid = date("YmdHis");
$xmldoc = new DOMDocument( "1.0", "UTF-8" );
$root = $xmldoc->appendChild($xmldoc->createElement("module"));
$meta = $root->appendChild($xmldoc->createElement("meta"));
$meta->appendChild($xmldoc->createElement("versionid", $versionid));
$meta->appendChild($xmldoc->createElement("priority", $priority));

$meta->appendChild($xmldoc->createElement("server", $serverconnection->url));
$meta->appendChild($xmldoc->createElement("sequencing", $sequencing));
$meta->appendChild($xmldoc->createElement("tags", $tags));
$meta->appendChild($xmldoc->createElement("exportversion", $pluginversion));

add_publishing_log($serverconnection->url, $USER->id, $id, "export_start", "Export process starting");

$title = extract_langs($course->fullname, false, false, false);
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
    $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
    $meta->appendChild($temp);
}
$temp = $xmldoc->createElement("shortname");
$temp->appendChild($xmldoc->createCDATASection(strtolower($course->shortname)));
$meta->appendChild($temp);

$summary = extract_langs($course->summary, false, false, false);
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
    $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
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
            'courseroot' => $courseroot,
            'serverid' => $server,
            'section' => 0,
            'keephtml' => $keephtml,
            'videooverlay' => $videooverlay,
            'localmediafiles' => $localmediafiles,
        ));
        $page->process();
        $page->get_xml($mod, $i, $meta, $xmldoc, false);
    }
    if ($mod->modname == 'quiz' && $mod->visible == 1) {
        echo "<p>".$mod->name."</p>";

        $randomselect = get_oppiaconfig($id, 'randomselect', 0, true, $server);
        $passthreshold = get_oppiaconfig($id, 'passthreshold', 0, true, $server);
        $showfeedback = get_oppiaconfig($id, 'showfeedback', 2, true, $server);
        $maxattempts = get_oppiaconfig($id, 'maxattempts', 'unlimited', true, $server);

        $quiz = new MobileActivityQuiz(array(
            'id' => $mod->id,
            'courseroot' => $courseroot,
            'section' => 0,
            'serverid' => $server,
            'courseid' => $id,
            'shortname' => $course->shortname,
            'summary' => 'Pre-test',
            'courseversion' => $versionid,
            'keephtml' => $keephtml,
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
            'courseroot' => $courseroot,
            'section' => 0,
            'serverid' => $server,
            'courseid' => $id,
            'shortname' => $course->shortname,
            'courseversion' => $versionid,
            'keephtml' => $keephtml,
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
$filename = extract_image_file($course->summary,
                            'course',
                            'summary',
                            '0',
                            $context->id,
                            $courseroot, 0);

if ($filename) {
    $resizedfilename = resize_image($courseroot."/".$filename,
        $courseroot."/images/".$course->id.'_'.$context->id,
                        $CFG->block_oppia_mobile_export_course_icon_width,
                        $CFG->block_oppia_mobile_export_course_icon_height,
                        true);
    unlink($courseroot."/".$filename) || die('Unable to delete the file');
    $temp = $xmldoc->createElement("image");
    $temp->appendChild($xmldoc->createAttribute("filename"))->appendChild($xmldoc->createTextNode("/images/".$resizedfilename));
    $meta->appendChild($temp);
}

$structure = $xmldoc->createElement("structure");

echo "<h3>".get_string('export_sections_start', PLUGINNAME)."</h3>";

$processor = new ActivityProcessor(array(
            'courseroot' => $courseroot,
            'serverid' => $server,
            'courseid' => $id,
            'courseshortname' => $course->shortname,
            'versionid' => $versionid,
            'keephtml' => $keephtml,
            'videooverlay' => $videooverlay,
            'localmediafiles' => $localmediafiles
));

$sectorderno = 1;
$activitysummaries = array();
foreach ($sections as $sect) {
    flush_buffers();
    // We avoid the topic0 as is not a section as the rest.
    if ($sect->section == 0) {
        continue;
    }
    $sectionmods = explode(",", $sect->sequence);
    $secttitle = get_section_title($sect);

    if (count($sectionmods) > 0) {
        echo '<hr>';
        echo '<div class="oppia_export_section">';
        echo "<h4>".get_string('export_section_title', PLUGINNAME, $secttitle['display_title'])."</h4>";

        $section = $xmldoc->createElement("section");
        $section->appendChild($xmldoc->createAttribute("order"))->appendChild($xmldoc->createTextNode($sectorderno));
        if (!$secttitle['using_default'] && is_array($secttitle['title']) && count($secttitle['title']) > 0) {
            foreach ($secttitle['title'] as $l => $t) {
                $temp = $xmldoc->createElement("title");
                $temp->appendChild($xmldoc->createCDATASection(strip_tags($t)));
                $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($l));
                $section->appendChild($temp);
            }
        } else {
            $temp = $xmldoc->createElement("title");
            $temp->appendChild($xmldoc->createCDATASection(strip_tags($secttitle['title'])));
            $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
            $section->appendChild($temp);
        }

        $sectpassword = optional_param('section_'.$sect->id.'_password', '', PARAM_TEXT);
        if ($sectpassword != '') {
            echo '<span class="export-results warning">'. get_string('section_password_added', PLUGINNAME) .'</span>'.OPPIA_HTML_BR;
            $section->appendChild($xmldoc->createAttribute("password"))->appendChild($xmldoc->createTextNode($sectpassword));
            // We store the section's password for future exports.
            add_or_update_oppiaconfig($sect->id, 'password', $sectpassword, $server);
        } else {
            // If the password was empty, we remove possible previous ones.
            remove_oppiaconfig_if_exists($sect->id, 'password', $server);
        }

        // Get section image (from summary).
        $filename = extract_image_file($sect->summary,
                                    'course',
                                    'section',
                                    $sect->id,
                                    $context->id,
                                    $courseroot, 0);

        if ($filename) {
            $resizedfilename = resize_image(
                $courseroot."/".$filename,
                $courseroot."/images/".$sect->id.'_'.$context->id,
                $sectionwidth, $sectionheight, true);
            unlink($courseroot."/".$filename) || die('Unable to delete the file');
            $temp = $xmldoc->createElement("image");
            $temp->appendChild($xmldoc->createAttribute("filename"))->appendChild($xmldoc->createTextNode("/images/".$resizedfilename));
            $section->appendChild($temp);
        }

        $actorderno = 1;
        $activities = $xmldoc->createElement("activities");
        $processor->set_current_section($sectorderno);
        foreach ($sectionmods as $modnumber) {

            if ($modnumber == "" || $modnumber === false) {
                continue;
            }
            $mod = $mods[$modnumber];

            if ($mod->visible != 1) {
                continue;
            }

            echo '<div class="step"><strong>' . format_string($mod->name) . '</strong>'.OPPIA_HTML_BR;
            $password = optional_param('mod_'.$mod->id.'_password', '', PARAM_TEXT);
            $activity = $processor->process_activity($mod, $sect, $actorderno, $activities, $xmldoc, $password);
            if ($activity != null) {
                $actorderno++;
                $activitysummaries[$activity->id] = array(
                    'digest' => $activity->md5,
                    'no_questions' => $activity->get_no_questions(),
                );
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

        if ($actorderno > 1) {
            $section->appendChild($activities);
            $structure->appendChild($section);
            $sectorderno++;
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
foreach ($globals['mobilelangs'] as $k => $v) {
    $temp = $xmldoc->createElement("lang", $k);
    $langs->appendChild($temp);
}
if (count($globals['mobilelangs']) == 0) {
    $temp = $xmldoc->createElement("lang", $DEFAULTLANG);
    $langs->appendChild($temp);
}
$meta->appendChild($langs);
$localmediafiles = $processor->localmediafiles;

// Add media includes.
if (count($MEDIA) > 0 || count($localmediafiles) > 0) {
    $media = $xmldoc->createElement("media");
    foreach ($MEDIA as $m) {
        $temp = $xmldoc->createElement("file");
        foreach ($m as $var => $value) {
            $temp->appendChild($xmldoc->createAttribute($var))->appendChild($xmldoc->createTextNode($value));
        }
        $media->appendChild($temp);
    }
    foreach ($localmediafiles as $m) {
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
$xmldoc->save($courseroot.OPPIA_MODULE_XML);


if ($sectorderno <= 1) {
    echo '<h3>'.get_string('error_exporting', PLUGINNAME).'</h3>';
    echo '<p>'.get_string('error_exporting_no_sections', PLUGINNAME).'</p>';
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->render_from_template(
    PLUGINNAME.'/export_step4_form',
    array(
        'id' => $id,
        'serverconnection' => $serverconnection->url,
        'mediafiles' => $localmediafiles,
        'media_files_str' => json_encode($localmediafiles),
        'serverid' => $server,
        'stylesheet' => $stylesheet,
        'coursetags' => $tags,
        'courseexportstatus' => $courseexportstatus,
        'courseroot' => $courseroot,
        'activity_summaries' => json_encode($activitysummaries),
        'wwwroot' => $CFG->wwwroot));

echo $OUTPUT->footer();
