<?php

class MobileActivityFeedback extends MobileActivity {
    
    private $supported_types = array('multichoicerated', 'textarea', 'multichoice', 'numeric', 'textfield');
    private $courseversion;
    private $summary;
    private $shortname;
    private $content = "";
    private $is_valid = true; //i.e. doesn't only contain essay or random questions.
    private $no_questions = 0; // total no of valid questions
    private $configArray = array(); // config (quiz props) array


    public function __construct(){ 
        $this->component_name = 'mod_feedback';
    }


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
        global $DB;
        $cm = get_coursemodule_from_id('feedback', $this->id);
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
        


        // get the image from the intro section
        $this->extractThumbnailFromIntro($feedback->intro, $cm->id);
        
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
        // do nothing
    }
    
    function getXML($mod, $counter, &$node, &$xmlDoc, $activity=true){
        global $DEFAULT_LANG;
        
        $act = $this->getActivityNode($xmlDoc, $mod, $counter);
        $this->addLangXMLNodes($xmlDoc, $act, $mod->name, "title");
        $this->addThumbnailXMLNode($xmlDoc, $act);

        $temp = $xmlDoc->createElement("content");
        $temp->appendChild($xmlDoc->createTextNode($this->content));
        $temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
        $act->appendChild($temp);
        
        $node->appendChild($act);
    }
    
    function get_is_valid(){
        return $this->is_valid;
    }
}

?>
