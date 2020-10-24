<?php

class MobileActivityUrl extends MobileActivity {
	
	private $url;


	public function __construct(){ 
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
	
	function export2print(){
		return "<p>".strip_tags($this->url->intro)."</p><p>URL: ".$this->url->externalurl."</p>";
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
	
}
?>
