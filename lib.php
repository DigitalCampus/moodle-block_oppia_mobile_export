<?php 


function deleteDir($dirPath) {
	if (! is_dir($dirPath)) {
		return;
		//throw new InvalidArgumentException('$dirPath must be a directory');
	}
	if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
		$dirPath .= '/';
	}
	$files = glob($dirPath . '*', GLOB_MARK);
	foreach ($files as $file) {
		if (is_dir($file)) {
			deleteDir($file);
		} else {
			unlink($file);
		}
	}
	rmdir($dirPath);
}

function add_or_update_oppiaconfig($modid, $name, $value){
	global $DB;
	
	$record = $DB->get_record('block_oppia_mobile_config', array('modid'=>$modid,'name'=>$name));
	
	if ($record){
		$DB->update_record("block_oppia_mobile_config",array('id'=>$record->id,'value'=>$value));
	} else {
		$DB->insert_record("block_oppia_mobile_config", array('modid'=>$modid,'name'=>$name,'value'=>$value));
	}
}

function get_oppiaconfig($modid,$name){
	global $DB;
	$record = $DB->get_record('block_oppia_mobile_config', array('modid'=>$modid,'name'=>$name));
	if ($record){
		return $record->value;
	} else {
		return 0;
	}
}

function extractLangs($content){
	global $MOBILE_LANGS, $CURRENT_LANG;
	preg_match_all('((lang=[\'|\"](?P<langs>[\w\-]*)[\'|\"]))',$content,$langs_tmp, PREG_OFFSET_CAPTURE);
	$tempLangs = array();
	if(isset($langs_tmp['langs']) && count($langs_tmp['langs']) > 0){
		for($i=0;$i<count($langs_tmp['langs']);$i++){
			$lang = $langs_tmp['langs'][$i][0];
			$lang = str_replace("-","_",$lang);
			$tempLangs[$lang] = true;
		}
	} else {
		return $content;
	}

	$filter = new tomobile_langfilter();
	foreach($tempLangs as $k=>$v){
		$CURRENT_LANG = $k;
		$tempLangs[$k] = $filter->filter($content);
	}
	
	//reverse array
	$tempLangsRev = array_reverse($tempLangs);
	foreach($tempLangsRev as $k=>$v){
		$MOBILE_LANGS[$k] = true;
	}

	return $tempLangsRev;
}

function extractImageFile($content, $component, $filearea, $itemid, $contextid, $course_root){
	global $CFG;
	//find if any images/links exist
	preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\.\-\_[:space:]]*)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
	if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
		return false;
	}	

	$toreplace = array();
	for($i=0;$i<count($files_tmp['filenames']);$i++){
		$filename = $files_tmp['filenames'][$i][0];
		if($CFG->block_oppia_mobile_export_debug){
			echo "trying file: ".$filename."<br/>";
		}
		
		
		$fullpath = "/$contextid/$component/$filearea/$itemid/$filename";
		$fs = get_file_storage();
		$fileinfo = array(
				'component' => $component,   
				'filearea' => $filearea,     
				'itemid' => $itemid,               
				'contextid' => $contextid,
				'filepath' => '/',           
				'filename' => $filename);
		$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
				$fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
		
		if ($file) {
			$imgfile = $course_root."/images/".sha1($fullpath);
			$file->copy_content_to($imgfile);
		} else {
			if($CFG->block_oppia_mobile_export_debug){
				echo "<span style='color:red'>Extract image file not found</span><br/>";
			}
		}
		
		$tr = new StdClass;
		$tr->originalfilename = $filename;
		$tr->filename = sha1($fullpath);
		if($CFG->block_oppia_mobile_export_debug){
			echo "Extract Image: ".$filename." successfully exported<br/>";
		}
	}
	return "images/".sha1($fullpath);
}

function resizeImage($image,$image_new_name, $image_width, $image_height, $transparent=false){
	global $CFG;
	
	if($CFG->block_oppia_mobile_export_thumb_crop){
		resizeImageCrop($image,$image_new_name, $image_width, $image_height, $transparent);
	} else {
		resizeImageScale($image,$image_new_name, $image_width, $image_height, $transparent);
	}
	
}

