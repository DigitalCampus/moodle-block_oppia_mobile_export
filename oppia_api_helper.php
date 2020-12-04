<?php 

require_once(dirname(__FILE__) . '/constants.php');

const VERSION_REGEX = '/^v([0-9])+\.([0-9]+)\.([0-9]+)[\-_a-zA-Z0-9\.]*$/';
const QUIZ_EXPORT_MINVERSION_MINOR = 9;
const QUIZ_EXPORT_MINVERSION_SUB = 8;

class ApiHelper{
	private $connection;
	private $curl;
	public $version;
	
	function init($connection){
		$this->connection = $connection;
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
	}


	function fetchServerVersion($server_connection){
		$this->init($server_connection);
		$server_info = $this->exec('server', array(),'get', false, false);
		$this->version = $server_info->version;

	}

	function getExportMethod(){


		if ($this->version == null || $this->version==''){
			return false;
		}

		$result = preg_match(VERSION_REGEX, $this->version, $version_nums);
		if ($result >=0 && !empty($version_nums)){
			if (!empty($version_nums) && (
				( (int) $version_nums[1] >= 0) || //major version check (>0.x.x)
				( (int) $version_nums[2] >= $QUIZ_EXPORT_MINVERSION_MINOR) || //minor version check (>=0.9.x)
				( (int) $version_nums[3] >= $QUIZ_EXPORT_MINVERSION_SUB) //sub version check (>=0.9.8)
				)){
				return 'local';
			}
			else{
				return 'server';	
			}
			
		}
		//If there was some error, we return a false value as a fallback
		return false;
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