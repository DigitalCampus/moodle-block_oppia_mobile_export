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
require_once($pluginroot . 'lib.php');

require_once($CFG->libdir.'/componentlib.class.php');

$dataroot = $CFG->dataroot . "/";
$tempmedia = $dataroot.OPPIA_OUTPUT_DIR.$USER->id."/temp_media/";
if (!file_exists($tempmedia)) {
    mkdir($tempmedia, 0777, true);
}

$server = required_param('server', PARAM_TEXT);
$digest = required_param('digest', PARAM_TEXT);
$serverbaseurl = get_server_url($server);
$mediainfo = get_media_info($serverbaseurl, $digest);

if ((!$mediainfo) && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $file = required_param('moodlefile', PARAM_TEXT);
    $username = required_param('username', PARAM_TEXT);
    $password = required_param('password', PARAM_TEXT);
    $mediainfo = publish_media($serverbaseurl, $file, $username, $password, $tempmedia);
}

header('Content-Type: application/json');
if (!$mediainfo) {
    echo json_encode(array('error' => 'not_valid_json'));
} else {
    echo json_encode($mediainfo);
}


function get_media_info($serverbaseurl, $digest) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $serverbaseurl."api/media/".$digest );
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    $httpstatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close ($curl);

    return process_response($httpstatus, $response);
}


function publish_media($serverbaseurl, $moodlefile, $username, $password, $tempmedia) {

    list($contextid, $component, $filearea, $itemid, $path, $filename) = explode(";", $moodlefile);
    $file = get_file_storage()->get_file($contextid, $component, $filearea, $itemid, $path, $filename);

    if (!$file) {
        http_response_code(500);
        return false;
    }

    $temppath = $tempmedia . $filename;
    $success = $file->copy_content_to($temppath);
    if (!$success) {
        http_response_code(500);
        return false;
    }
    $curlfile = new CurlFile($temppath, 'video/mp4', $filename);

    $post = array(
            'username' => $username,
            'password' => $password,
            'media_file' => $curlfile);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL,  $serverbaseurl."api/media/" );
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_VERBOSE, true);

    $response = curl_exec($curl);
    $httpstatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close ($curl);

    // We remove the temporary copied file.
    unlink($temppath);

    if ($httpstatus == 400) {
        /* If the server returned a 400 error, it is probably because the file already exists
         * so we try to fetch the info if it was already published in the server
         */
        $digest = required_param('digest', PARAM_TEXT);
        return get_media_info($serverbaseurl, $digest);
    } else {
        return process_response($httpstatus, $response);
    }
}

function process_response($httpstatus, $response) {

    $jsonresponse = json_decode($response, true);
    http_response_code($httpstatus);

    if (!$httpstatus || $httpstatus == 404 || is_null($jsonresponse)) {
        if (!$httpstatus || !$response) {
            http_response_code(400);
        }
        return false;
    }
    return get_mediainfo_from_response($jsonresponse);
}

function get_mediainfo_from_response($jsonresponse) {
    $mediainfo = array();
    if (array_key_exists('download_url', $jsonresponse)) {
        $mediainfo['download_url'] = $jsonresponse['download_url'];
    }
    if (array_key_exists('filesize', $jsonresponse)) {
        $mediainfo['filesize'] = $jsonresponse['filesize'];
    }
    if (array_key_exists('length', $jsonresponse)) {
        $mediainfo['length'] = $jsonresponse['length'];
    }
    return $mediainfo;
}

function get_server_url($server) {
    global $DB, $OUTPUT, $USER, $CFG;

    $serverconnection = $DB->get_record(OPPIA_SERVER_TABLE, array( 'id' => $server));
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

    return $serverconnection->url;
}
