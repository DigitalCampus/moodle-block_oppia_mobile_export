<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(__FILE__) . '/constants.php');

class ApiHelper {
    private $url;
    private $curl;
    public $version;
    public $name;
    public $max_upload;

    public function init($url) {
        $this->url = $url;
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
    }

    public function fetch_server_info($url) {
        $this->init($url);
        $server_info = $this->exec('server', array(), 'get', false, false);
        $this->version = $server_info->version;
        $this->name = $server_info->name;
        $this->max_upload = $server_info->max_upload;
    }

    public function exec($object, $data_array, $type='post', $api_path=true, $print_error_msg=true) {
        $json = json_encode($data_array);
        // Check if the url already has trailing '/' or not.
        if (substr($this->url, -strlen('/')) === '/') {
            $temp_url = $this->url.($api_path ? "api/v2/" : "").$object."/";
        } else {
            $temp_url = $this->url."/".($api_path ? "api/v2/" : "").$object."/";
        }
        curl_setopt($this->curl, CURLOPT_URL, $temp_url );
        if ($type == 'post') {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $json);
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json) ));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, 1 );
        }
        $data = curl_exec($this->curl);
        $json = json_decode($data);
        $http_status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($http_status != 200 && $http_status != 201 && $print_error_msg) {
            echo '<p style="color:red">'.get_string('error_creating_quiz', PLUGINNAME).' ( status code: ' . $http_status . ')</p>';
        }
        return $json;
    }
}
