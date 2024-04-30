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

require_once($CFG->dirroot.'/config.php');
require_once(dirname(__FILE__) . '/../constants.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/componentlib.class.php');
require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'activity/processor.php');

const SELECT_COURSES_DIGEST = 'name="coursepriority"';


function populate_digests_published_courses() {
    global $DB;

    $coursescount = $DB->count_records_select(OPPIA_CONFIG_TABLE,
                SELECT_COURSES_DIGEST, null,
                'COUNT(DISTINCT modid)');

    $coursesresult = $DB->get_recordset_select(OPPIA_CONFIG_TABLE,
                SELECT_COURSES_DIGEST, null, null,
                'DISTINCT modid');

    if (($coursescount > 0) && ($coursesresult->valid())) {
        echo '<p class="lead">' . $coursescount . " courses to process" . '</p>';

        foreach ($coursesresult as $r) {
            $courseid = $r->modid;
            $course = $DB->get_record('course', array('id' => $courseid), '*', $strictness = IGNORE_MISSING);

            if ($course == false) {
                // The course was deleted but there are still some rows in the course_info table.
                continue;
            }
            echo '<br>';
            echo '<h3>' . strip_tags($course->fullname) . '</h3>';

            $courseservers = $DB->get_recordset_select(OPPIA_CONFIG_TABLE,
                "modid='$courseid'", null, null,
                "DISTINCT serverid");

            foreach ($courseservers as $s) {
                $serverid = $s->serverid;
                echo '<strong>Server ID:' . $serverid . '</strong><br>';
                populate_digests_for_course($course, $courseid, $serverid, null, true);
            }
        }
    } else {
        echo "There were no previously exported courses to process.";
    }

    $coursesresult->close();

}


/*
    'digeststopreserve' is an array containing the value of the digest that we have to preserve.
      The array's key is the real digest of the moodle activity.
      The array's value is the digest that we want to preserve in the output modules.xml. Might be different from the real digest.
*/

function populate_digests_for_course($course, $courseid, $serverid, $digeststopreserve, $printlogs) {
    global $CFG, $DEFAULTLANG, $pluginroot;
    $DEFAULTLANG = get_oppiaconfig($courseid, 'default_lang', $CFG->block_oppia_mobile_export_default_lang, true, $serverid);

    $modinfo = course_modinfo::instance($courseid);
    $sections = $modinfo->get_section_info_all();
    $mods = $modinfo->get_cms();

    $keephtml = get_oppiaconfig($courseid, 'keephtml', '', true, $serverid);
    $quizhtmlfiles = get_oppiaconfig($courseid, 'quizhtmlfiles', '', true, $serverid);
    $videooverlay = get_oppiaconfig($courseid, 'videooverlay', '', true, $serverid);
    $course->shortname = clean_shortname($course->shortname);

    delete_dir($pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp");
    delete_dir($pluginroot.OPPIA_OUTPUT_DIR."upgrade");

    mkdir($pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp/", 0777, true);
    $courseroot = $pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp/".strtolower($course->shortname);
    mkdir($courseroot, 0777);
    mkdir($courseroot."/images", 0777);
    $fh = fopen($courseroot."/images/.nomedia", 'w');
    fclose($fh);
    mkdir($courseroot."/resources", 0777);
    $fh = fopen($courseroot."/resources/.nomedia", 'w');
    fclose($fh);

    $processor = new ActivityProcessor(array(
        'courseroot' => $courseroot,
        'serverid' => $serverid,
        'courseid' => $courseid,
        'courseshortname' => $course->shortname,
        'versionid' => '0',
        'keephtml' => $keephtml,
        'quizhtmlfiles' => $quizhtmlfiles,
        'videooverlay' => $videooverlay,
        'printlogs' => $printlogs,
    ));

    echo '<div class="oppia_export_section py-3">';

    $sectorderno = 1;
    foreach ($sections as $sect) {
        flush_buffers();
        // We avoid the topic0 as is not a section as the rest.
        if ($sect->section == 0) {
            $sectiontitle = "Intro";
        } else {
            $sectiontitle = strip_tags($sect->summary);
            // If the course has no summary, we try to use the section name.
            if ($sectiontitle == "") {
                $sectiontitle = strip_tags($sect->name);
            }
        }

        echo "<h4>".get_string('export_renewing_digests_in_section', PLUGINNAME, $sectiontitle)."</h4>";

        $sectionmods = explode(",", $sect->sequence);
        $actorderno = 1;
        $processor->set_current_section($sectorderno);

        foreach ($sectionmods as $modnumber) {
            if ($modnumber == "" || $modnumber === false) {
                continue;
            }
            $mod = $mods[$modnumber];
            echo '<div class="step"><strong>'.$mod->name.'</strong>'.OPPIA_HTML_BR;
            $activity = $processor->process_activity($mod, $sect, $actorderno);
            if ($activity != null) {
                $nquestions = null;
                if (($mod->modname == 'quiz') || ($mod->modname == 'feedback')) {
                    $nquestions = $activity->get_no_questions();
                }
                $moodleactivitymd5 = $activity->md5;

                if ($digeststopreserve != null) {
                    $oppiaserverdigest = $digeststopreserve[$moodleactivitymd5];
                }

                save_activity_digest($courseid, $mod->id, $oppiaserverdigest, $moodleactivitymd5, $serverid, $nquestions);
                $actorderno++;
            }
            echo '</div>';
        }
        if ($actorderno > 1) {
            $sectorderno++;
        }
    }
    echo '</div>';
}

function save_activity_digest($courseid, $modid, $oppiaserverdigest, $moodleactivitymd5, $serverid, $nquestions=null) {
    global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $recordexists = $DB->get_record(OPPIA_DIGEST_TABLE,
        array(
            'courseid' => $courseid,
            'modid' => $modid,
            'serverid' => $serverid,
        ),
    );

    if ($recordexists) {
        if ($oppiaserverdigest != null) {
            $recordexists->oppiaserverdigest = $oppiaserverdigest;
        }
        $recordexists->moodleactivitymd5 = $moodleactivitymd5;
        $recordexists->updated = $timestamp;
        $recordexists->nquestions = $nquestions;

        $DB->update_record(OPPIA_DIGEST_TABLE, $recordexists);
    } else {
        $oppiaserverdigest = $moodleactivitymd5;
        $DB->insert_record(OPPIA_DIGEST_TABLE,
            array(
                'courseid' => $courseid,
                'modid' => $modid,
                'oppiaserverdigest' => $oppiaserverdigest,
                'moodleactivitymd5' => $moodleactivitymd5,
                'updated' => $timestamp,
                'serverid' => $serverid,
                'nquestions' => $nquestions)
        );
    }

    echo 'Digest: <span class="alert alert-warning mt-3 py-1">' . ($oppiaserverdigest ?? $recordexists->oppiaserverdigest) . '</span>';
}
