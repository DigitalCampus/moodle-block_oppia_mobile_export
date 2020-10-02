<?php 

require_once(dirname(__FILE__) . '/constants.php');

class QuizHelper{
	private $connection;
	private $curl;
	
	function init($connection){
		$this->connection = $connection;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
	}
	
	function exec($object, $data_array, $type='post', $api_path=true, $print_error_msg=true){
		
		$json = json_encode($data_array);
		// Check if the url already has trailing '/' or not
		if (substr($this->connection->url, -strlen('/'))==='/'){ 
			$temp_url = $this->connection->url.($api_path ? "api/v1/" : "").$object."/";
		} else {
			$temp_url = $this->connection->url."/".($api_path ? "api/v1/" : "").$object."/";
		}
		$temp_url .= "?format=json";
		$temp_url .= "&username=".$this->connection->username;
		$temp_url .= "&api_key=".$this->connection->apikey;
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
		$http_status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		if ($http_status != 200 && $http_status != 201 && $print_error_msg){
			echo '<p style="color:red">'.get_string('error_creating_quiz', PLUGINNAME).' ( status code: ' . $http_status . ')</p>';
		}
		return $json;
			
	}
	
}

?>