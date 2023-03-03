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

class MobileActivityQuiz extends MobileActivity {

    private $supportedtypes = array('multichoice', 'match', 'truefalse', 'description', 'shortanswer', 'numerical');
    private $courseversion;
    private $summary;
    private $shortname;
    private $content = "";
    private $matchingsperator = "|";
    private $isvalid = true; // I.e. doesn't only contain essay or random questions.
    private $configarray = array(); // Config (quiz props) array.
    private $quizmedia = array();
    private $keephtml = false; // Should the HTML of questions and answers be stripped out or not.


    public function __construct($params=array()) {
        parent::__construct($params);
        if (isset($params['shortname'])) {
            $this->shortname = strip_tags($params['shortname']);
        }
        if (isset($params['summary'])) {
            $this->summary = $params['summary'];
        }
        if (isset($params['config_array'])) {
            $this->configarray = $params['config_array'];
        }
        if (isset($params['courseversion'])) {
            $this->courseversion = $params['courseversion'];
        }
        if (isset($params['keephtml'])) {
            $this->keephtml = $params['keephtml'];
        }

        $this->componentname = 'mod_quiz';
    }

    function generate_md5($quiz, $quizjson) {
        $md5postfix = "";
        foreach ($this->configarray as $key => $value) {
            $md5postfix .= $key[0].((string) $value);
        }
        $contents = json_encode($quizjson);
        $this->md5 = md5( $quiz->intro . removeIDsFromJSON($contents) . $md5postfix);
    }

    // Bit masks for the quiz review options (copied from Moodle's internal class `mod_quiz_display_options`).
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN = 0x00100;
    const AFTER_CLOSE = 0x00010;

    function get_review_availability($quiz, $when) {
        return boolval(($when & intval($quiz->reviewcorrectness)) == $when);
    }

    function preprocess() {
        global $DB, $USER;
        $cm = get_coursemodule_from_id('quiz', $this->id);

        $quizobj = quiz::create($cm->instance, $USER->id);
        if (!$quizobj->has_questions()) {
            $this->noquestions = 0;
            $this->isvalid = false;
            return;
        }

        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $allowreviewafter = $this->get_review_availability($quiz, self::IMMEDIATELY_AFTER);
        $allowreviewlater = $this->get_review_availability($quiz, self::LATER_WHILE_OPEN);
        // Only include the config values if they are set to false.
        if (!$allowreviewafter) {
            $this->configarray['immediate_whether_correct'] = false;
        }
        if (!$allowreviewlater) {
            $this->configarray['later_whether_correct'] = false;
        }

        $quizobj->preload_questions();
        $quizobj->load_questions();
        $qs = $quizobj->get_questions();

        // Check has at least one non-essay and non-random question.
        $countomitted = 0;
        foreach ($qs as $q) {
            if (in_array($q->qtype, $this->supportedtypes)) {
                $this->noquestions++;
            } else {
                $countomitted++;
            }
        }
        if ($countomitted == count($qs)) {
            $this->isvalid = false;
        }
    }

    public function has_password() {
        global $DB, $USER;

        $cm = get_coursemodule_from_id('quiz', $this->id);
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);

