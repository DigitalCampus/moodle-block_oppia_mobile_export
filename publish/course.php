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

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;
$dataroot = $CFG->dataroot . "/";

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');

require_once($CFG->libdir.'/componentlib.class.php');
require_once(dirname(__FILE__) . '/../migrations/populate_digests.php');

$id = required_param('id', PARAM_INT);
$file = required_param('file', PARAM_TEXT);
$tags = required_param('tags', PARAM_TEXT);
$server = required_param('serverid', PARAM_TEXT);
$username = required_param('username', PARAM_TEXT);
$password = required_param('password', PARAM_TEXT);
$course_status = required_param('courseexportstatus', PARAM_TEXT);
$digeststopreserve = required_param('digeststopreserve', PARAM_TEXT);

$course = $DB->get_record('course', array('id' => $id));

$PAGE->set_url(PLUGINPATH.'publish/course.php', array('id' => $id));
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

$serverconnection = $DB->get_record(OPPIA_SERVER_TABLE, array('id' => $server));

add_or_update_oppiaconfig($id, 'is_draft', $isdraft);
add_publishing_log($serverconnection->url, $USER->id, $id, "api_publish_start", "API publish process started");

echo $OUTPUT->header();

flush_buffers();

echo "<h2>";
if ($course_status == 'draft') {
    echo get_string('publishing_header_draft', PLUGINNAME);
} else {
    echo get_string('publishing_header_live', PLUGINNAME);
}
echo "</h2>";

flush_buffers();

if (trim($username) == '') {
    echo "<p>".get_string('publish_error_username', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}

if (trim($password) == '') {
    echo "<p>".get_string('publish_error_password', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}
if (trim($tags) == '') {
    echo "<p>".get_string('publish_error_tags', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}

if (!$serverconnection && $server != "default") {
    echo "<p>".get_string('server_not_owner', PLUGINNAME)."</p>";
    echo $OUTPUT->footer();
    die();
}
if ($server == "default") {
    $serverconnection = new stdClass();
    $serverconnection->url = $CFG->block_oppia_mobile_export_default_server;
}

if (substr($serverconnection->url, -strlen('/')) !== '/') {
    $serverconnection->url .= '/';
}

if ($course_status == 'draft') {
    $isdraft = "true";
} else {
    $isdraft = "false";
}

$filepath = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/".$file;
$curlfile = new CurlFile($filepath, 'application/zip', $file);

$post = array(
        'username' => $username,
        'password' => $password,
        'tags' => $tags,
        'is_draft' => $isdraft,
        'course_file' => $curlfile);

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $serverconnection->url."api/publish/" );
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_VERBOSE, true);

$result = curl_exec($curl);
$httpstatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close ($curl);

add_publishing_log($serverconnection->url, $USER->id, $id, "api_publishing_user", $username);
add_publishing_log($serverconnection->url, $USER->id, $id, "api_file_posted", $file);

switch ($httpstatus) {
    case "405":
        $msgtext = get_string('publish_message_405', PLUGINNAME);
        show_and_log_message($serverconnection, $id, $msgtext, false, "api_publish_invalid_request", false);
        break;
    case "400":
        $msgtext = get_string('publish_message_400', PLUGINNAME);
        show_and_log_message($serverconnection, $id, $msgtext, false, "api_publish_bad_request", false);
        break;
    case "401":
        $msgtext = get_string('publish_message_401', PLUGINNAME);
        show_and_log_message($serverconnection, $id, $msgtext, false, "api_publish_unauthorised", false);
        break;
    case "500":
        $msgtext = get_string('publish_message_500', PLUGINNAME);
        show_and_log_message($serverconnection, $id, $msgtext, false, "api_publish_server_error", false);
        break;
    case "201":
        $msgtext = get_string('publish_message_201', PLUGINNAME);
        show_and_log_message($serverconnection, $id, $msgtext, false, "api_publish_success", false);
        populate_digests_for_course($course, $course->id, $server, json_decode($digeststopreserve, true), false);
        delete_dir($dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp");
        break;
    default:
}
$jsonresponse = json_decode($result, true);

if (is_null($jsonresponse)) {
    echo $result;
} else {
    if (array_key_exists('message', $jsonresponse)) {
        show_and_log_message($serverconnection, $id, $jsonresponse['message'], false, "api_publish_response", false);
    }
    if (array_key_exists('messages', $jsonresponse)) {
        $messages = $jsonresponse['messages'];
        foreach ($messages as $msg) {
            show_and_log_message($serverconnection, $id, $msg['message'], $msg['tags'], "api_publish_bad_request", true);
        }
    }
    if (array_key_exists('errors', $jsonresponse)) {
        $errors = $jsonresponse['errors'];
        foreach ($errors as $err) {
            show_and_log_message($serverconnection, $id, $err, 'warning', "api_publish_response_message", true);
        }
    }
}


add_publishing_log($serverconnection->url, $USER->id, $id, "api_publish_end", "API publish process ended");

echo $OUTPUT->footer();

// Function to show on screen the message and save it in the publishing log.
function show_and_log_message($serverconnection, $courseid, $message, $tags, $logaction, $showdialog) {
    global $USER;
    echo '<div class="' . ($showdialog ? 'export-results' : '') . ' ' .$tags.'">'.$message.'</div>';
    add_publishing_log($serverconnection->url, $USER->id, $courseid, $logaction, ($tags ? $tags.':' : '').$message);
}
