<?php

class mobile_activity_quiz extends mobile_activity {

	private $supported_types = array('multichoice', 'match', 'truefalse', 'description', 'shortanswer', 'numerical');
	private $courseversion;
	private $summary;
	private $shortname;
	private $content = "";
	private $MATCHING_SEPERATOR = "|";
	private $quiz_image = null;
	private $is_valid = true; //i.e. doesn't only contain essay or random questions.
	private $no_questions = 0; // total no of valid questions
	private $configArray = array(); // config (quiz props) array
	private $server_connection;
	private $quiz_media = array();
	
	function init($server_connection, $shortname, $summary, $configArray, $courseversion){
		$this->shortname = strip_tags($shortname);
		$this->summary = $summary;
		$this->configArray = $configArray;
		$this->courseversion = $courseversion;
		$this->server_connection = $server_connection;
	}
	
	function preprocess(){
		global $DB,$USER;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = context_module::instance($cm->id);
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$quizobj = quiz::create($cm->instance, $USER->id);
		if(!$quizobj->has_questions()){
			$this->no_questions = 0;
			$this->is_valid = false;
			return;
		}
		$quizobj->preload_questions();
		$quizobj->load_questions();
		$qs = $quizobj->get_questions();
		
		// check has at least one non-essay and non-random question
		$count_omitted = 0;
		foreach($qs as $q){
			if(in_array($q->qtype,$this->supported_types)){
				$this->no_questions++;
			} else {
				$count_omitted++;
			}
		}
		if($count_omitted == count($qs)){
			$this->is_valid = false;
		}
	}
	
