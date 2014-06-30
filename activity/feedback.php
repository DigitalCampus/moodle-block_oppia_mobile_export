<?php

class mobile_activity_feedback extends mobile_activity {
	
	private $supported_types = array('multichoicerated', 'textarea', 'multichoice');
	private $courseversion;
	private $summary;
	private $shortname;
	private $content = "";
	private $feedback_image = null;
	private $is_valid = true; //i.e. doesn't only contain essay or random questions.
	private $no_questions = 0; // total no of valid questions
	private $server_connection;
	
	function init($server_connection, $shortname, $summary, $courseversion){
		$this->shortname = strip_tags($shortname);
		$this->summary = strip_tags($summary);
		$this->courseversion = $courseversion;
		$this->server_connection = $server_connection;
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
		global $DB,$CFG,$USER;
		$cm = get_coursemodule_from_id('feedback', $this->id);
		$context = context_module::instance($cm->id);
		$feedback = $DB->get_record('feedback', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$select = 'feedback = ?';
		$params = array($feedback->id);
		$feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');
		
		
		$mQH = new QuizHelper();
		$mQH->init($this->server_connection);
		
		$this->md5 = md5(serialize($feedbackitems)).$this->id;
		
		// find if this quiz already exists
		$resp = $mQH->exec('quizprops/digest/'.$this->md5, array(),'get');
		if(!isset($resp->quizzes)){
			echo get_string('error_connection','block_oppia_mobile_export');
			die;
		}
		
		$filename = extractImageFile($feedback->intro,
				'mod_feedback',
				'intro',
				'0',
				$context->id,
				$this->courseroot,
				$cm->id);
			
		if($filename){
			$this->feedback_image = "/images/".resizeImage($this->courseroot."/".$filename,
					$this->courseroot."/images/".$cm->id,
					$CFG->block_oppia_mobile_export_thumb_width,
					$CFG->block_oppia_mobile_export_thumb_height);
			//delete original image
			unlink($this->courseroot."/".$filename) or die(get_string('error_file_delete','block_oppia_mobile_export'));
		}
		
		
		if(count($resp->quizzes) > 0){
			$quiz_id = $resp->quizzes[0]->quiz_id;
			$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
			$this->content = json_encode($quiz);
			return;
		}
		
		$props = array();
		$props[0] = array('name' => "digest", 'value' => $this->md5);
		$props[1] = array('name' => "courseversion", 'value' => $this->courseversion);
		
		//create the quiz
		$post = array('title' => $cm->name,
				'description' => $this->summary,
				'questions' => array(),
				'props' => $props);
		$resp = $mQH->exec('quiz', $post);
		$quiz_uri = $resp->resource_uri;
		$quiz_id = $resp->id;
		
		$i = 1;
		foreach($feedbackitems as $fi){
			if(!in_array($fi->typ,$this->supported_types)){
				continue;
			}
			
			if($fi->required){
				$value = "true";
			} else {
				$value = "false";
			}
			$props = array();
			$props[0] = array('name' => "required", 'value' => $value);
			
			//create the question
			if($fi->typ == "multichoice" || $fi->typ == "multichoicerated"){
				$post = array('title' => trim(strip_tags($fi->name)),
						'type' => "multichoice",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			
			if($fi->typ == "textarea"){
				$post = array('title' => trim(strip_tags($fi->name)),
						'type' => "essay",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			
			// add the response options
			if($fi->typ == "multichoice"){
				$j = 1;
				$presentation = preg_replace("(r[>]+)",'',$fi->presentation);	
				$presentation = preg_replace("(c[>]+)",'',$presentation);
				$response_options = explode("|",$presentation);
				foreach($response_options as $ro){
					$post = array('question' => $question_uri,
							'order' => $j,
							'title' => trim(strip_tags($ro)),
							'score' => 0,
							'props' => array());
					$resp = $mQH->exec('response', $post);
					$j++;
				}
				
			}

			if($fi->typ == "multichoicerated"){
				$j = 1;
				$presentation = preg_replace("(r[>>]+)",'',$fi->presentation);				
				$response_options = explode("|",$presentation);
				foreach($response_options as $ro){
					$new_ro = preg_replace("([0-9]+[#]+)",'',$ro);
					$post = array('question' => $question_uri,
							'order' => $j,
							'title' => trim(strip_tags($new_ro)),
							'score' => 0,
							'props' => array());
					$resp = $mQH->exec('response', $post);
					$j++;
				}
			}
			
			// add question to quiz
			$post = array('quiz' => $quiz_uri,
					'question' => $question_uri,
					'order' => $i);
			$resp = $mQH->exec('quizquestion', $post);
			
			$i++;
		}
		
		// get the final quiz object
		$feedback = $mQH->exec('quiz/'.$quiz_id, array(),'get');
		$this->content = json_encode($feedback);
	}
	
	
	function export2print(){
		global $DB,$CFG,$USER,$QUIZ_CACHE;
		$cm = get_coursemodule_from_id('feedback', $this->id);
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		$act = $xmlDoc->createElement("activity");
		$act->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($mod->modname));
		$act->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
		$act->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));
		
		$temp = $xmlDoc->createElement("title");
		$temp->appendChild($xmlDoc->createTextNode($mod->name));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode("en"));
		$act->appendChild($temp);
		
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