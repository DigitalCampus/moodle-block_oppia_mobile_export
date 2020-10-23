<?php

class mobile_activity_quiz extends mobile_activity {

	private $supported_types = array('multichoice', 'match', 'truefalse', 'description', 'shortanswer', 'numerical', 'ddimageortext');
	private $courseversion;
	private $summary;
	private $shortname;
	private $content = "";
	private $MATCHING_SEPERATOR = "|";
	private $quiz_image = null;
	private $is_valid = true; //i.e. doesn't only contain essay or random questions.
	private $no_questions = 0; // total no of valid questions
	private $configArray = array(); // config (quiz props) array
	private $quiz_media = array();


	function init($shortname, $summary, $configArray, $courseversion){
		$this->shortname = strip_tags($shortname);
		$this->summary = $summary;
		$this->configArray = $configArray;
		$this->courseversion = $courseversion;
	}

	function generate_md5($quiz, $quizJSON){
		$md5postfix = "";
		foreach($this->configArray as $key => $value){
			$md5postfix .= $key[0].((string) $value);
		}
		$contents = json_encode($quizJSON);
		$this->md5 = md5( $quiz->intro . removeIDsFromJSON($contents) . $md5postfix);
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
		$quizobj->preload_questions();
		$quizobj->load_questions();
		$qs = $quizobj->get_questions();
		
		$filename = extractImageFile($quiz->intro,'mod_quiz','intro','0',
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
		foreach($qs as $q){

			$questionMaxScore = intval($q->maxmark);
			$quizMaxScore += $questionMaxScore;
			$questionprops = array("maxscore" => $questionMaxScore);

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
				$questionprops['shuffleanswers'] = $q->options->shuffleanswers;
			}
			if($q->qtype == 'truefalse'){
				$q->qtype = 'multichoice';
			}



			$responses = array();

			//add feedback for matching questions
			if($q->qtype == 'match'){
				$q->qtype = 'matching';
				if($q->options->correctfeedback != ""){
					$feedbackJSON = extractLangs($q->options->correctfeedback, true);
					$questionprops["correctfeedback"] = json_decode($feedbackJSON);
				}
				if($q->options->partiallycorrectfeedback != ""){
					$feedbackJSON = extractLangs($q->options->partiallycorrectfeedback, true);
					$questionprops["partiallycorrectfeedback"] = json_decode($feedbackJSON);
				}
				if($q->options->incorrectfeedback != ""){
					$feedbackJSON = extractLangs($q->options->incorrectfeedback, true);
					$questionprops["incorrectfeedback"] = json_decode($feedbackJSON);
				}
			}

			if ($q->qtype == 'ddimageortext'){
				$q->qtype = 'draganddrop';
				$fs = get_file_storage();
				$ddoptions = $q->options;

				// find the dropzones
				$responseprops;
				foreach ($ddoptions->drops as $drop){
					$responseprops = array(
						'id' 		=> rand(1,1000),
						'type'		=> 'dropzone',
						'choice' 	=> $drop->choice,
						'xleft'		=> $drop->xleft,
						'ytop'		=> $drop->ytop,
						'droplabel'	=> $drop->label);

					array_push($responses, array(
						'order' => 1,
						'id' 	=> rand(1,1000),
						'props' => $responseprops,
						'title' => $drop->label,
						'score' => sprintf("%.4f", 0)
					));
				}

				// find the draggables
				foreach($ddoptions->drags as $drag){
					$responseprops = array(
						'id' 		=> rand(1,1000),
						'type'		=> 'drag',
						'draggroup'	=> $drag->draggroup,
						'infinite'	=> $drag->infinite,
						'no' 		=> $drag->no,
						'label'		=> $drag->label);

					$dragimage = $fs->get_area_files($q->contextid, 'qtype_ddimageortext', 'dragimage', $drag->id, 'itemid');
					foreach ($dragimage as $file){
						if ($file->is_directory()) {
		                    continue;
		                }
		                if ($dragimage = copyFile($file, 'qtype_ddimageortext', 'dragimage', $drag->id, $q->contextid,$this->courseroot,$cm->id)){
		                	$responseprops['dragimage'] = $dragimage;
		                }
					}

					array_push($responses, array(
						'order' => 1,
						'id' 	=> rand(1,1000),
						'props' => $responseprops,
						'title' => $drag->label,
						'score' => sprintf("%.4f", 0)
					));			
				}
				
				$bgfiles = $fs->get_area_files($q->contextid, 'qtype_ddimageortext', 'bgimage', $q->id, 'itemid');
				if ($bgfiles) {
            		foreach ($bgfiles as $file) {
		                if ($file->is_directory()) {
		                    continue;
		                }

		                $bgimage = copyFile($file, 'qtype_ddimageortext', 'bgimage', $q->id, $q->contextid,$this->courseroot,$cm->id);
		                if ($bgimage){
		                	$questionprops["bgimage"] = $bgimage;
		                }
		            }
		        }
			}

			// find if the question text has any images in it
			$question_image = extractImageFile($q->questiontext,'question','questiontext',
									$q->id,$q->contextid,$this->courseroot,$cm->id); 
		
			if($question_image){
				$questionprops["image"] = $question_image;
			}
			
			// find if any videos embedded in question text
			$q->questiontext = $this->extractMedia($q->id, $q->questiontext);
			if (array_key_exists($q->id,$this->quiz_media)){
				foreach($this->quiz_media[$q->id] as $media){
					$questionprops["media"] = $media->filename;
				}
			}
			
			$questionTitle = extractLangs(cleanHTMLEntities($q->questiontext, true), true, true);

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
					$titleJSON = extractLangs($sq->questiontext.$this->MATCHING_SEPERATOR.$sq->answertext, true);
					// add response
					$score = ($q->maxmark / $subqs);

					array_push($responses, array(
						'order' => $j,
						'id' 	=> rand(1,1000),
						'props' => json_decode ("{}"),
						'title' => json_decode($titleJSON),
						'score' => sprintf("%.4f", $score)
					));
					$j++;
				}
			}
			
