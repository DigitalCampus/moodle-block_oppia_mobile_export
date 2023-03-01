<?php 
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
$server = required_param('server_id',PARAM_TEXT);
$course_export_status = required_param('course_export_status', PARAM_TEXT);
$course_root = required_param('course_root', PARAM_TEXT);
$is_draft = ($course_export_status == 'draft');
$course = $DB->get_record('course', array('id'=>$id));

$PAGE->set_url(PLUGINPATH.'export/step6.php', array('id' => $id));
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

echo "<h2>".get_string('export_step6_title', PLUGINNAME)."</h2>";

$server_connection = $DB->get_record(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id,'id'=>$server));
if(!$server_connection && $server != "default"){
    echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}
if ($server == "default"){
    $server_connection = new stdClass();
    $server_connection->url = $CFG->block_oppia_mobile_export_default_server;
}

echo '<div class="oppia_export_section">';

echo '<p class="step">'. get_string('export_xml_valid_start', PLUGINNAME);

if (!file_exists($course_root.OPPIA_MODULE_XML)){
    echo "<p>".get_string('error_xml_notfound', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}


libxml_use_internal_errors(true);
$xml = new DOMDocument();
$xml->load($course_root.OPPIA_MODULE_XML);

// We update the local media URLs from the results of the previous step
foreach ($xml->getElementsByTagName('file') as $mediafile) {
    if ($mediafile->hasAttribute('download_url')){
        // If it already has the url set, we don't need to do anything
        continue;
    }
    if ($mediafile->hasAttribute('moodlefile')){
        // We remove the moodlefile attribute (it's only a helper to publish media)
        $mediafile->removeAttribute('moodlefile');
    }

    $digest = $mediafile->getAttribute('digest');
    $medialength = optional_param($digest.'_length', null, PARAM_INT);
    $url = optional_param($digest, null, PARAM_TEXT);
    if ($url !== null){
        $mediafile->setAttribute('download_url', $url);
        $mediafile->setAttribute('length', $medialength);
    }
}


$activities = array();
$duplicated = array();
$digests_to_preserve = array();
// Check that we don't have duplicated digests in the course
foreach ($xml->getElementsByTagName('activity') as $activity) {
    $digest = $activity->getAttribute('digest');
    // Get digest from previous step if the 'Preserve ID' option was selected
    $preserve_digest = optional_param('digest_'.$digest, $digest, PARAM_TEXT);
    $digests_to_preserve[$digest] = $preserve_digest;
    $activity->setAttribute('digest', $preserve_digest);
    foreach ($activity->getElementsByTagName('content') as $content) {
        $content->firstChild->nodeValue = str_replace($digest, $preserve_digest, $content->nodeValue);
    }

    if (isset($activities[$digest])){
        foreach ($activity->childNodes as $node){
            if ($node->nodeName == "title"){
                $title = $node->nodeValue;
                break;
            }
        }
        array_push($duplicated, array(
            'title' => $title, 
            'digest' => $digest));
    }
    else{
        $activities[$digest] = true;
    }
}
if (count($duplicated) > 0){
    echo $OUTPUT->render_from_template(PLUGINNAME.'/export_error_duplicated_digest', array('duplicated'=>$duplicated));
    echo $OUTPUT->footer();
    die();
}

$versionid = $xml->getElementsByTagName('versionid')->item(0)->textContent;

if (!$xml->schemaValidate($pluginroot.'oppia-schema.xsd')) {
    print '<p><strong>'.get_string('error_xml_invalid', PLUGINNAME).'</strong></p>';
    libxml_display_errors();
    add_publishing_log($server_connection->url, $USER->id, $id, "error_xml_invalid", "Invalid course XML");
} else {
    echo get_string('export_xml_validated', PLUGINNAME)  . '</p>';
    echo '<p class="step">'. get_string('export_course_xml_created', PLUGINNAME)  . '</p>';
    
    $xml->preserveWhiteSpace = false;
    $xml->formatOutput = true;
    $xml->save($course_root.OPPIA_MODULE_XML);

    echo '<p class="step">'. get_string('export_style_start', PLUGINNAME) . ' - ' . $stylesheet. '</p>';

    $styles = getCompiledCSSTheme($pluginroot, $stylesheet);
    if (!file_put_contents($course_root."/style.css", $styles)){
        echo "<p>".get_string('error_style_copy', PLUGINNAME)."</p>";
    }
    
    echo '<p class="step">'. get_string('export_style_resources', PLUGINNAME) . '</p>';
    
    $style_resources_dir = $course_root.COURSE_STYLES_RESOURCES_DIR;
    recurse_copy($pluginroot."styles/".COMMON_STYLES_RESOURCES_DIR, $style_resources_dir);
    recurse_copy($pluginroot."styles/".$stylesheet."-style-resources/", $style_resources_dir);

    recurse_copy($pluginroot."js/", $course_root."/js/");
    
    echo '<p class="step">'. get_string('export_export_complete', PLUGINNAME) . '</p>';
    $dir2zip = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp";

    $zipname = strtolower($course->shortname).'-'.$versionid.'.zip';
    $ziprelativepath = OPPIA_OUTPUT_DIR.$USER->id."/".$zipname;
    $outputpath = $dataroot.$ziprelativepath;
    Zip($dir2zip, $outputpath);

    add_or_update_oppiaconfig($id, 'stylesheet', $stylesheet, null);

     $filerecord = array(
         'contextid'=> $context->id,
         'component' => PLUGINNAME,
         'filearea' => COURSE_EXPORT_FILEAREA,
         'itemid' => $USER->id,
         'filepath' => '/',
         'filename' => $zipname
     );

    $fs = get_file_storage();
    $file = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'], $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);
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
    
    $form_values = array(
        'server_connection' =>$server_connection->url,
        'wwwroot' => $CFG->wwwroot,
        'server_id' => $server,
        'sesskey' => sesskey(),
        'course_id' => $COURSE->id,
        'file' => $zipname,
        'is_draft' => $is_draft,
        'tags' => $tags,
        'course_export_status' => $course_export_status,
        'export_url' => $url,
        'course_name' => strip_tags($course->fullname),
        'digests_to_preserve' => json_encode($digests_to_preserve)
    );

    echo $OUTPUT->render_from_template(PLUGINNAME.'/export_step6_form', $form_values);
    
    add_publishing_log($server_connection->url, $USER->id, $id,  "export_file_created", strtolower($course->shortname)."-".$versionid.".zip");
    add_publishing_log($server_connection->url, $USER->id, $id,  "export_end", "Export process completed");
}

echo $OUTPUT->footer();


?>
