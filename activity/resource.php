<?php

class MobileActivityResource extends MobileActivity {
	
	private $resource;
	private $resource_filename = null;
	private $resource_type = null;
    

    public function __construct($params=array()){ 
    	parent::__construct($params);
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

		$filename = $this->filter_filename($file->get_filename());
		$resourcefile = $this->courseroot."/resources/".$filename;
		$success = $file->copy_content_to($resourcefile);

		$finfo = new finfo(FILEINFO_MIME);
		$type = $finfo->file($resourcefile);
		$this->resource_type = substr($type, 0, strpos($type, ';'));
		
		$this->generate_md5($file);
		$this->resource_filename = "/resources/".$filename;
	}

	private function filter_filename($filename){
		return preg_replace(
	        '~
	        [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
	        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
	        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
	        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
	        ~x',
        	'_', $filename);
	}
	
}
?>