	function process(){
		global $DB,$CFG,$USER,$QUIZ_CACHE;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = context_module::instance($cm->id);
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
	
		$quizobj = quiz::create($cm->instance, $USER->id);
		$mQH = new QuizHelper();
		$mQH->init($this->server_connection);

		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			
			$md5postfix = "";
			foreach($this->configArray as $key => $value){
				$md5postfix .= $key[0].((string) $value);
			}
			// generate the md5 of the quiz
			$this->md5 = md5(serialize($qs)).$this->id."c".$md5postfix;
			// find if this quiz already exists
			$resp = $mQH->exec('quizprops/digest/'.$this->md5, array(),'get');
			if(!isset($resp->quizzes)){
				echo get_string('error_connection','block_oppia_mobile_export');
				die;
			}
			
			$filename = extractImageFile($quiz->intro,
										'mod_quiz',
										'intro',
										'0',
										$context->id,
										$this->courseroot,
										$cm->id); 		
			
			if($filename){
				$this->quiz_image = "/images/".resizeImage($this->courseroot."/".$filename,
							$this->courseroot."/images/".$cm->id,
							$CFG->block_oppia_mobile_export_thumb_width,
							$CFG->block_oppia_mobile_export_thumb_height);
				//delete original image
				unlink($this->courseroot."/".$filename) or die(get_string('error_file_delete','block_oppia_mobile_export'));
			}
			
			// Don't export the full quiz if it already exists on the server
			// instead just add the course version
			if(count($resp->quizzes) > 0){
				$quiz_id = $resp->quizzes[0]->quiz_id;	
				$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
				$this->content = json_encode($quiz);
				
				$post = array('quiz_id' => $quiz_id,
						'name' => "courseversion",
						'value' => $this->courseversion);
				$resp = $mQH->exec('quizprops',$post);
				
				$this->exportQuestionImages();
				$this->exportQuestionMedia();
				return;
			}
			
			$props = array();
			array_push($props,array('name' => "digest", 'value' => $this->md5));
			array_push($props,array('name' => "courseversion", 'value' => $this->courseversion));
			foreach($this->configArray as $k=>$v){
				if ($k != 'randomselect' || $v != 0){
					array_push($props,array('name' => $k, 'value' => $v));
				}
			}
			
			$nameJSON = extractLangs($cm->name,true);
			$descJSON = extractLangs($this->summary,true);
			
			//create the quiz
			$post = array('title' => $nameJSON,
					'description' => $descJSON,
					'questions' => array(),
					'props' => $props);
			$resp = $mQH->exec('quiz', $post);
			$quiz_uri = $resp->resource_uri;
			$quiz_id = $resp->id;
			
			$i = 1;
			foreach($qs as $q){
				// skip any essay questions
				if($q->qtype == 'essay'){
					echo get_string('export_quiz_skip_essay','block_oppia_mobile_export')."<br/>";
					continue;
				}
				
				// skip any random questions
				if($q->qtype == 'random'){
					echo get_string('export_quiz_skip_random','block_oppia_mobile_export')."<br/>";
					continue;
				}
				
				
				//check to see if a multichoice is actually a multiselect
				if($q->qtype == 'multichoice'){
					$counter = 0;
					foreach($q->options->answers as $r){
						if($r->fraction > 0){
							$counter++;
						}
					}
					if($counter > 1){
						$q->qtype = 'multiselect';
					}
				}
				if($q->qtype == 'truefalse'){
					$q->qtype = 'multichoice';
				}
				
				// add max score property
				$props = array();
				$props[0] = array('name' => "maxscore", 'value' => $q->maxmark);
				
				//add feedback for matching questions
				if($q->qtype == 'match'){
					$q->qtype = 'matching';
					$prop_i = 1;
					if($q->options->correctfeedback != ""){
						$feedbackJSON = extractLangs($q->options->correctfeedback, true);
						$props[$prop_i] = array('name' => "correctfeedback", 'value' => $feedbackJSON);
						$prop_i++;
					}
					if($q->options->partiallycorrectfeedback != ""){
						$feedbackJSON = extractLangs($q->options->partiallycorrectfeedback, true);
						$props[$prop_i] = array('name' => "partiallycorrectfeedback", 'value' => $feedbackJSON);
						$prop_i++;
					}
					if($q->options->incorrectfeedback != ""){
						$feedbackJSON = extractLangs($q->options->incorrectfeedback, true);
						$props[$prop_i] = array('name' => "incorrectfeedback", 'value' => $feedbackJSON);
						$prop_i++;
					}
				}
				
				// find if the question text has any images in it
				$question_image = extractImageFile($q->questiontext,
										'question',
										'questiontext',
										$q->id,
										$q->contextid,
										$this->courseroot,
										$cm->id); 
			
				if($question_image){
					array_push($props, array('name' => "image", 'value' => $question_image));
				}
				
				// find if any videos embedded in question text
				$q->questiontext = $this->extractMedia($q->id, $q->questiontext);
				
				if (array_key_exists($q->id,$this->quiz_media)){
					foreach($this->quiz_media[$q->id] as $media){
						array_push($props, array('name' => "media", 'value' => $media->filename));
					}
				}
				
				$questionJSON = extractLangs($q->questiontext, true, true);
				
				// create question
				$post = array('title' => $questionJSON,
						'type' => $q->qtype,
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
				
				$j = 1;
				
				// if matching question then concat the options with |
				if(isset($q->options->subquestions)){
					// Find out how many subquestions
					$subqs = 0;
					foreach($q->options->subquestions as $sq){
						if(trim($sq->questiontext) != ""){
							$subqs++;
						}	
					}
					foreach($q->options->subquestions as $sq){
						$titleJSON = extractLangs($sq->questiontext.$this->MATCHING_SEPERATOR.$sq->answertext, true, true);
						// add response
						
						$props = array();
						
						$post = array('question' => $question_uri,
								'order' => $j,
								'title' => $titleJSON,
								'score' => ($q->maxmark / $subqs),
								'props' => $props);
						$resp = $mQH->exec('response', $post);
						$response_uri = $resp->resource_uri;
						
						$j++;
					}
				}
				
				// for multichoice/multiselect/shortanswer/numerical questions
				if(isset($q->options->answers)){
					foreach($q->options->answers as $r){
						
						$props = array();
						if(strip_tags($r->feedback) != ""){
							$feedbackJSON = extractLangs($r->feedback, true, true);
							$props[0] = array('name' => 'feedback', 'value' => $feedbackJSON);
						}
						
						// if numerical also add a tolerance
						if($q->qtype == 'numerical'){
							$props[1] = array('name' => 'tolerance', 'value' => $r->tolerance);
						}
						
						$responseJSON = extractLangs($r->answer, true, true);
						// add response
						$post = array('question' => $question_uri,
								'order' => $j,
								'title' => $responseJSON,
								'score' => ($r->fraction * $q->maxmark),
								'props' => $props);
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
			$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
			$this->content = json_encode($quiz);
			
		} catch (moodle_exception $me){
			echo get_string('export_quiz_skip','block_oppia_mobile_export')."<br/>";
			$this->is_valid = false;
			return;
		}
	}
	
	function export2print(){
		global $DB,$CFG,$USER,$QUIZ_CACHE;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = context_module::instance($cm->id);
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$quizobj = quiz::create($cm->instance, $USER->id);
		$return_content = "";
		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			
			$return_content = "<ol>";
			
			$i = 1;
			foreach($qs as $q){
				// skip any essay questions
				if($q->qtype == 'essay'){
					continue;
				}
			
				// skip any random questions
				if($q->qtype == 'random'){
					continue;
				}
				
				$return_content .= "<li>";
				$return_content .= "[".$q->name .": ".$q->qtype."] ".strip_tags($q->questiontext);
				
				if(isset($q->options->subquestions)){
					$return_content .= "<ul>";
					foreach($q->options->subquestions as $sq){
						$return_content .= "<li>".strip_tags($sq->questiontext)." -> ".strip_tags($sq->answertext)."</li>";
					}
					$return_content .= "</ul>";
				}
				
				if(isset($q->options->answers)){
					$return_content .= "<ul>";
					foreach($q->options->answers as $r){
						$return_content .= "<li>".strip_tags($r->answer)." [". ($r->fraction * $q->maxmark) ."] ";
						if(strip_tags($r->feedback) != ""){
							$return_content .= "feedback: ".strip_tags($r->feedback);
						}
						$return_content .= "</li>";
					}
					$return_content .= "</ul>";
				}
				$return_content .= "</li>";
				
				$i++;
			}
			$return_content .= "</ol>";
			return $return_content;
			
		} catch (moodle_exception $me){
			$this->is_valid = false;
			return;
		}	
	}
	
	private function extractMedia($question_id, $content){
		global $MEDIA;
	
		$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>([\{\}\'\"\:a-zA-Z0-9\._\-/,[:space:]]|[^\x00-\x7F])*)[[:space:]]?[\"|\']\]\]))';
	
		preg_match_all($regex,$content,$media_tmp, PREG_OFFSET_CAPTURE);
	
		if(!isset($media_tmp['mediaobject']) || count($media_tmp['mediaobject']) == 0){
			return $content;
		}
	
		for($i=0;$i<count($media_tmp['mediaobject']);$i++){
			$mediajson = json_decode($media_tmp['mediaobject'][$i][0]);
			$toreplace = $media_tmp[0][$i][0];
	
			$content = str_replace($toreplace, "", $content);
			// check all the required attrs exist
			if(!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)){
				echo get_string('error_media_attributes','block_oppia_mobile_export')."<br/>";
				die;
			}
				
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$mediajson->digest] = $mediajson;
			$this->quiz_media[$question_id][$mediajson->digest] = $mediajson;
		}
		$content = str_replace("[[/media]]", "", $content);
		return $content;
	}
	
	function exportQuestionImages (){
		global $DB,$CFG,$USER,$QUIZ_CACHE,$CFG;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = context_module::instance($cm->id);
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
		$quizobj = quiz::create($cm->instance, $USER->id);
		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			foreach($qs as $q){
				extractImageFile($q->questiontext,
										'question',
										'questiontext',
										$q->id,
										$q->contextid,
										$this->courseroot,
										$cm->id); 
			}
			
		} catch (moodle_exception $me){
			return;
		}
	}
	
	function exportQuestionMedia(){
		global $DB,$CFG,$USER,$QUIZ_CACHE,$CFG;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = context_module::instance($cm->id);
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
		$quizobj = quiz::create($cm->instance, $USER->id);
		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			foreach($qs as $q){
				$this->extractMedia($q->id, $q->questiontext);
			}
				
		} catch (moodle_exception $me){
			return;
		}
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
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
		
		if($this->quiz_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->quiz_image));
			$act->appendChild($temp);
		}
		$node->appendChild($act);
	}
	
	function get_is_valid(){
		return $this->is_valid;
	}
	
	function get_no_questions(){
		return $this->no_questions;
	}
}