        if ($quiz->password != "") {
            return true;
        } else {
            return parent::has_password();
        }
    }

    function process() {
        global $DB, $USER;

        $cm = get_coursemodule_from_id('quiz', $this->id);
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $quizobj = quiz::create($cm->instance, $USER->id);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $qs = $quizobj->get_questions();

        // Get the image from the intro section.
        $this->extract_thumbnail_from_intro($quiz->intro, $cm->id);

        $quizprops = array("courseversion" => $this->courseversion);

        foreach ($this->configarray as $k => $v) {
            if ($k != 'randomselect' || $v != 0) {
                $quizprops[$k] = $v;
            }
        }

        $namejson = extractLangs($cm->name, true);
        $descjson = extractLangs($quiz->intro, true, !$this->keephtml);

        $quizjsonquestions = array();
        $quizmaxscore = 0;

        $i = 1;
        foreach ($qs as $q) {

            $questionmaxscore = intval($q->maxmark);
            $quizmaxscore += $questionmaxscore;
            $questionprops = array(
                "moodle_question_id" => $q->questionbankentryid,
                "moodle_question_latest_version_id" => $q->id,
                "maxscore" => $questionmaxscore);

            // Skip any essay questions.
            if ($q->qtype == 'essay') {
                echo get_string('export_quiz_skip_essay', PLUGINNAME).OPPIA_HTML_BR;
                continue;
            }

            // Skip any random questions.
            if ($q->qtype == 'random') {
                echo get_string('export_quiz_skip_random', PLUGINNAME).OPPIA_HTML_BR;
                continue;
            }

            // Check to see if a multichoice is actually a multiselect.
            if ($q->qtype == 'multichoice') {
                $counter = 0;
                foreach ($q->options->answers as $r) {
                    if ($r->fraction > 0) {
                        $counter++;
                    }
                }
                if ($counter > 1) {
                    $q->qtype = 'multiselect';
                }
                $questionprops['shuffleanswers'] = $q->options->shuffleanswers;
            }
            if ($q->qtype == 'truefalse') {
                $q->qtype = 'multichoice';
            }

            $responses = array();

            // Add feedback for matching questions.
            if ($q->qtype == 'match') {
                $q->qtype = 'matching';
                if ($q->options->correctfeedback != "") {
                    $feedbackjson = extractLangs($q->options->correctfeedback, true, !$this->keephtml);
                    $questionprops["correctfeedback"] = json_decode($feedbackjson);
                }
                if ($q->options->partiallycorrectfeedback != "") {
                    $feedbackjson = extractLangs($q->options->partiallycorrectfeedback, true, !$this->keephtml);
                    $questionprops["partiallycorrectfeedback"] = json_decode($feedbackjson);
                }
                if ($q->options->incorrectfeedback != "") {
                    $feedbackjson = extractLangs($q->options->incorrectfeedback, true, !$this->keephtml);
                    $questionprops["incorrectfeedback"] = json_decode($feedbackjson);
                }
            }

            // Find if the question text has any images in it.
            $questionimage = extractImageFile($q->questiontext, 'question', 'questiontext',
                                    $q->id, $q->contextid, $this->courseroot, $cm->id);
            if ($questionimage) {
                $questionprops["image"] = $questionimage;
            }

            // Find if any videos embedded in question text.
            $q->questiontext = $this->extract_media($q->id, $q->questiontext);
            if (array_key_exists($q->id, $this->quizmedia)) {
                foreach ($this->quizmedia[$q->id] as $media) {
                    $questionprops["media"] = $media->filename;
                }
            }

            $questiontitle = extractLangs(cleanHTMLEntities($q->questiontext, true), true, !$this->keephtml);

            $j = 1;
            // If matching question then concat the options with |.
            if (isset($q->options->subquestions)) {
                // Find out how many subquestions.
                $subqs = 0;
                foreach ($q->options->subquestions as $sq) {
                    if (trim($sq->questiontext) != "") {
                        $subqs++;
                    }
                }
                foreach ($q->options->subquestions as $sq) {
                    $titlejson = extractLangs($sq->questiontext.$this->matchingsperator.$sq->answertext, true, !$this->keephtml, true);
                    // Add response.
                    $score = ($q->maxmark / $subqs);

                    array_push($responses, array(
                        'order' => $j,
                        'id' => rand(1, 1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($titlejson),
                        'score' => sprintf("%.4f", $score)
                    ));
                    $j++;
                }
            }

            // For multichoice/multiselect/shortanswer/numerical questions.
            if (isset($q->options->answers)) {
                foreach ($q->options->answers as $r) {
                    $responseprops = array('id' => rand(1, 1000));

                    if (strip_tags($r->feedback) != "") {
                        $feedbackjson = extractLangs($r->feedback, true, !$this->keephtml);
                        $responseprops['feedback'] = json_decode($feedbackjson);
                    }
                    // If numerical also add a tolerance.
                    if ($q->qtype == 'numerical') {
                        $responseprops['tolerance'] = $r->tolerance;
                    }
                    $score = ($r->fraction * $q->maxmark);

                    array_push($responses, array(
                        'order' => $j,
                        'id' => rand(1, 1000),
                        'props' => $responseprops,
                        'title' => json_decode(extractLangs($r->answer, true, !$this->keephtml, true)),
                        'score' => sprintf("%.4f", $score)
                    ));
                    $j++;
                }
            }

            $questionjson = array(
                "id" => rand(1, 1000),
                "type" => $q->qtype,
                "title" => json_decode($questiontitle),
                "props" => $questionprops,
                "responses" => $responses);

            array_push($quizjsonquestions, array(
                'order' => $i,
                'id' => rand(1, 1000),
                'question' => $questionjson));
            $i++;
        }

        $quizprops["maxscore"] = $quizmaxscore;

        $quizjson = array(
            'id' => rand(1, 1000),
            'title' => json_decode($namejson),
            'description' => json_decode($descjson),
            'props' => $quizprops,
            'questions' => $quizjsonquestions);

        $this->generate_md5($quiz, $quizjson);
        $quizjson['props']['digest'] = $this->md5;
        $quizjson['props']['moodle_quiz_id'] = $this->id;

        // Check for password protection.
        // Done after md5 is created so password can be changed without it being a new quiz.
        if ($quiz->password != "") {
            $quizjson['props']['password'] = $quiz->password;
        }
        $this->content = json_encode($quizjson);
    }

    private function extract_media($questionid, $content) {

        preg_match_all(EMBED_MEDIA_REGEX, $content, $mediatmp, PREG_OFFSET_CAPTURE);

        if (!isset($mediatmp['mediaobject']) || count($mediatmp['mediaobject']) == 0) {
            return $content;
        }

        for ($i = 0; $i < count($mediatmp['mediaobject']); $i++) {
            $mediajson = json_decode($mediatmp['mediaobject'][$i][0]);
            $toreplace = $mediatmp[0][$i][0];

            $content = str_replace($toreplace, "", $content);
            // Check all the required attrs exist.
            if (!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)) {
                echo get_string('error_media_attributes', PLUGINNAME).OPPIA_HTML_BR;
                die;
            }

            // Put the media in both the structure for page ($this->quizmedia) and for module ($MEDIA).
            $MEDIA[$mediajson->digest] = $mediajson;
            $this->quizmedia[$questionid][$mediajson->digest] = $mediajson;
        }
        return str_replace("[[/media]]", "", $content);
    }

    private function export_question_images() {
        global $USER;
        $cm = get_coursemodule_from_id('quiz', $this->id);

        $quizobj = quiz::create($cm->instance, $USER->id);
        try {
            $quizobj->preload_questions();
            $quizobj->load_questions();
            $qs = $quizobj->get_questions();
            foreach ($qs as $q) {
                extractImageFile($q->questiontext,
                                        'question',
                                        'questiontext',
                                        $q->id,
                                        $q->contextid,
                                        $this->courseroot,
                                        $cm->id);
            }
        } catch (moodle_exception $me) {
            return;
        }
    }

    private function export_question_media() {
        global $USER;
        $cm = get_coursemodule_from_id('quiz', $this->id);

        $quizobj = quiz::create($cm->instance, $USER->id);
        try {
            $quizobj->preload_questions();
            $quizobj->load_questions();
            $qs = $quizobj->get_questions();
            foreach ($qs as $q) {
                $this->extract_media($q->id, $q->questiontext);
            }
        } catch (moodle_exception $me) {
            return;
        }
    }

    function get_xml($mod, $counter, &$node, &$xmldoc, $activity=true) {
        global $defaultlang;

        $act = $this->get_activity_node($xmldoc, $mod, $counter);
        $this->add_lang_xml_nodes($xmldoc, $act, $mod->name, "title");

        $temp = $xmldoc->createElement("content");
        $temp->appendChild($xmldoc->createCDATASection($this->content));
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($defaultlang));
        $act->appendChild($temp);

        $this->add_thumbnail_xml_node($xmldoc, $act);

        $node->appendChild($act);
    }

    function get_is_valid() {
        return $this->isvalid;
    }

    function get_no_questions() {
        return $this->noquestions;
    }
}
