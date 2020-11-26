<?php

class MobileActivityQuiz extends MobileActivity {

	private $supported_types = array('multichoice', 'match', 'truefalse', 'description', 'shortanswer', 'numerical');
	private $courseversion;
	private $summary;
	private $shortname;
	private $content = "";
	private $MATCHING_SEPERATOR = "|";
	private $is_valid = true; //i.e. doesn't only contain essay or random questions.
	private $no_questions = 0; // total no of valid questions
	private $configArray = array(); // config (quiz props) array
	private $quiz_media = array();


	public function __construct(){ 
		$this->component_name = 'mod_quiz';
    } 
	
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
		$quiz = $DB->get_record('quiz', array('id'=>$cm->instance), '*', MUST_EXIST);
		$quizobj = quiz::create($cm->instance, $USER->id);
		$quizobj->preload_questions();
		$quizobj->load_questions();
		$qs = $quizobj->get_questions();



		// get the image from the intro section
		$this->extractThumbnailFromIntro($quiz->intro, $cm->id);
		
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
			    echo get_string('export_quiz_skip_essay', PLUGINNAME).OPPIA_HTML_BR;
				continue;
			}
			
			// skip any random questions
			if($q->qtype == 'random'){
			    echo get_string('export_quiz_skip_random', PLUGINNAME).OPPIA_HTML_BR;
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
		global $USER;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		
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
					    $return_content .= "<li>".strip_tags($sq->questiontext)." -> ".strip_tags($sq->answertext).OPPIA_HTML_LI_END;
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
						$return_content .= OPPIA_HTML_LI_END;
					}
					$return_content .= "</ul>";
				}
				$return_content .= OPPIA_HTML_LI_END;
				
				$i++;
			}
			$return_content .= "</ol>";
			return $return_content;
			
		} catch (moodle_exception $me){
			$this->is_valid = false;
			return null;
		}	
	}
	
	private function extractMedia($question_id, $content){
	
		$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-/,[:space:]]*)[[:space:]]?[\"|\']\]\]))';
	
		preg_match_all($regex, $content, $media_tmp, PREG_OFFSET_CAPTURE);
	
		if(!isset($media_tmp['mediaobject']) || count($media_tmp['mediaobject']) == 0){
			return $content;
		}
	
		for($i=0;$i<count($media_tmp['mediaobject']);$i++){
			$mediajson = json_decode($media_tmp['mediaobject'][$i][0]);
			$toreplace = $media_tmp[0][$i][0];
	
			$content = str_replace($toreplace, "", $content);
			// check all the required attrs exist
			if(!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)){
			    echo get_string('error_media_attributes', PLUGINNAME).OPPIA_HTML_BR;
				die;
			}
				
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$mediajson->digest] = $mediajson;
			$this->quiz_media[$question_id][$mediajson->digest] = $mediajson;
		}
		return str_replace("[[/media]]", "", $content);
	}
	
	function exportQuestionImages (){
		global $USER;
		$cm = get_coursemodule_from_id('quiz', $this->id);

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
		global $USER;
		$cm = get_coursemodule_from_id('quiz', $this->id);

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
	
	function getXML($mod, $counter, &$node, &$xmlDoc, $activity=true){
		
		$act = $this->getActivityNode($xmlDoc, $mod, $counter);
		$this->addLangXMLNodes($xmlDoc, $act, $mod->name, "title");
		
		$temp = $xmlDoc->createElement("content");
		$temp->appendChild($xmlDoc->createCDATASection($this->content));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
		$act->appendChild($temp);
		
		$this->addThumbnailXMLNode($xmlDoc, $act);

		$node->appendChild($act);
	}
	
	function get_is_valid(){
		return $this->is_valid;
	}
	
	function get_no_questions(){
		return $this->no_questions;
	}
}
