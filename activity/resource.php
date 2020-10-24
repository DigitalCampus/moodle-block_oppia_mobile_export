<?php

class mobile_activity_resource extends mobile_activity {
	
	private $act = array();
	private $resource;
	private $resource_filename = null;
	private $resource_image = null;
	private $resource_type = null;

	function generate_md5($file){
		$resourcefile = $this->courseroot."/resources/".$file->get_filename();
		$md5contents = $file->get_filename() . md5_file($resourcefile);

		$this->md5 = md5($md5contents);
	}
	
	function process(){
		global $DB, $CFG, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('resource', $this->id);
		$this->resource = $DB->get_record('resource', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$this->extractResource($context->id, $this->resource->revision);
		
		$eiffilename = extractImageFile($this->resource->intro,
										'mod_resource',
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
		
		if ($this->resource_type == "image/jpeg" && $this->resource_image == null){
			$this->resource_image = "/images/".resizeImage($this->courseroot."/".$this->resource_filename,
						$this->courseroot."/images/".$cm->id,
						$CFG->block_oppia_mobile_export_thumb_width,
						$CFG->block_oppia_mobile_export_thumb_height);
			//DON'T delete original image!
		}
	}
	
	function export2print(){
		$return_content = "";
		$return_content .= "<p>".strip_tags($this->resource->intro)."</p>";
		$return_content .= "<p>[".$this->resource_type ."] Filename: ".$this->resource_filename."</p>";
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
		$description = extractLangs($this->resource->intro);
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
		$temp = $xmlDoc->createElement("location",$this->resource_filename);
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
		$temp->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($this->resource_type));
		$struct->appendChild($temp);
		if($this->resource_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->resource_image));
			$struct->appendChild($temp);
		}	
	}
	
	private function extractResource($contextid,$revision){
		$fs = get_file_storage();
		$files = $fs->get_area_files($contextid, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
		$file = reset($files);
		$resourcefile = $this->courseroot."/resources/".$file->get_filename();
		$file->copy_content_to($resourcefile);


		$finfo = new finfo(FILEINFO_MIME);
		$type = $finfo->file($resourcefile);
		$this->resource_type = substr($type, 0, strpos($type, ';'));
		
		$this->generate_md5($file);
		$this->resource_filename = "/resources/".$file->get_filename();
	}
	
}
?>