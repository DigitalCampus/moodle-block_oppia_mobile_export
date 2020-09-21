<?php 

abstract class mobile_activity {
	
	public $courseroot;
	public $id;
	public $section;
	public $md5;
	
	public $thumbnail_image = null;
	public $component_name;

	abstract function process();
	abstract function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc);
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
				unlink($this->courseroot."/".$thumbnail) or die(get_string('error_file_delete','block_oppia_mobile_export'));
			}
		} else{
			$link = $CFG->wwwroot."/course/modedit.php?return=0&sr=0&update=".$module_id;
			echo "<span style='color:red'>".get_string('error_edit_page','block_oppia_mobile_export', $link)."</span><br/>";
		}
	}
}

