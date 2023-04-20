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
        $serverinfo = $this->exec('server', array(), 'get', false, false);
        $this->version = $serverinfo->version;
        $this->name = $serverinfo->name;
        $this->max_upload = $serverinfo->max_upload;
    }

    public function exec($object, $dataarray, $type='post', $apipath, $printerrormsg) {
        $json = json_encode($dataarray);
        // Check if the url already has trailing '/' or not.
        if (substr($this->url, -strlen('/')) === '/') {
            $tempurl = $this->url.($apipath ? "api/v2/" : "").$object."/";
        } else {
            $tempurl = $this->url."/".($apipath ? "api/v2/" : "").$object."/";
        }
        curl_setopt($this->curl, CURLOPT_URL, $tempurl );
        if ($type == 'post') {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $json);
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json) ));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, 1 );
        }
        $data = curl_exec($this->curl);
        $json = json_decode($data);
        $httpstatus = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($httpstatus != 200 && $httpstatus != 201 && $printerrormsg) {
            echo '<p style="color:red">'.get_string('error_creating_quiz', PLUGINNAME).' ( status code: ' . $httpstatus . ')</p>';
        }
        return $json;
    }
}
