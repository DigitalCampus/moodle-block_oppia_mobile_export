<?php

class MobileActivityFeedback extends MobileActivity {
    
    private $supported_types = array('multichoicerated',
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
    private $is_valid = true; //i.e. doesn't only contain essay or random questions.
    private $no_questions = 0; // total no of valid questions
    private $configArray = array(); // config (quiz props) array
    private $keep_html = false; //Should the HTML of questions and answers be stripped out or not


    public function __construct($params=array()){ 
        parent::__construct($params);
        if (isset($params['shortname'])) { $this->shortname = strip_tags($params['shortname']); }
        if (isset($params['summary'])) { $this->summary = $params['summary']; }
        if (isset($params['config_array'])) { $this->configArray = $params['config_array']; }
        if (isset($params['courseversion'])) { $this->courseversion = $params['courseversion']; }
        if (isset($params['keep_html'])) { $this->keep_html = $params['keep_html']; }   

        $this->component_name = 'mod_feedback';
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
        global $DB;
        
        $cm = get_coursemodule_from_id('feedback', $this->id);
        context_module::instance($cm->id);
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

        $multiple_submit = intval($feedback->multiple_submit) == 1;

        if (!$multiple_submit){
            $quizprops['maxattempts'] = 1;    
        }

        $nameJSON = extractLangs($cm->name, true);
        $descJSON = extractLangs($feedback->intro, true, !$this->keep_html);
        
        $quizJsonQuestions = array();
        $quizMaxScore = 0;
        
        $i = 1;

        foreach($feedbackitems as $q){            

            if(!in_array($q->typ,$this->supported_types)){
                continue;
            }
            $responses = array();
            $title = $q->name;
            $required = $q->required == 1;
            $questionTitle = extractLangs(cleanHTMLEntities($title, true), true, !$this->keep_html);
            $type = null;
            
            // multichoice multi
            if($q->typ == "multichoice" 
                && (substr($q->presentation, 0, 1)==='c'
                    || substr($q->presentation, 0, 1)==='d')){
                $type = "multiselect";
                $respstr = substr($q->presentation, 6);
                $resps = explode('|', $respstr);
                $j = 1;
                foreach($resps as $resp){
                     $respTitle = extractLangs($resp, true, !$this->keep_html, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($respTitle),
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
                $questionTitle = extractLangs($q->presentation, true, !$this->keep_html);
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
                    preg_match('/([0-9]+)####(.*)/', $resp, $matches);
                    $score = is_null($matches[1]) ? "0" : $matches[1];
                    $respTitle = trim($matches[2]);
                    $respTitle = extractLangs($respTitle, true, !$this->keep_html, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($respTitle),
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
                    $respTitle = extractLangs($resp, true, !$this->keep_html, true);
                    array_push($responses, array(
                        'order' => $j,
                        'id'    => rand(1,1000),
                        'props' => json_decode ("{}"),
                        'title' => json_decode($respTitle),
                        'score' => "0"
                    ));
                    $j++;
                }
            }
            
            $questionprops = array(
                "maxscore" => 0,
                "required"  => $required,
                "label" => $q->label
            );
            
            // add any dependency props (skip logic)
            if ($q->dependitem != 0){
                // find dependitem label
                $dependitem = "";
                foreach($feedbackitems as $q_depend){
                    if($q->dependitem == $q_depend->id){
                        $dependitem = $q_depend->label;
                    }
                }
                $questionprops["dependvalue"] = $q->dependvalue;
                $questionprops["dependitemlabel"] = $dependitem;
            }
            
            
            $questionJson = array(
                "id"        => rand(1,1000),
                "type"      => $type,
                "title"     => json_decode($questionTitle),
                "props"     => $questionprops,
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

        // check for password protection
        // done after md5 is created so password can be changed without it being a new quiz
        if($this->password !== '') {
            $quizJson['props']['password'] = $this->password;
        }

        $this->content = json_encode($quizJson);
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

    function get_no_questions(){
        return $this->no_questions;
    }
}

?>
