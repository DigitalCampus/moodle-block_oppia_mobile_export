<?php

class mobile_activity_quiz extends mobile_activity {

	private $summary;
	private $shortname;
	private $content = "";
	private $MATCHING_SEPERATOR = "|";
	
	function init($shortname,$summary){
		$this->shortname = strip_tags($shortname);
		$this->summary = strip_tags($summary);
	}
	
	function process(){
		global $DB,$USER,$QUIZ_CACHE,$CFG;
		$cm = get_coursemodule_from_id('quiz', $this->id);
		$context = get_context_instance(CONTEXT_MODULE, $cm->id);
		$quizobj = quiz::create($cm->instance, $USER->id);
		$mQH = new MquizHelper();
		$mQH->init($CFG->block_export_mobile_package_mquiz_url);
		if($CFG->block_export_mobile_package_mquiz_api_key == ""){
			echo "Invalid mQuiz username/api_key";
			die;
		}

		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			
			// generate the md5 of the quiz
			$this->md5 = md5(serialize($qs));
			
			// find if this quiz already exists
			$resp = $mQH->exec('quizprops/'.$this->md5, array(),'get');
			
			if(count($resp->quizzes) > 0){
				$quiz_id = $resp->quizzes[0]->quiz_id;	
				$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
				$this->content = json_encode($quiz);
				return;
			}
			
			$props = array();
			$props[0] = array('name' => "digest", 'value' => $this->md5);
			
			//create the Mquiz
			$post = array('title' => $this->shortname." ".$this->section." ".$cm->name,
					'description' => $this->shortname.": ".$this->section.": ".$this->summary.": ".$cm->name,
					'questions' => array(),
					'props' => $props);
			$resp = $mQH->exec('quiz', $post);
			$quiz_uri = $resp->resource_uri;
			$quiz_id = $resp->id;
			
			$i = 1;
			foreach($qs as $q){
				if($q->qtype == 'match'){
					$q->qtype = 'matching';
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
				
				// create question
				$post = array('title' => strip_tags($q->questiontext),
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
						$title = strip_tags($sq->questiontext).$this->MATCHING_SEPERATOR.strip_tags($sq->answertext);
						// add response
						
						$props = array();
						// TODO - figure out how to do feedback for matching questions
						//$props[0] = array('name' => 'feedback', 'value' => '');
						
						$post = array('question' => $question_uri,
								'order' => $j,
								'title' => $title,
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
							$props[0] = array('name' => 'feedback', 'value' => strip_tags($r->feedback));
						}
						
						// if numerical also add a tolerance
						if($q->qtype == 'numerical'){
							$props[1] = array('name' => 'tolerance', 'value' => $r->tolerance);
						}
						
						// add response
						$post = array('question' => $question_uri,
								'order' => $j,
								'title' => strip_tags($r->answer),
								'score' => ($r->fraction * $q->maxmark),
								'props' => $props);
						$resp = $mQH->exec('response', $post);
						$response_uri = $resp->resource_uri;

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
			
			// get the final mquiz object
			$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
			$this->content = json_encode($quiz);
			
		} catch (moodle_exception $me){
			//echo "no questions in this quiz";
		}
	}
	
	function getXML($mod,$counter){
		$structure_xml = "<activity type='".$mod->modname."' id='".$counter."' digest='".$this->md5."'>";
		$structure_xml .= "<title lang='en'>".$mod->name."</title>";
		$structure_xml .= "<content lang='en'>".$this->content."</content>";
		$structure_xml .= "</activity>";
		return $structure_xml;
	}
}

class MquizHelper{
	private $url;
	private $curl;
	
	function init($url){
		$this->url = $url;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
	}
	
	function exec($object, $data_array, $type='post'){
		global $CFG;
		$json = json_encode($data_array);
		$temp_url = $this->url.$object."/";
		if($CFG->block_export_mobile_package_mquiz_api_key != ""){
			$temp_url .= "?format=json";
			$temp_url .= "&username=".$CFG->block_export_mobile_package_mquiz_username;
			$temp_url .= "&api_key=".$CFG->block_export_mobile_package_mquiz_api_key;
		}
		curl_setopt($this->curl, CURLOPT_URL, $temp_url );
		if($type == 'post'){
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $json);
			curl_setopt($this->curl, CURLOPT_POST,1);
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json) ));
		} else {
			curl_setopt($this->curl, CURLOPT_HTTPGET, 1 );
		}
		$data = curl_exec($this->curl);
		$json = json_decode($data);
		return $json;
			
	}
	
}