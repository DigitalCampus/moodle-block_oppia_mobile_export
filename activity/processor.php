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

    public $course_root;
    public $course_id;
    public $server_id;
    public $versionid;
    public $keep_html;
    public $video_overlay;
    public $course_shortname;

    public $current_section;
    public $local_media_files;

    public $print_logs = true;

    public function __construct($params=array()) {
        if (isset($params['id'])) {
            $this->id = $params['id'];
        }
        if (isset($params['course_root'])) {
            $this->course_root = $params['course_root'];
        }
        if (isset($params['server_id'])) {
            $this->server_id = $params['server_id'];
        }
        if (isset($params['course_id'])) {
            $this->course_id = $params['course_id'];
        }
        if (isset($params['section'])) {
            $this->section = $params['section'];
        }
        if (isset($params['versionid'])) {
            $this->versionid = $params['versionid'];
        }
        if (isset($params['keep_html'])) {
            $this->keep_html = $params['keep_html'];
        }
        if (isset($params['video_overlay'])) {
            $this->video_overlay = $params['video_overlay'];
        }
        if (isset($params['local_media_files'])) {
            $this->local_media_files = $params['local_media_files'];
        } else {
            $this->local_media_files = array();
        }
        if (isset($params['print_logs'])) {
            $this->print_logs = $params['print_logs'];
        }
    }

    public function set_current_section($section) {
        $this->current_section = $section;
    }

    public function process_activity($mod, $sect, $act_orderno, $xmlnode=null, $xmldoc=null, $password='') {

        $params = array(
            'id' => $mod->id,
            'courseroot' => $this->course_root,
            'section' => $this->current_section,
            'server_id' => $this->server_id,
            'course_id' => $this->course_id,
            'print_logs' => $this->print_logs,
            'courseversion' => $this->versionid,
            'shortname' => $this->course_shortname,
            'summary' => $sect->summary,
            'keep_html' => $this->keep_html,
            'video_overlay' => $this->video_overlay,
            'password' => $password,
        );


        if ($mod->modname == 'page') {
            $page = new MobileActivityPage($params);
            $page->process();

            if ($xmlnode != null) {
                $page->get_xml($mod, $act_orderno, $xmlnode, $xmldoc, true);
                $local_media = $page->getLocalMedia();
                $media_files = $this->local_media_files;
                $this->local_media_files = array_merge($media_files, $local_media);
            }
            return $page;

        } else if ($mod->modname == 'quiz') {

            $randomselect = get_oppiaconfig($mod->id, 'randomselect', 0, $this->server_id);
            $passthreshold = get_oppiaconfig($mod->id, 'passthreshold', 0, $this->server_id);
            $showfeedback = get_oppiaconfig($mod->id, 'showfeedback', 1, $this->server_id);
            $maxattempts = get_oppiaconfig($mod->id, 'maxattempts', 'unlimited', $this->server_id);

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
                    $quiz->get_xml($mod, $act_orderno, $xmlnode, $xmldoc, true);
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
                $resource->get_xml($mod, $act_orderno, $xmlnode, $xmldoc, true);
            }
            return $resource;
        } else if ($mod->modname == 'url') {
            $url = new MobileActivityUrl($params);
            $url->process();
            if ($xmlnode != null) {
                $url->get_xml($mod, $act_orderno, $xmlnode, $xmldoc, true);
            }
            return $url;
        } else if ($mod->modname == 'feedback') {
            $params['config_array'] = array(
                'showfeedback' => false,
                'passthreshold' => 0,
                'maxattempts' => 'unlimited',
            );

            $grade_boundaries = array();
            foreach (get_grade_boundaries($mod->id, $this->server_id) as $gb) {
                array_push($grade_boundaries, (object)[
                    $gb->grade => $gb->message
                ]);
            }
            rsort($grade_boundaries);

            if (!empty($grade_boundaries)) {
                $params['config_array']['grade_boundaries'] = $grade_boundaries;
            }

            $feedback = new MobileActivityFeedback($params);

            $feedback->preprocess();
            if ($feedback->get_is_valid()) {
                $feedback->process();
                if ($xmlnode != null) {
                    $feedback->get_xml($mod, $act_orderno, $xmlnode, $xmldoc, true);
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