<?php

class mobile_activity_quiz extends mobile_activity {

	private $mquizusername;
	private $mquizpassword;
	private $summary;
	private $shortname;
	private $content = "";
	private $MATCHING_SEPERATOR = "|";
	
	function init($user,$pass,$shortname,$summary){
		$this->mquizusername = $user;
		$this->mquizpassword = $pass;
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
		// login user to get api_key
		$post = array('username'=>$this->mquizusername, 'password'=>$this->mquizpassword);
		$resp = $mQH->exec('user',$post);
		if(isset($resp->api_key)){
			$api_key = $resp->api_key;
		} else {
			echo "Invalid mQuiz username/password";
			die;
		}
		$mQH->setCredentials($this->mquizusername, $api_key);

		try {
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$qs = $quizobj->get_questions();
			
			//create the Mquiz
			$post = array('title' => $this->shortname." ".$this->section." ".$cm->name,
					'description' => $this->shortname.": ".$this->section.": ".$this->summary.": ".$cm->name,
					'questions' => array(),
					'props' => array());
			$resp = $mQH->exec('quiz', $post);
			$quiz_uri = $resp->resource_uri;
			echo $quiz_uri."\n";
			
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
				echo json_encode($post);
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
						$props[0] = array('name' => 'feedback', 'value' => '');
						
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
						$props[0] = array('name' => 'feedback', 'value' => strip_tags($r->feedback));
						
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
			
		} catch (moodle_exception $me){
			//echo "no questions in this quiz";
		}
		$this->content = "";
		$this->md5 = md5($this->content);
	}
	
	function getXML($mod,$counter){
		$structure_xml = "<activity type='".$mod->modname."' id='".$counter."' digest='".$this->md5."'>";
		$structure_xml .= "<title lang='en'>".$mod->name."</title>";
		$structure_xml .= "<content lang='en'>".$this->content."</content>";
		$structure_xml .= "</activity>";
	}
}

class MquizHelper{
	private $url;
	private $curl;
	private $user;
	private $api_key = "";
	
	function init($url){
		$this->url = $url;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($this->curl, CURLOPT_POST,           1 );
	}
	
	function setCredentials($user,$api_key){
		$this->user = $user;
		$this->api_key = $api_key;
	}
	function exec($object, $data_array){
		$json = json_encode($data_array);
		$temp_url = $this->url.$object."/";
		if($this->api_key != ""){
			$temp_url .= "?username=".$this->user;
			$temp_url .= "&api_key=".$this->api_key;
		}
		curl_setopt($this->curl, CURLOPT_URL,            $temp_url );
		curl_setopt($this->curl, CURLOPT_POSTFIELDS,     $json);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json) ));
		$data = curl_exec($this->curl);
		$json = json_decode($data);
		return $json;
			
	}
	
}