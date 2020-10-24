<?php

class mobile_activity_url extends mobile_activity {
	
	private $act = array();
	private $url;
	private $url_image = null;
	

	function generate_md5($activity){
		$md5contents = $activity->intro . $activity->externalurl;
		$this->md5 = md5($md5contents);
	}

	function process(){
		global $DB, $CFG, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('url', $this->id);
		$this->url = $DB->get_record('url', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$this->generate_md5($this->url);
		$eiffilename = extractImageFile($this->url->intro,
										'mod_url',
										'intro',
										'0',
										$context->id,
										$this->courseroot,
										$cm->id); 
	
		if($eiffilename){
			$this->resource_image = "/images/".resizeImage($this->courseroot."/".$eiffilename,
						$this->courseroot."/images/".$cm->id,
						$CFG->block_oppia_mobile_export_thumb_width,
						$CFG->block_oppia_mobile_export_thumb_height);
			//delete original image
			unlink($this->courseroot."/".$eiffilename) or die(get_string('error_file_delete','block_oppia_mobile_export'));
		}
		unset($eiffilename);
	}
	
	function export2print(){
		$return_content = "";
		$return_content .= "<p>".strip_tags($this->url->intro)."</p>";
		$return_content .= "<p>URL: ".$this->url->externalurl."</p>";
		return $return_content;
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		global $DEFAULT_LANG;
		if($activity){
			$struct = $xmlDoc->createElement("activity");
			$struct->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($mod->modname));
			$struct->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
			$struct->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));
			$node->appendChild($struct);
		}
		$title = extractLangs($mod->name);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement("title");
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$struct->appendChild($temp);
			}
		} else {
			$temp = $xmlDoc->createElement("title");
			$temp->appendChild($xmlDoc->createCDATASection(strip_tags($mod->name)));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$struct->appendChild($temp);
		}
		$description = extractLangs($this->url->intro);
		if(is_array($description) && count($description)>0){
			foreach($description as $l=>$d){
				$temp = $xmlDoc->createElement("description");
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($d)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$struct->appendChild($temp);
			}
		} else {
			$description = strip_tags($this->resource->intro);
			if ($description != ""){
				$temp = $xmlDoc->createElement("description");
				$temp->appendChild($xmlDoc->createCDATASection($description));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
				$struct->appendChild($temp);
			}
		}
		$temp = $xmlDoc->createElement("location",$this->url->externalurl);
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
		$struct->appendChild($temp);
		if($this->url_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->url_image));
			$struct->appendChild($temp);
		}	
	}
	
}
?>