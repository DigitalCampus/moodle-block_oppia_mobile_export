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

class MobileActivityFeedback extends MobileActivity {

    private $supportedtypes = array('multichoicerated',
                                     'textarea',
                                     'multichoice',
                                     'numeric',
                                     'textfield',
                                     'info',
                                     'label');
    private $courseversion;
    private $summary;
    private $shortname;
    private $content = "";
    private $isvalid = true; // I.E. doesn't only contain essay or random questions.
    private $noratedquestions = 0; // Total no of questions of type multichoicerated.
    private $configarray = array(); // Config (quiz props) array.
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

        $this->componentname = 'mod_feedback';
    }


    private function generate_md5($feedback, $quizjson) {
        $md5postfix = "";
        foreach ($this->configarray as $key => $value) {
            $md5postfix .= $key[0].((string) $value);
        }
        $contents = json_encode($quizjson);
        $this->md5 = md5( $feedback->intro . removeIDsFromJSON($contents) . $md5postfix);
    }

    public function preprocess() {
        global $DB;
        $cm = get_coursemodule_from_id('feedback', $this->id);
        $feedback = $DB->get_record('feedback', array('id' => $cm->instance), '*', MUST_EXIST);

        $select = 'feedback = ?';
        $params = array($feedback->id);
        $feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');

        $countomitted = 0;
        foreach ($feedbackitems as $fi) {
            if (in_array($fi->typ, $this->supportedtypes)) {
                $this->noquestions++;
                if ($fi->typ == 'multichoicerated') {
                    $this->noratedquestions++;
                }
            } else {
                $countomitted++;
            }
        }
        if ($countomitted == count($feedbackitems)) {
            $this->isvalid = false;
        }
    }

    public function process() {
        global $DB;

        $cm = get_coursemodule_from_id('feedback', $this->id);
        context_module::instance($cm->id);
        $feedback = $DB->get_record('feedback', array('id' => $cm->instance), '*', MUST_EXIST);
        $select = 'feedback = ?';
        $params = array($feedback->id);
        $feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');

        // Get the image from the intro section.
        $this->extract_thumbnail_from_intro($feedback->intro, $cm->id);

        $quizprops = array("courseversion" => $this->courseversion);

        foreach ($this->configarray as $k => $v) {
            if ($k != 'randomselect' || $v != 0) {
                $quizprops[$k] = $v;
            }
        }

        $multiplesubmit = intval($feedback->multiple_submit) == 1;

        if (!$multiplesubmit) {
            $quizprops['maxattempts'] = 1;
        }

        $namejson = extract_langs($cm->name, true);
        $descjson = extract_langs($feedback->intro, true, !$this->keephtml);

        $quizjsonquestions = array();
        $quizmaxscore = 0;

        $i = 1;

        foreach ($feedbackitems as $q) {

            if (!in_array($q->typ, $this->supportedtypes)) {
                continue;
            }
            $responses = array();
            $title = $q->name;
            $required = $q->required == 1;
            $questiontitle = extract_langs(clean_html_entities($title, true), true, !$this->keephtml);
            $type = null;
            $maxquestionscore = 0;

            // Multichoice multi.
            if ($q->typ == "multichoice"
                && (substr($q->presentation, 0, 1) === 'c'
                    || substr($q->presentation, 0, 1) === 'd')) {
                $type = "multiselect";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach ($resps as $resp) {
                    $resptitle = extract_langs($resp, true, !$this->keephtml, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id' => rand(1, 1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($resptitle),
                        'score' => "0"
                    ));
                    $j++;
                }
            } else if ($q->typ == "info") {
                // Info.
                $type = "description";
            } else if ($q->typ == "label") {
                // Label.
                $type = "description";
                $questiontitle = extract_langs($q->presentation, true, !$this->keephtml);
            } else if ($q->typ == "textarea") {
                // Long answer.
                $type = "essay";
            } else if ($q->typ == "multichoicerated" && substr($q->presentation, 0, 1) === 'r') {
                // Multi - rated.
                $type = "multichoice";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach ($resps as $resp) {
                    preg_match('/([0-9]+)####(.*)/', $resp, $matches);
                    $score = is_null($matches[1]) ? "0" : $matches[1];
                    $maxquestionscore = max($maxquestionscore, $score);
                    $resptitle = trim($matches[2]);
                    $resptitle = extract_langs($resptitle, true, !$this->keephtml, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id' => rand(1, 1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($resptitle),
                        'score' => $score
                    ));
                    $j++;
                }
                $quizmaxscore += $maxquestionscore;
            } else if ($q->typ == "numeric") {
                // Numeric.
                $type = "numerical";
            } else if ($q->typ == "textfield") {
                // Short answer.
                $type = "shortanswer";
            } else if ($q->typ == "multichoice") {
                // Multichoice 1.
                $type = "multichoice";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach ($resps as $resp) {
                    $resptitle = extract_langs($resp, true, !$this->keephtml, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id' => rand(1, 1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($resptitle),
                        'score' => "0"
                    ));
                    $j++;
                }
            }

            $questionprops = array(
                "maxscore" => $maxquestionscore,
                "required" => $required,
                "label" => $q->label,
                "moodle_question_id" => $q->id,
            );

            // Add any dependency props (skip logic).
            if ($q->dependitem != 0) {
                // Find dependitem label.
                $dependitem = "";
                foreach ($feedbackitems as $qdepend) {
                    if ($q->dependitem == $qdepend->id) {
                        $dependitem = $qdepend->label;
                    }
                }
                $questionprops["dependvalue"] = $q->dependvalue;
                $questionprops["dependitemlabel"] = $dependitem;
            }

            $questionjson = array(
                "id" => rand(1, 1000),
                "type" => $type,
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
        $this->generate_md5($feedback, $quizjson);
        $quizjson['props']['digest'] = $this->md5;
        $quizjson['props']['moodle_quiz_id'] = $this->id;

        // Check for password protection.
        // Done after md5 is created so password can be changed without it being a new quiz.
        if ($this->password !== '') {
            $quizjson['props']['password'] = $this->password;
        }

        $this->content = json_encode($quizjson);
    }

    public function get_xml($mod, $counter, &$node, &$xmldoc, $activity=true) {
        global $DEFAULTLANG;

        $act = $this->get_activity_node($xmldoc, $mod, $counter);
        $this->add_lang_xml_nodes($xmldoc, $act, $mod->name, "title");
        $this->add_thumbnail_xml_node($xmldoc, $act);

        $temp = $xmldoc->createElement("content");
        $temp->appendChild($xmldoc->createTextNode($this->content));
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
        $act->appendChild($temp);

        $node->appendChild($act);
    }

    public function get_is_valid() {
        return $this->isvalid;
    }

    public function get_no_questions() {
        return $this->noquestions;
    }

    public function get_no_rated_questions() {
        return $this->noratedquestions;
    }
}
