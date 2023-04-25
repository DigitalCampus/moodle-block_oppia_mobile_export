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

define('CLI_SCRIPT', true);
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

global $CFG, $schema;

$pluginroot = $CFG->dirroot . PLUGINPATH;
$schema = $pluginroot.'oppia-schema.xsd';


require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/componentlib.class.php');

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'activity/processor.php');

if (!isset($argc) || ($argc < 2)) {
    echo 'Invalid call to the script, you must include a course XML file as first paremeter' . "\n";
    echo '   usage: php import_course_digests.php module.xml' . "\n";
    return -1;
}

$starttime = microtime(true);

fetch_module_types();

for ($i = 1; $i < $argc; $i++) {
    $filename = $argv[$i];
    parse_module_file($filename);
}

$timediff = microtime(true) - $starttime;
echo 'Completed in ' . $timediff . " seconds. \n";


function parse_module_file($filename) {
    global $schema;

    if (!file_exists($filename)) {
        echo get_string('error_xml_notfound', PLUGINNAME) . "\n";
        return -1;
    }

    echo "Parsing file " . $filename . "... \n";
    $file = fopen($filename, 'r');
    $contents = stream_get_contents($file);

    $xml = new DOMDocument();
    $xml->loadXML($contents);

    // Enable error handling.
    libxml_use_internal_errors(true);

    if (!$xml->schemaValidate($schema)) {
        libxml_display_errors();
        echo "The course XML file didn't validate against the current course schema. Trying to import activities anyway \n";
    }

    $course = get_course_by_shortname($xml);
    $server = get_and_validate_server($xml);
    if (!$course || !$server) {
        return -1;
    }

    foreach ($xml->getElementsByTagName('section') as $section) {
        foreach ($section->childNodes as $node) {
            if ($node->nodeName == "title") {
                $secttitle = strip_tags($node->nodeValue);
                break;
            }
        }
        $sectorderno = $section->getAttribute('order');
        echo "\nSection " . $sectorderno . ": " . $secttitle . " \n================== \n";
        $moodlesect = $sectorderno + 1; // Internally, Moodle starts in 1 with the Pre-Topics section.

        foreach ($section->getElementsByTagName('activity') as $activity) {
            $digest = $activity->getAttribute('digest');
            $modid = get_moodle_activity_modid($course->id, $moodlesect, $activity);

            if ($modid == false) {
                continue;
            }
            $prevdigest = update_activity_digest($course->id, $modid, $digest, $server);

            echo "   modid: [" . $modid . "]: " . $digest;
            if ($prevdigest == false) {
                echo " (did not exist previoulsy)\n";
            } else {
                echo ($prevdigest == $digest ? " (unchanged)" : (" (was ".$prevdigest).")") . "\n";
            }
        }
    }
}


function get_moodle_activity_modid($courseid, $sectorderno, $activity) {
    global $DB, $moduletypes;
    $title = $activity->getElementsByTagName('title')->item(0)->nodeValue;
    echo " > " . $title . "\n";

    $type = $activity->getAttribute('type');
    if ($type == 'quiz') {
        // For quizzes, we have the prop that links with its Moodle id.
        $contents = $activity->getElementsByTagName('content')->item(0)->nodeValue;
        $quiz = json_decode($contents);
        if (isset($quiz->props->moodle_quiz_id)) {
            $modid = $quiz->props->moodle_quiz_id;
            echo "   The quiz has 'moodle_quiz_id' property (" . $modid . "), fetching moodle activity... ";
            $cm = get_coursemodule_from_id('quiz', intval($modid));
            if ($cm == false) {
                 echo "Not found, are you sure the course was exported from this Moodle server?\n";
            } else {
                echo "Found!\n";
                return $modid;
            }
        }
    }

    // We get all the activities that match the title.
    $activities = $DB->get_records_select($type, "name LIKE '%{$title}%' and course=$courseid");
    $modid = false;
    $nummatches = 0;

    foreach ($activities as $act) {
        $actid = $act->id;
        // Check if the page belongs to the same section (in case of posible repeated activity titles like "Introduction").
        $mod = $DB->get_record('course_modules', array('instance' => $actid, 'module' => $moduletypes[$type], 'section' => $sectorderno));
        if ($mod !== false) {
            $modid = $mod->id;
            $nummatches++;
        }
    }

    if ($nummatches == 1) {
        return $modid;
    } else {
        if ($nummatches > 0) {
            echo "   There is more than one activity in the same section with this title, skipping... \n";
        }
        return false;
    }
}

function fetch_module_types() {
    global $DB, $moduletypes;

    $moduletypes = array(
        'page' => '',
        'resource' => '',
        'quiz' => '',
        'feedback' => '',
        'url' => ''
    );

    foreach ($moduletypes as $type => $value) {
        $modtype = $DB->get_record('modules', array('name' => $type));
        $moduletypes[$type] = $modtype->id;
    }
}

function get_course_by_shortname($xml) {
    global $DB;

    $shortname = $xml->getElementsByTagName('shortname')->item(0)->nodeValue;
    $course = $DB->get_record('course', array('shortname' => $shortname));

    if ($course) {
        return $course;
    }

    if (strrpos($shortname, '-draft')) {
        echo "The course was exported in \"draft\" mode, trying the lookup without the suffix \n";
        $shortname = substr($shortname, 0, strrpos($shortname, '-draft'));
        $course = $DB->get_record('course', array('shortname' => $shortname));
    }

    if (!$course) {
        echo "The course '" . $shortname . "' was not found in this Moodle server. \n";
    }

    return $course;
}

function get_and_validate_server($xml) {
    global $CFG, $DB;

    $serverurl = $xml->getElementsByTagName('server')->item(0)->nodeValue;
    if ($serverurl == $CFG->block_oppia_mobile_export_default_server) {
        echo "Using default server (" . $serverurl . ")\n";
        return 'default';
    }

    $server = $DB->get_record(OPPIA_SERVER_TABLE, array('url' => $serverurl));
    if ($server) {
        echo "Server (" . $serverurl . ") found in available servers\n";
        return $server->id;
    }

    echo "The server (" . $serverurl . ") the course was exported to was not found in available servers in this Moodle instance.\n";
    return false;
}


function update_activity_digest($courseid, $modid, $digest, $server) {
    global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $recordexists = $DB->get_record(OPPIA_DIGEST_TABLE,
        array(
            'courseid' => $courseid,
            'modid' => $modid,
            'serverid' => $server,
        ),
    );

    if ($recordexists) {
        $prevdigest = $recordexists->oppiaserverdigest;
        $recordexists->oppiaserverdigest = $digest;
        $recordexists->moodleactivitymd5 = $digest;
        $recordexists->updated = $timestamp;
        $DB->update_record(OPPIA_DIGEST_TABLE, $recordexists);
        return $prevdigest;
    } else {
        $DB->insert_record(OPPIA_DIGEST_TABLE,
            array(
                'courseid' => $courseid,
                'modid' => $modid,
                'oppiaserverdigest' => $digest,
                'moodleactivitymd5' => $digest,
                'serverid' => $server,
                'updated' => $timestamp)
        );
        return false;
    }
}
