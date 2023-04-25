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

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');

require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'langfilter.php');
require_once($pluginroot . 'activity/activity.class.php');
require_once($pluginroot . 'activity/page.php');
require_once($pluginroot . 'activity/quiz.php');
require_once($pluginroot . 'activity/resource.php');
require_once($pluginroot . 'activity/feedback.php');
require_once($pluginroot . 'activity/url.php');
require_once($pluginroot . 'activity/processor.php');

require_once($CFG->libdir.'/componentlib.class.php');


class ActivityProcessor {

    public $courseroot;
    public $courseid;
    public $serverid;
    public $versionid;
    public $keephtml;
    public $videooverlay;
    public $courseshortname;

    public $current_section;
    public $localmediafiles;

    public $printlogs = true;

    public function __construct($params=array()) {
        if (isset($params['id'])) {
            $this->id = $params['id'];
        }
        if (isset($params['courseroot'])) {
            $this->courseroot = $params['courseroot'];
        }
        if (isset($params['serverid'])) {
            $this->serverid = $params['serverid'];
        }
        if (isset($params['courseid'])) {
            $this->courseid = $params['courseid'];
        }
        if (isset($params['section'])) {
            $this->section = $params['section'];
        }
        if (isset($params['versionid'])) {
            $this->versionid = $params['versionid'];
        }
        if (isset($params['keephtml'])) {
            $this->keephtml = $params['keephtml'];
        }
        if (isset($params['videooverlay'])) {
            $this->videooverlay = $params['videooverlay'];
        }
        if (isset($params['localmediafiles'])) {
            $this->localmediafiles = $params['localmediafiles'];
        } else {
            $this->localmediafiles = array();
        }
        if (isset($params['printlogs'])) {
            $this->printlogs = $params['printlogs'];
        }
    }

    public function set_current_section($section) {
        $this->current_section = $section;
    }

    public function process_activity($mod, $sect, $actorderno, $xmlnode=null, $xmldoc=null, $password='') {

        $params = array(
            'id' => $mod->id,
            'courseroot' => $this->courseroot,
            'section' => $this->current_section,
            'serverid' => $this->serverid,
            'courseid' => $this->courseid,
            'printlogs' => $this->printlogs,
            'courseversion' => $this->versionid,
            'shortname' => $this->courseshortname,
            'summary' => $sect->summary,
            'keephtml' => $this->keephtml,
            'videooverlay' => $this->videooverlay,
            'password' => $password,
        );

        if ($mod->modname == 'page') {
            $page = new MobileActivityPage($params);
            $page->process();

            if ($xmlnode != null) {
                $page->get_xml($mod, $actorderno, $xmlnode, $xmldoc, true);
                $localmedia = $page->get_local_media();
                $mediafiles = $this->localmediafiles;
                $this->localmediafiles = array_merge($mediafiles, $localmedia);
            }
            return $page;

        } else if ($mod->modname == 'quiz') {

            $randomselect = get_oppiaconfig($mod->id, 'randomselect', 0, true, $this->serverid);
            $passthreshold = get_oppiaconfig($mod->id, 'passthreshold', 0, true, $this->serverid);
            $showfeedback = get_oppiaconfig($mod->id, 'showfeedback', 1, true, $this->serverid);
            $maxattempts = get_oppiaconfig($mod->id, 'maxattempts', 'unlimited', true, $this->serverid);

            $params['config_array'] = array(
                'randomselect' => $randomselect,
                'showfeedback' => $showfeedback,
                'passthreshold' => $passthreshold,
                'maxattempts' => $maxattempts
            );

            $quiz = new MobileActivityQuiz($params);

            $quiz->preprocess();
            if ($quiz->get_is_valid()) {
                $quiz->process();
                if ($xmlnode != null) {
                    $quiz->get_xml($mod, $actorderno, $xmlnode, $xmldoc, true);
                }
                return $quiz;
            } else {
                echo get_string('error_quiz_no_questions', PLUGINNAME).OPPIA_HTML_BR;
                return null;
            }
        } else if ($mod->modname == 'resource') {
            $resource = new MobileActivityResource($params);
            $resource->process();
            if ($xmlnode != null) {
                $resource->get_xml($mod, $actorderno, $xmlnode, $xmldoc, true);
            }
            return $resource;
        } else if ($mod->modname == 'url') {
            $url = new MobileActivityUrl($params);
            $url->process();
            if ($xmlnode != null) {
                $url->get_xml($mod, $actorderno, $xmlnode, $xmldoc, true);
            }
            return $url;
        } else if ($mod->modname == 'feedback') {
            $params['config_array'] = array(
                'showfeedback' => false,
                'passthreshold' => 0,
                'maxattempts' => 'unlimited',
            );

            $gradeboundaries = array();
            foreach (get_grade_boundaries($mod->id, $this->serverid) as $gb) {
                array_push($gradeboundaries, (object)[
                    $gb->grade => $gb->message
                ]);
            }
            rsort($gradeboundaries);

            if (!empty($gradeboundaries)) {
                $params['config_array']['grade_boundaries'] = $gradeboundaries;
            }

            $feedback = new MobileActivityFeedback($params);

            $feedback->preprocess();
            if ($feedback->get_is_valid()) {
                $feedback->process();
                if ($xmlnode != null) {
                    $feedback->get_xml($mod, $actorderno, $xmlnode, $xmldoc, true);
                }
                return $feedback;

            } else {
                echo get_string('error_feedback_no_questions', PLUGINNAME).OPPIA_HTML_BR;
                return null;
            }
        } else {
            echo get_string('error_not_supported', PLUGINNAME);
            return null;
        }
    }
}
