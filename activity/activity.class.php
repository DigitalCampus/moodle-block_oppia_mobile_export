<?php 

abstract class MobileActivity {
	
	public $courseroot;
	public $id;
	public $section;
	public $md5;
	
	public $thumbnail_image = null;
	public $component_name;

	abstract function process();
	abstract function getXML($mod,$counter,&$node,&$xmlDoc,$activity=true);
	abstract function export2print();


	public function extractThumbnailFromIntro($content, $module_id){
		$this->extractThumbnail($content, $module_id, 'intro');
	}

	public function extractThumbnailFromContents($content, $module_id){
		$this->extractThumbnail($content, $module_id, 'content');
	}

	public function extractThumbnail($content, $module_id, $file_area){

		$context = context_module::instance($module_id);
		// get the image from the intro section
		$thumbnail = extractImageFile($content, $this->component_name, $file_area,
										0, $context->id, $this->courseroot, $module_id);

		if($thumbnail){
			$this->saveResizedThumbnail($thumbnail, $module_id);
		}
	}

	public function saveResizedThumbnail($thumbnail, $module_id, $keep_original=false){
		global $CFG;

		$this->thumbnail_image = $thumbnail;
		$imageResized = resizeImage($this->courseroot . "/". $this->thumbnail_image,
									$this->courseroot."/images/".$module_id,
									$CFG->block_oppia_mobile_export_thumb_width,
									$CFG->block_oppia_mobile_export_thumb_height);

		if ($imageResized){
			$this->thumbnail_image = "/images/" . $imageResized;
			if (!$keep_original){
				unlink($this->courseroot."/".$thumbnail) or die(get_string('error_file_delete', PLUGINNAME));
			}
		} else{
			$link = $CFG->wwwroot."/course/modedit.php?return=0&sr=0&update=".$module_id;
			echo '<span class="export-error">'.get_string('error_edit_page', PLUGINNAME, $link).'</span><br/>';
		}
	}


	protected function getActivityNode($xmlDoc, $module, $counter){
		$act = $xmlDoc->createElement("activity");
		$act->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($module->modname));
		$act->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
		$act->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));

		return $act;
	}

	protected function addTitleXMLNodes($xmlDoc, $module, $activity_node){
		$this->addLangXMLNodes($xmlDoc, $activity_node, $module->name, "title");
	}

	protected function addDescriptionXMLNodes($xmlDoc, $module, $activity_node){
		$this->addLangXMLNodes($xmlDoc, $activity_node, $module->intro, "description");
	}

	protected function addLangXMLNodes($xmlDoc, $activity_node, $content, $property_name){
		global $DEFAULT_LANG;

		$title = extractLangs($content);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement($property_name);
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$activity_node->appendChild($temp);
			}
		} else {
			$title = strip_tags($content);
			if ($title != ""){
				$temp = $xmlDoc->createElement($property_name);
				$temp->appendChild($xmlDoc->createCDATASection(strip_tags($title)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
				$activity_node->appendChild($temp);
			}
		}
	}

	protected function addThumbnailXMLNode($xmlDoc, $activity_node){

		if($this->thumbnail_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->thumbnail_image));
			$activity_node->appendChild($temp);
		}
	}
}

