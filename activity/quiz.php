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
    private $quizhtmlfiles = false; // Should the quiz questions, responses and feedback be exported as HTML files.

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
        if (isset($params['quizhtmlfiles'])) {
            $this->quizhtmlfiles = $params['quizhtmlfiles'];
        }

        $this->componentname = 'mod_quiz';
    }

    private function generate_md5($quiz, $quizjson) {
        $md5postfix = "";
        foreach ($this->configarray as $key => $value) {
            $md5postfix .= $key[0].((string) $value);
        }
        $contents = json_encode($quizjson);
        $this->md5 = md5( $quiz->intro . remove_ids_from_json($contents) . $md5postfix);
    }

    // Bit masks for the quiz review options (copied from Moodle's internal class `mod_quiz_display_options`).
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN = 0x00100;
    const AFTER_CLOSE = 0x00010;

    private function get_review_availability($quiz, $when) {
        return boolval(($when & intval($quiz->reviewcorrectness)) == $when);
    }

    public function preprocess() {
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
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $this->id);
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);

        if ($quiz->password != "") {
            return true;
        } else {
            return parent::has_password();
        }
    }

    public function process() {
        global $DB, $USER, $MEDIA, $DEFAULTLANG;

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

        $namejson = extract_langs($cm->name, true, false, false);
        $descjson = extract_langs($quiz->intro, true, !$this->keephtml, false);

        $quizjsonquestions = array();
        $quizmaxscore = 0;

        $i = 1;
        foreach ($qs as $q) {

            $questionmaxscore = intval($q->maxmark);
            $quizmaxscore += $questionmaxscore;
            $questionprops = array(
                "moodle_question_id" => $q->questionbankentryid,
                "moodle_question_latest_version_id" => $q->id,
                "maxscore" => $questionmaxscore
            );

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
                $questionprops['show_standard_instructions'] = $q->options->showstandardinstruction;
            }
            if ($q->qtype == 'truefalse') {
                $q->qtype = 'multichoice';
            }

            $responses = array();

            // Add feedback for matching questions.
            if ($q->qtype == 'match') {
                $q->qtype = 'matching';
                
            }

            // Find if the question text has any images in it.
            $questionimage = extract_image_file($q->questiontext, 'question', 'questiontext',
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

            $questiontitle = extract_langs(clean_html_entities($q->questiontext, true), true, !$this->keephtml, false);

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
                    $titlejson = extract_langs($sq->questiontext.$this->matchingsperator.$sq->answertext,
                        true,
                        !$this->keephtml,
                        true);
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
                    
                    if($this->quizhtmlfiles){
                        // save response as an html file
                        $response_option_langs = extract_langs($r->answer, false, false, false);
                        
                        $temp_response_langs = array();
                        if (is_array($response_option_langs) && count($response_option_langs) > 0) {
                            foreach ($response_option_langs as $lang => $text) {
                                // Process individually each language.
                                $temp_response_langs[$lang] = $this->generate_as_html($q->contextid, 'response', $cm->id, $text, $lang,  $q->id, $r->id);
                            }
                        } else {
                            $temp_response_langs[$DEFAULTLANG] = $this->generate_as_html($q->contextid, 'response', $cm->id, $text, $DEFAULTLANG,  $q->id, $r->id);
                        }
                        $responseprops["responsehtmlfile"] = json_encode($temp_response_langs);
                    }
                    
                    if (strip_tags($r->feedback) != "") {
                        $feedbackjson = extract_langs($r->feedback, true, !$this->keephtml, false);
                        $responseprops['feedback'] = json_decode($feedbackjson);
                        
                        if($this->quizhtmlfiles){
                            // save feedback as an html file
                            $feedback_option_langs = extract_langs($r->feedback, false, false, false);
                            $temp_feedback_langs = array();
                            if (is_array($feedback_option_langs) && count($feedback_option_langs) > 0) {
                                foreach ($feedback_option_langs as $lang => $text) {
                                    // Process individually each language.
                                    $temp_feedback_langs[$lang] = $this->generate_as_html($q->contextid, 'feedback', $cm->id, $text, lang,  $q->id, $r->id);
                                }
                            } else {
                                $temp_feedback_langs[$DEFAULTLANG] = $this->generate_as_html($q->contextid, 'feedback', $cm->id, $text, $DEFAULTLANG,  $q->id, $r->id);
                            }
                            $responseprops["feedbackhtmlfile"] = json_encode($temp_feedback_langs);
                        }
                        
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
                        'title' => json_decode(extract_langs($r->answer, true, !$this->keephtml, true)),
                        'score' => sprintf("%.4f", $score)
                    ));
                    $j++;
                }
            }

            # add options for correct/partial/incorrect
            if ($q->options->correctfeedback != "") {
                $feedbackjson = extract_langs($q->options->correctfeedback, true, !$this->keephtml, false);
                $questionprops["correctfeedback"] = json_decode($feedbackjson);
                if($this->quizhtmlfiles){
                    // save feedback as an html file
                    $correctfeedback_option_langs = extract_langs($q->options->correctfeedback, false, false, false);
                    $temp_correctfeedback_langs = array();
                    if (is_array($correctfeedback_option_langs) && count($correctfeedback_option_langs) > 0) {
                        foreach ($correctfeedback_option_langs as $lang => $text) {
                            // Process individually each language.
                            $temp_correctfeedback_langs[$lang] = $this->generate_as_html($q->contextid, 'feedback', $cm->id, $text, lang,  $q->id, $r->id);
                        }
                    } else {
                        $temp_correctfeedback_langs[$DEFAULTLANG] = $this->generate_as_html($q->contextid, 'feedback', $cm->id, $text, $DEFAULTLANG,  $q->id, $r->id);
                    }
                    $questionprops["feedbackhtmlfile"] = json_encode($temp_correctfeedback_langs);
                }
            }
            if ($q->options->partiallycorrectfeedback != "") {
                $feedbackjson = extract_langs($q->options->partiallycorrectfeedback, true, !$this->keephtml, false);
                $questionprops["partiallycorrectfeedback"] = json_decode($feedbackjson);
            }
            if ($q->options->incorrectfeedback != "") {
                $feedbackjson = extract_langs($q->options->incorrectfeedback, true, !$this->keephtml, false);
                $questionprops["incorrectfeedback"] = json_decode($feedbackjson);
            }
            
            if($this->quizhtmlfiles){
                // save question as an html file
                $question_title_langs = extract_langs($q->questiontext, false, false, false);
                $temp_question_langs = array();
                if (is_array($question_title_langs) && count($question_title_langs) > 0) {
                    foreach ($question_title_langs as $lang => $text) {
                        // Process individually each language.
                        $temp_question_langs[$lang] = $this->generate_as_html($q->contextid, 'question', $cm->id, $text, $lang,  $q->id, null);
                    }
                } else {
                    $temp_question_langs[$DEFAULTLANG] = $this->generate_as_html($q->contextid, 'question', $cm->id, $text, $DEFAULTLANG,  $q->id, null);
                }
                $questionprops["htmlfile"] = json_encode($temp_question_langs);
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
        $count = count($mediatmp['mediaobject']);
        for ($i = 0; $i < $count; $i++) {
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

    public function get_xml($mod, $counter, &$node, &$xmldoc, $activity) {
        global $DEFAULTLANG;

        $act = $this->get_activity_node($xmldoc, $mod, $counter);
        $this->add_lang_xml_nodes($xmldoc, $act, $mod->name, "title");

        $temp = $xmldoc->createElement("content");
        $temp->appendChild($xmldoc->createCDATASection($this->content));
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
        $act->appendChild($temp);

        $this->add_thumbnail_xml_node($xmldoc, $act);

        $node->appendChild($act);
    }

    private function generate_as_html($contextid, $type, $modid, $content, $lang, $question_id, $response_id){
        
        if($type == "question"){
            $html_filename = $this->make_question_html_filename($this->section, $question_id, $lang);
            $content = $this->extract_and_replace_image_files($content, 'question', 'questiontext', $question_id, $contextid);
        } else if ($type == "response"){
            $html_filename = $this->make_response_html_filename($this->section, $question_id, $response_id, $lang);
            $content = $this->extract_and_replace_image_files($content, 'question', 'answer', $response_id, $contextid);
        } else if ($type == "feedback"){
            $html_filename = $this->make_feedback_html_filename($this->section, $question_id, $response_id, $lang);
            $content = $this->extract_and_replace_image_files($content, 'question', 'answerfeedback', $response_id, $contextid);
        } else if ($type == "correctfeedback"){
            $html_filename = $this->make_question_correctfeedback_html_filename($this->section, $question_id, $lang);
            $content = $this->extract_and_replace_image_files($content, 'question', 'correctfeedback', $response_id, $contextid);
        } else if ($type == "partiallyincorrectfeedback"){
            $html_filename = $this->make_question_partiallyincorrectfeedback_html_filename($this->section, $question_id, $lang);
            $content = $this->extract_and_replace_image_files($content, 'question', 'partiallycorrectfeedback', $response_id, $contextid);
        }
        
        
        $webpage = '<!DOCTYPE html>';
        $webpage .= '<html><head>';
        $webpage .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $webpage .= '<link href="style.css" rel="stylesheet" type="text/css"/>';
        $webpage .= '<script src="js/jquery-3.6.0.min.js"></script>';
        $webpage .= '<script src="js/oppia.js"></script>';
        $webpage .= '</head>';
        $webpage .= '<body>'.$content.'</body></html>';
        
        $index = $this->courseroot."/".$html_filename;
        $fh = fopen($index, 'w');
        if ($fh !== false) {
            fwrite($fh, $webpage);
            fclose($fh);
        }
        return $html_filename;
    }
    
    private function extract_and_replace_image_files($content, $component, $filearea, $itemid, $contextid) {
        global $CFG;
        
        
        preg_match_all(MEDIAFILE_REGEX, $content, $filestmp, PREG_OFFSET_CAPTURE);
        
        if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
            return $content;
        }
        $toreplace = array();
        $count = count($filestmp['filenames']);
        
        for ($i = 0; $i < $count; $i++) {
            
            $origfilename = $filestmp['filenames'][$i][0];
            $filename = urldecode($origfilename);
            $cleanfilename = filename_to_ascii($filename);
  
            $filepath = '/';
            $fs = get_file_storage();            
            $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
            
            if ($file) {
                $imgfile = $this->courseroot."/images/".$cleanfilename;
                $file->copy_content_to($imgfile);
            } else {
                if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                    echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found', PLUGINNAME,
                        $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
                        return null;
                }
            }
            
            if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
            }
            
            
            $filenamereplace = new StdClass;
            $filenamereplace->filename = $filename;
            $filenamereplace->origfilename = $origfilename;
            $filenamereplace->cleanfilename = $cleanfilename;
            array_push($toreplace, $filenamereplace);
        }
        
        foreach ($toreplace as $tr) {
            $content = str_replace(MEDIAFILE_PREFIX.'/'.$tr->origfilename, 'images/'.$tr->cleanfilename, $content);
            $content = str_replace(MEDIAFILE_PREFIX.'/'.urlencode($tr->filename), 'images/'.$tr->cleanfilename, $content);
        }
        
        return $content;
    }
    
    private function make_question_html_filename($sectionno, $question_id, $lang) {
        return sprintf('%02d_%02d', $sectionno, $question_id)."_question_".strtolower($lang).".html";
    }
    
    private function make_response_html_filename($sectionno, $question_id, $response_id, $lang) {
        return sprintf('%02d_%02d_%02d', $sectionno, $question_id, $response_id)."_response_".strtolower($lang).".html";
    }
    
    private function make_feedback_html_filename($sectionno, $question_id, $response_id, $lang) {
        return sprintf('%02d_%02d_%02d', $sectionno, $question_id, $response_id)."_feedback_".strtolower($lang).".html";
    }
    
    private function make_question_correctfeedback_html_filename($sectionno, $question_id, $lang) {
        return sprintf('%02d_%02d', $sectionno, $question_id)."_question_correctfeedback".strtolower($lang).".html";
    }
    
    private function make_question_partiallyincorrectfeedback_html_filename($sectionno, $question_id, $lang) {
        return sprintf('%02d_%02d', $sectionno, $question_id)."_question_partiallyincorrectfeedback".strtolower($lang).".html";
    }
    
    public function get_is_valid() {
        return $this->isvalid;
    }

    public function get_no_questions() {
        return $this->noquestions;
    }
}
