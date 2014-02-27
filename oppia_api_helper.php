<?php 

class QuizHelper{
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
		if($CFG->block_oppia_mobile_export_api_key != ""){
			$temp_url .= "?format=json";
			$temp_url .= "&username=".$CFG->block_oppia_mobile_export_username;
			$temp_url .= "&api_key=".$CFG->block_oppia_mobile_export_api_key;
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
		//echo $data."<hr/>";
		$json = json_decode($data);
		return $json;
			
	}
	
}

?>