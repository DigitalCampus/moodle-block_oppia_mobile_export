<?php

class mobile_activity_quiz extends mobile_activity {

	private $mquizusername;
	private $mquizpassword;
	private $summary;
	private $shortname;
	private $content = "";
	
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
		$mQH->init($CFG->block_export_mobile_package_mquiz_url,$this->mquizusername,$this->mquizpassword);
		
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
				print_r($q);
				
				// create question
				$post = array('title' => strip_tags($q->questiontext),
						'type' => $q->qtype,
						'responses' => array(),
						'props' => array());
				
				$resp = $mQH->exec('question', $post);
				print_r($resp);
				$question_uri = $resp->resource_uri;
				
				// add max score property
				$post = array('question' => $question_uri,
						'name' => "maxscore",
						'value' => $q->maxmark);
				$resp = $mQH->exec('questionprops', $post);
				
				$j = 1;
				// add responses
				foreach($q->options->answers as $r){
					// add response
					$post = array('question' => $question_uri,
							'order' => $j,
							'title' => strip_tags($r->answer),
							'score' => ($r->fraction * $q->maxmark),
							'props' => array());
					$resp = $mQH->exec('response', $post);
					$response_uri = $resp->resource_uri;
					
					// add response feedback
					$post = array('response' => $response_uri,
							'name' => 'feedback',
							'value' => strip_tags($r->feedback));
					$resp = $mQH->exec('responseprops', $post);
					$j++;
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
	
	function init($url, $user, $pass){
		$this->url = $url;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($this->curl, CURLOPT_POST,           1 );
		curl_setopt($this->curl, CURLOPT_USERPWD,	     "$user:$pass");
	}
	
	function exec($object, $data_array){
		$json = json_encode($data_array);
		$temp_url = $this->url.$object."/";
		curl_setopt($this->curl, CURLOPT_URL,            $temp_url );
		curl_setopt($this->curl, CURLOPT_POSTFIELDS,     $json);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json),'Expects: application/json' ));
		$data = curl_exec($this->curl);
		$json = json_decode($data);
		return $json;
			
	}
	
}