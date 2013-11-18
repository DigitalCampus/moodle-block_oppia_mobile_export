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

function extractImageFile($content, $contextid, $contextname, $itemid, $course_root){
	global $CFG;
	//find if any images/links exist
	preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\.\-\_[:space:]]*)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
	if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
		return false;
	}	

	$toreplace = array();
	for($i=0;$i<count($files_tmp['filenames']);$i++){
		$filename = $files_tmp['filenames'][$i][0];
		echo "\t\ttrying file: ".$filename."\n";
		$fullpath = "/$contextid/$contextname/$itemid/$filename";
		//echo "\t\ttrying file: ".$fullpath."\n";
		$fs = get_file_storage();
		$file = $fs->get_file_by_hash(sha1($fullpath));
		$fh = $file->get_content_file_handle();

		$originalfilename = $filename;
		//hack to get around the possibilty of the filename being in a directory structure
		$tmp = explode("/",$filename);
		$filename = $tmp[count($tmp)-1];

		//copy file
		$imgfile = $course_root."/images/".sha1($fullpath);
		$ifh = fopen($imgfile, 'w');

		while(!feof($fh)) {
			$data = fgets($fh, 1024);
			fwrite($ifh, $data);
		}
		fclose($ifh);
		fclose($fh);
		$tr = new StdClass;
		$tr->originalfilename = $originalfilename;
		$tr->filename = sha1($fullpath);
		echo "\t\tFile: ".sha1($fullpath)." successfully exported\n";
	}
	return "images/".sha1($fullpath);
}

function resizeImage($image,$image_new_name, $image_width, $image_height, $transparent=false){
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
	
	switch($size['mime']){
		case 'image/jpeg':
			imagejpeg($image_new,$image_new_name,75);
			break;
		case 'image/png':
			imagepng($image_new,$image_new_name,9);
			break;
		case 'image/gif':
			imagegif($image_new,$image_new_name);
			break;
	}
	

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

function flush_buffers(){
	ob_end_flush();
	@ob_flush();
	@flush();
	ob_start();
}

?>