function resizeImageScale($image,$image_new_name, $image_width, $image_height, $transparent=false){
	global $CFG;
	$size=GetimageSize($image);
	$orig_w = $size[0];
	$orig_h = $size[1];
	$ratio_src = $orig_w/$orig_h;
	
	$ratio_target = $image_width/$image_height;
	
	$image_new = ImageCreateTrueColor($image_width, $image_height);

	
	if(!$transparent){
		$bg_colour = imagecolorallocate($image_new, 
						$CFG->block_oppia_mobile_export_thumb_bg_r, 
						$CFG->block_oppia_mobile_export_thumb_bg_g, 
						$CFG->block_oppia_mobile_export_thumb_bg_b);
		imagefill($image_new, 0, 0, $bg_colour);
	} else {
		imagealphablending( $image_new, false );
		imagesavealpha($image_new, true);
	}

	
	switch($size['mime']){
		case 'image/jpeg':
			$image_src = imagecreatefromjpeg($image);
			break;
		case 'image/png':
			$image_src = imagecreatefrompng($image);
			break;
		case 'image/gif':
			$image_src = imagecreatefromgif($image);
			break;
	}
	
	if($orig_h > $orig_w || $ratio_src < $ratio_target){
		$border = floor(($image_width - ($image_height*$orig_w/$orig_h))/2);
		imagecopyresampled($image_new, $image_src, $border, 0, 0, 0, $image_width -($border*2), $image_height , $orig_w, $orig_h);
	} else {
		$border = floor(($image_height - ($image_width*$orig_h/$orig_w))/2);
		imagecopyresampled($image_new, $image_src, 0, $border, 0, 0, $image_width , $image_height- ($border*2) , $orig_w, $orig_h);
	} 
	
	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
}

function resizeImageCrop($image,$image_new_name, $image_width, $image_height, $transparent=false){
	global $CFG;
	$size=GetimageSize($image);
	$orig_w = $size[0];
	$orig_h = $size[1];
	$ratio_src = $orig_w/$orig_h;

	$ratio_target = $image_width/$image_height;

	$image_new = ImageCreateTrueColor($image_width, $image_height);


	if(!$transparent){
		$bg_colour = imagecolorallocate($image_new,
				$CFG->block_oppia_mobile_export_thumb_bg_r,
				$CFG->block_oppia_mobile_export_thumb_bg_g,
				$CFG->block_oppia_mobile_export_thumb_bg_b);
		imagefill($image_new, 0, 0, $bg_colour);
	} else {
		imagealphablending( $image_new, false );
		imagesavealpha($image_new, true);
	}


	switch($size['mime']){
		case 'image/jpeg':
			$image_src = imagecreatefromjpeg($image);
			break;
		case 'image/png':
			$image_src = imagecreatefrompng($image);
			break;
		case 'image/gif':
			$image_src = imagecreatefromgif($image);
			break;
	}

	
	if($ratio_src > $ratio_target){
		$crop = floor(($orig_w - ($orig_h*$image_width/$image_height))/2);
		imagecopyresampled($image_new, $image_src, 0, 0, $crop, 0, $image_width, $image_height, $orig_w-(2*$crop), $orig_h);
	} else {
		$crop = floor(($orig_h - ($orig_w*$image_height/$image_width))/2);
		imagecopyresampled($image_new, $image_src, 0, 0,  0, $crop,  $image_width, $image_height, $orig_w, $orig_h -(2*$crop));
	}

	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
}

function Zip($source, $destination){
	if (!extension_loaded('zip') || !file_exists($source)) {
		return false;
	}

	$zip = new ZipArchive();
	if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
		return false;
	}

	$source = str_replace('\\', '/', realpath($source));

	if (is_dir($source) === true){
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

		foreach ($files as $file){
			$file = str_replace('\\', '/', realpath($file));

			if (is_dir($file) === true)
			{
				//echo "adding dir $file\n";
				//$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
			}
			else if (is_file($file) === true)
			{
				$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
			}
		}
	} else if (is_file($source) === true){
		$zip->addFromString(basename($source), file_get_contents($source));
	}

	return $zip->close();
}


function libxml_display_error($error)
{
	$return = "<br/>\n";
	switch ($error->level) {
		case LIBXML_ERR_WARNING:
			$return .= "<b>Warning $error->code</b>: ";
			break;
		case LIBXML_ERR_ERROR:
			$return .= "<b>Error $error->code</b>: ";
			break;
		case LIBXML_ERR_FATAL:
			$return .= "<b>Fatal Error $error->code</b>: ";
			break;
	}
	$return .= trim($error->message);
	if ($error->file) {
		$return .= " in <b>$error->file</b>";
	}
	$return .= " on line <b>$error->line</b>\n";

	return $return;
}

function libxml_display_errors() {
	$errors = libxml_get_errors();
	foreach ($errors as $error) {
		print libxml_display_error($error);
	}
	libxml_clear_errors();
}

function flush_buffers(){
	ob_end_flush();
	@ob_flush();
	@flush();
	ob_start();
}

function recurse_copy($src,$dst) {
	$dir = opendir($src);
	@mkdir($dst);
	while(false !== ( $file = readdir($dir)) ) {
		if (( $file != '.' ) && ( $file != '..' )) {
			if ( is_dir($src . '/' . $file) ) {
				recurse_copy($src . '/' . $file,$dst . '/' . $file);
			}
			else {
				copy($src . '/' . $file,$dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}

?>