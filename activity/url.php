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

class MobileActivityUrl extends MobileActivity {
    
    private $url;


    public function __construct($params=array()){ 
        parent::__construct($params);
        $this->component_name = 'mod_url';
    }
    

    function generate_md5($activity){
        $md5contents = $activity->intro . $activity->externalurl;
        $this->md5 = md5($md5contents);
    }


    function process(){
        global $DB;
        $cm= get_coursemodule_from_id('url', $this->id);
        $this->url = $DB->get_record('url', array('id'=>$cm->instance), '*', MUST_EXIST);
        $this->generate_md5($this->url);
        // get the image from the intro section
        $this->extractThumbnailFromIntro($this->url->intro, $cm->id);
    }
    
    
    function getXML($mod, $counter, &$node, &$xmlDoc, $activity=true){
        global $DEFAULT_LANG;
        
        if(!$activity){
            return;
        }
        
        $act = $this->getActivityNode($xmlDoc, $mod, $counter);
        $this->addLangXMLNodes($xmlDoc, $act, $mod->name, "title");
        $this->addLangXMLNodes($xmlDoc, $act, $this->url->intro, "description");

        $temp = $xmlDoc->createElement("location",$this->url->externalurl);
        $temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
        $act->appendChild($temp);

        $this->addThumbnailXMLNode($xmlDoc, $act);
        $node->appendChild($act);
    }

    function get_no_questions() {
        return null;
    }
}
?>
