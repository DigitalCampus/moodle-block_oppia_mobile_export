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
 * Step 6: Final step, XML validation and create the course package ready to publish
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$dataroot = $CFG->dataroot . "/";
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


$id = required_param('id', PARAM_INT);
$stylesheet = required_param('stylesheet', PARAM_TEXT);
$tags = required_param('coursetags', PARAM_TEXT);
$server = required_param('serverid', PARAM_TEXT);
$courseexportstatus = required_param('courseexportstatus', PARAM_TEXT);
$courseroot = required_param('courseroot', PARAM_TEXT);
$isdraft = ($courseexportstatus == 'draft');
$course = $DB->get_record('course', array('id' => $id));

$PAGE->set_url(PLUGINPATH.'export/step6.php', array('id' => $id));
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

echo "<h2>".get_string('export_step6_title', PLUGINNAME)."</h2>";

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

echo '<div class="oppia_export_section">';

echo '<p class="step">'. get_string('export_xml_valid_start', PLUGINNAME);

if (!file_exists($courseroot.OPPIA_MODULE_XML)) {
    echo "<p>".get_string('error_xml_notfound', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}


libxml_use_internal_errors(true);
$xml = new DOMDocument();
$xml->load($courseroot.OPPIA_MODULE_XML);

// We update the local media URLs from the results of the previous step.
foreach ($xml->getElementsByTagName('file') as $mediafile) {
    if ($mediafile->hasAttribute('download_url')) {
        // If it already has the url set, we don't need to do anything.
        continue;
    }
    if ($mediafile->hasAttribute('moodlefile')) {
        // We remove the moodlefile attribute (it's only a helper to publish media).
        $mediafile->removeAttribute('moodlefile');
    }

    $digest = $mediafile->getAttribute('digest');
    $medialength = optional_param($digest.'_length', null, PARAM_INT);
    $url = optional_param($digest, null, PARAM_TEXT);
    if ($url !== null) {
        $mediafile->setAttribute('download_url', $url);
        $mediafile->setAttribute('length', $medialength);
    }
}


$activities = array();
$duplicated = array();
$digeststopreserve = array();
// Check that we don't have duplicated digests in the course.
foreach ($xml->getElementsByTagName('activity') as $activity) {
    $digest = $activity->getAttribute('digest');
    // Get digest from previous step if the 'Preserve ID' option was selected.
    $preservedigest = optional_param('digest_'.$digest, $digest, PARAM_TEXT);
    $digeststopreserve[$digest] = $preservedigest;
    $activity->setAttribute('digest', $preservedigest);
    foreach ($activity->getElementsByTagName('content') as $content) {
        $content->firstChild->nodeValue = str_replace($digest, $preservedigest, $content->nodeValue);
    }

    if (isset($activities[$digest])) {
        foreach ($activity->childNodes as $node) {
            if ($node->nodeName == "title") {
                $title = $node->nodeValue;
                break;
            }
        }
        array_push($duplicated, array(
            'title' => $title,
            'digest' => $digest));
    } else {
        $activities[$digest] = true;
    }
}
if (count($duplicated) > 0) {
    echo $OUTPUT->render_from_template(PLUGINNAME.'/export_error_duplicated_digest', array('duplicated' => $duplicated));
    echo $OUTPUT->footer();
    die();
}

$versionid = $xml->getElementsByTagName('versionid')->item(0)->textContent;

if (!$xml->schemaValidate($pluginroot.'oppia-schema.xsd')) {
    print '<p><strong>'.get_string('error_xml_invalid', PLUGINNAME).'</strong></p>';
    libxml_display_errors();
    add_publishing_log($serverconnection->url, $USER->id, $id, "error_xml_invalid", "Invalid course XML");
} else {
    echo get_string('export_xml_validated', PLUGINNAME)  . '</p>';
    echo '<p class="step">'. get_string('export_course_xml_created', PLUGINNAME)  . '</p>';

    $xml->preserveWhiteSpace = false;
    $xml->formatOutput = true;
    $xml->save($courseroot.OPPIA_MODULE_XML);

    echo '<p class="step">'. get_string('export_style_start', PLUGINNAME) . ' - ' . $stylesheet. '</p>';

    $styles = get_compiled_css_theme($pluginroot, $stylesheet);
    if (!file_put_contents($courseroot."/style.css", $styles)) {
        echo "<p>".get_string('error_style_copy', PLUGINNAME)."</p>";
    }

    echo '<p class="step">'. get_string('export_style_resources', PLUGINNAME) . '</p>';

    $styleresourcesdir = $courseroot.COURSE_STYLES_RESOURCES_DIR;
    recurse_copy($pluginroot."styles/".COMMON_STYLES_RESOURCES_DIR, $styleresourcesdir);
    recurse_copy($pluginroot."styles/".$stylesheet."-style-resources/", $styleresourcesdir);

    recurse_copy($pluginroot."js/", $courseroot."/js/");

    echo '<p class="step">'. get_string('export_export_complete', PLUGINNAME) . '</p>';
    $dir2zip = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp";

    $zipname = strtolower($course->shortname).'-'.$versionid.'.zip';
    $ziprelativepath = OPPIA_OUTPUT_DIR.$USER->id."/".$zipname;
    $outputpath = $dataroot.$ziprelativepath;
    zip_oppia_course($dir2zip, $outputpath);

    add_or_update_oppiaconfig($id, 'stylesheet', $stylesheet, null);

    $filerecord = array(
        'contextid' => $context->id,
        'component' => PLUGINNAME,
        'filearea' => COURSE_EXPORT_FILEAREA,
        'itemid' => $USER->id,
        'filepath' => '/',
        'filename' => $zipname
    );

    $fs = get_file_storage();
    $file = $fs->get_file($filerecord['contextid'],
        $filerecord['component'],
        $filerecord['filearea'],
        $filerecord['itemid'],
        $filerecord['filepath'],
        $filerecord['filename']);
    if ($file) {
        $file->delete();
    }
    $file = $fs->create_file_from_pathname($filerecord, $outputpath);

    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        false // Do not force download of the file.
    );

    echo '<p class="step">'. get_string('export_export_compressed', PLUGINNAME) . '</p>';

    $formvalues = array(
        'serverconnection' => $serverconnection->url,
        'wwwroot' => $CFG->wwwroot,
        'serverid' => $server,
        'sesskey' => sesskey(),
        'courseid' => $COURSE->id,
        'file' => $zipname,
        'isdraft' => $isdraft,
        'tags' => $tags,
        'courseexportstatus' => $courseexportstatus,
        'export_url' => $url,
        'course_name' => strip_tags($course->fullname),
        'digeststopreserve' => json_encode($digeststopreserve)
    );

    echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step6_form', $formvalues);

    add_publishing_log($serverconnection->url,
        $USER->id,
        $id,
        "export_file_created",
        strtolower($course->shortname)."-".$versionid.".zip");
    add_publishing_log($serverconnection->url, $USER->id, $id,  "export_end", "Export process completed");
}

echo $OUTPUT->footer();