			// for multichoice/multiselect/shortanswer/numerical questions
			if(isset($q->options->answers)){
				foreach($q->options->answers as $r){
					$responseprops = array('id' => rand(1,1000));
					
					if(strip_tags($r->feedback) != ""){
						$feedbackJSON = extractLangs($r->feedback, true);
						$responseprops['feedback'] = json_decode($feedbackJSON);
					}
					// if numerical also add a tolerance
					if($q->qtype == 'numerical'){
						$responseprops['tolerance'] = $r->tolerance;
					}
					$score = ($r->fraction * $q->maxmark);
					array_push($responses, array(
						'order' => $j,
						'id' 	=> rand(1,1000),
						'props' => $responseprops,
						'title' => json_decode(extractLangs($r->answer, true, true)),
						'score' => sprintf("%.4f", $score)
					));
					$j++;
				}
			}
			
			$questionJson = array(
				"id" 	=> rand(1,1000),
				"type" 	=> $q->qtype,
				"title" => json_decode($questionTitle),
				"props" => $questionprops,
				"responses" => $responses);

			array_push($quizJsonQuestions, array(
				'order'    => $i,
				'id'	   => rand(1,1000),
				'question' => $questionJson));
			$i++;
		}
		
		$quizprops["maxscore"] = $quizMaxScore;

		$quizJson = array(
			'id' 		 => rand(1,1000),
			'title' 	 => json_decode($nameJSON),
			'description'=> json_decode($descJSON),
			'props' 	 => $quizprops,
			'questions'  => $quizJsonQuestions);

		$this->generate_md5($quiz, $quizJson);
		$quizJson['props']['digest'] = $this->md5;
		$this->content = json_encode($quizJson);
		
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
	
		$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-/,[:space:]]*)[[:space:]]?[\"|\']\]\]))';
	
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
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$act->appendChild($temp);
			}
		} else {
			$temp = $xmlDoc->createElement("title");
			$temp->appendChild($xmlDoc->createCDATASection(strip_tags($mod->name)));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$act->appendChild($temp);
		}
		
		$temp = $xmlDoc->createElement("content");
		$temp->appendChild($xmlDoc->createCDATASection($this->content));
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
