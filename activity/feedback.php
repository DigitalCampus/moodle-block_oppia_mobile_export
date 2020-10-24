<?php

class mobile_activity_feedback extends mobile_activity {
    
    private $supported_types = array('multichoicerated', 'textarea', 'multichoice', 'numeric', 'textfield');
    private $courseversion;
    private $summary;
    private $shortname;
    private $content = "";
    private $feedback_image = null;
    private $is_valid = true; //i.e. doesn't only contain essay or random questions.
    private $no_questions = 0; // total no of valid questions
    private $configArray = array(); // config (quiz props) array
    
    function init($shortname, $summary, $courseversion, $configArray){
        $this->shortname = strip_tags($shortname);
        $this->summary = $summary;
        $this->courseversion = $courseversion;
        $this->configArray = $configArray;
    }

    function generate_md5($feedback, $quizJSON){
        $md5postfix = "";
        foreach($this->configArray as $key => $value){
            $md5postfix .= $key[0].((string) $value);
        }
        $contents = json_encode($quizJSON);
        $this->md5 = md5( $feedback->intro . removeIDsFromJSON($contents) . $md5postfix);
    }
    
    function preprocess(){
        global $DB,$CFG,$USER;
        $cm = get_coursemodule_from_id('feedback', $this->id);
        $context = context_module::instance($cm->id);
        $feedback = $DB->get_record('feedback', array('id'=>$cm->instance), '*', MUST_EXIST);
        
        $select = 'feedback = ?';
        $params = array($feedback->id);
        $feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');
        
        $count_omitted = 0;
        foreach($feedbackitems as $fi){
            if(in_array($fi->typ,$this->supported_types)){
                $this->no_questions++;
            } else {
                $count_omitted++;
            }
        }
        if($count_omitted == count($feedbackitems)){
            $this->is_valid = false;
        }
    }
    
    
    function process(){
        global $DB,$CFG,$USER,$QUIZ_CACHE;
        
        $cm = get_coursemodule_from_id('feedback', $this->id);
        $context = context_module::instance($cm->id);
        $feedback = $DB->get_record('feedback', array('id'=>$cm->instance), '*', MUST_EXIST);
        $select = 'feedback = ?';
        $params = array($feedback->id);
        $feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');
        
        $filename = extractImageFile($feedback->intro,'mod_feedback','intro','0',
            $context->id,$this->courseroot,$cm->id);
        
        if($filename){
            $this->quiz_image = "/images/".resizeImage($this->courseroot."/".$filename,
                $this->courseroot."/images/".$cm->id,
                $CFG->block_oppia_mobile_export_thumb_width,
                $CFG->block_oppia_mobile_export_thumb_height);
            //delete original image
            unlink($this->courseroot."/".$filename) or die(get_string('error_file_delete','block_oppia_mobile_export'));
        }
        
        $quizprops = array("courseversion" => $this->courseversion);
        
        foreach($this->configArray as $k=>$v){
            if ($k != 'randomselect' || $v != 0){
                $quizprops[$k] = $v;
            }
        }
        
        $nameJSON = extractLangs($cm->name,true);
        $descJSON = extractLangs($this->summary,true);
        
        $quizJsonQuestions = array();
        $quizMaxScore = 0;
        
        $i = 1;
        foreach($feedbackitems as $q){            

            $responses = array();
            $title = $q->label;
            if ($title == "" || $title == null){
                $title = $q->name;
  
            }
            $questionTitle = extractLangs(cleanHTMLEntities($title, true), true, true);
            $type = null;
            
            // multichoice multi
            if($q->typ == "multichoice" 
                && (substr($q->presentation,0,1)==='c'
                    || substr($q->presentation,0,1)==='d')){
                $type = "multiselect";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach($resps as $resp){
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => trim($resp),
                        'score' => "0"
                    ));
                    $j++;
                }
            } elseif ($q->typ == "info"){
                // info
                $type = "description";
            } elseif($q->typ == "label"){
                // label
                $type = "description";
                $questionTitle = extractLangs(cleanHTMLEntities($q->presentation, true), true, true);
            } elseif($q->typ == "textarea"){
                // long answer
                $type = "essay";
            } elseif($q->typ == "multichoicerated" && substr($q->presentation,0,1)==='r'){
                // multi - rated
                $type = "multichoice";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach($resps as $resp){
                    preg_match('/([0-9]+)#### (.*)/', $resp, $matches);
                    $score = $matches[1];
                    $respTitle = $matches[2];
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => $respTitle,
                        'score' => $score
                    ));
                    $j++;
                }
            } elseif($q->typ == "numeric"){
                // numeric
                $type = "numerical";
            } elseif($q->typ == "textfield"){
                // short answer
                $type = "shortanswer";
            } elseif($q->typ == "multichoice"){
                // multichoice 1
                $type = "multichoice";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach($resps as $resp){
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => trim($resp),
                        'score' => "0"
                    ));
                    $j++;
                }
            }
            $questionprops = array("maxscore" => 0);
            $questionJson = array(
                "id"    => rand(1,1000),
                "type"  => $type,
                "title" => json_decode($questionTitle),
                "props" => $questionprops,
                "responses" => $responses);
            
            array_push($quizJsonQuestions, array(
                'order'    => $i,
                'id'       => rand(1,1000),
                'question' => $questionJson));
            $i++;
        }
        
        $quizprops["maxscore"] = $quizMaxScore;
        
        $quizJson = array(
            'id'         => rand(1,1000),
            'title'      => json_decode($nameJSON),
            'description'=> json_decode($descJSON),
            'props'      => $quizprops,
            'questions'  => $quizJsonQuestions);
        
        $this->generate_md5($feedback, $quizJson);
        $quizJson['props']['digest'] = $this->md5;
        $this->content = json_encode($quizJson);
    }
    
    
    function export2print(){
        global $DB,$CFG,$USER,$QUIZ_CACHE;
        $cm = get_coursemodule_from_id('feedback', $this->id);
    }
    
    function getXML($mod, $counter, $activity=true, &$node, &$xmlDoc){
        global $DEFAULT_LANG;
        $act = $xmlDoc->createElement("activity");
        $act->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($mod->modname));
        $act->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
        $act->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));
        
        $title = extractLangs($mod->name);
        if(is_array($title) && count($title)>0){
            foreach($title as $l=>$t){
                $temp = $xmlDoc->createElement("title");
                $temp->appendChild($xmlDoc->createTextNode(strip_tags($t)));
                $temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
                $act->appendChild($temp);
            }
        } else {
            $temp = $xmlDoc->createElement("title");
            $temp->appendChild($xmlDoc->createTextNode(strip_tags($mod->name)));
            $temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
            $act->appendChild($temp);
        }
        
        $temp = $xmlDoc->createElement("content");
        $temp->appendChild($xmlDoc->createTextNode($this->content));
        $temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode("en"));
        $act->appendChild($temp);
        
        if($this->feedback_image){
            $temp = $xmlDoc->createElement("image");
            $temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->feedback_image));
            $act->appendChild($temp);
        }
        $node->appendChild($act);
    }
    
    function get_is_valid(){
        return $this->is_valid;
    }
}

?>