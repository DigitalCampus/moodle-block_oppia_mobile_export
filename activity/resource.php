<?php

class MobileActivityResource extends MobileActivity {
	
	private $resource;
	private $resource_filename = null;
	private $resource_type = null;
    

    public function __construct(){ 
		$this->component_name = 'mod_resource';
    }
    

	function generate_md5($file){
		$resourcefile = $this->courseroot."/resources/".$file->get_filename();
		$md5contents = $file->get_filename() . md5_file($resourcefile);

		$this->md5 = md5($md5contents);
	}
	

	function process(){
		global $DB;
		$cm = get_coursemodule_from_id('resource', $this->id);
		$this->resource = $DB->get_record('resource', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$this->extractResource($context->id);
	
		// get the image from the intro section
        $this->extractThumbnailFromIntro($this->resource->intro, $cm->id);
		
		if ($this->resource_type == "image/jpeg" && $this->thumbnail_image == null){
			$this->saveResizedThumbnail($this->resource_filename, $cm->id, true);
		}
	}
	
	function export2print(){
		$return_content = "";
		$return_content .= "<p>".strip_tags($this->resource->intro)."</p>";
		$return_content .= "<p>[".$this->resource_type ."] Filename: ".$this->resource_filename."</p>";
		return $return_content;
	}
	
	function getXML($mod, $counter, &$node, &$xmlDoc, $activity=true){
		global $DEFAULT_LANG;
		
		if(!$activity){
			return;
		}

		$act = $this->getActivityNode($xmlDoc, $mod, $counter);
		$this->addLangXMLNodes($xmlDoc, $act, $mod->name, "title");
		$this->addLangXMLNodes($xmlDoc, $act, $this->resource->intro, "description");
		$this->addThumbnailXMLNode($xmlDoc, $act);

		$temp = $xmlDoc->createElement("location",$this->resource_filename);
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
		$temp->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($this->resource_type));
		$act->appendChild($temp);

		$node->appendChild($act);
	}
	
	private function extractResource($contextid){
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