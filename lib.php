<?php 

const regex_forbidden_dir_chars = '([\\/?%*:|"<>\.[:space:]]+)';
const regex_forbidden_tag_chars = '([^a-zA-z0-9,\_]+)';
const regex_html_entities = '(&nbsp;|&amp;|&quot;)';
const regex_resource_extensions = '/\.(mp3|mp4|avi)/';
const basic_html_tags = '<strong><b><i><em>';


function deleteDir($dirPath) {
	if (! is_dir($dirPath)) {
		return;
		//throw new InvalidArgumentException('$dirPath must be a directory');
	}
	if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
		$dirPath .= '/';
	}

	$it = new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it,
			RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) {
		if ($file->getFilename() === '.' || $file->getFilename() === '..') {
			continue;
		}
		if ($file->isDir()){
			rmdir($file->getRealPath());
		} else {
			unlink($file->getRealPath());
		}
	}
	rmdir($dirPath);
}

function add_or_update_oppiaconfig($modid, $name, $value, $servid="default"){
	global $DB;
	
	$record = $DB->get_record('block_oppia_mobile_config', 
		array('modid'=>$modid,'name'=>$name,'serverid'=>$servid));
	
	if ($record){
		$DB->update_record("block_oppia_mobile_config",
			array('id'=>$record->id,'value'=>$value));
	} else {
		$DB->insert_record("block_oppia_mobile_config", 
			array('modid'=>$modid,'name'=>$name,'value'=>$value,'serverid'=>$servid));
	}
}

function get_oppiaconfig($modid, $name, $default, $servid="default"){
	global $DB;
	$record = $DB->get_record('block_oppia_mobile_config', 
		array('modid'=>$modid,'name'=>$name,'serverid'=>$servid));
	if ($record){
		return $record->value;
	} else {
		//Try if there is a non-server value saved
		$record = $DB->get_record('block_oppia_mobile_config', 
			array('modid'=>$modid,'name'=>$name));
		if ($record){
			return $record->value;
		} else {
			return $default;	
		}
	}
}

function get_oppiaservers(){
	global $DB, $USER;
	return $DB->get_records('block_oppia_mobile_server', array('moodleuserid'=>$USER->id));
}

function add_publishing_log($server, $userid, $courseid, $action, $data){
    global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $DB->insert_record("block_oppia_publish_log",
        array('server'=>$server,
                'logdatetime'=>$timestamp,
                'moodleuserid'=>$userid,
                'courseid'=>$courseid,
                'action'=>$action,
                'data'=>$data)
        );
}

function extractLangs($content, $asJSON = false, $strip_tags = false){
	global $MOBILE_LANGS, $CURRENT_LANG, $DEFAULT_LANG;
	preg_match_all('((lang=[\'|\"](?P<langs>[\w\-]*)[\'|\"]))',$content,$langs_tmp, PREG_OFFSET_CAPTURE);
	$tempLangs = array();
	if(isset($langs_tmp['langs']) && count($langs_tmp['langs']) > 0){
		for($i=0;$i<count($langs_tmp['langs']);$i++){
			$lang = $langs_tmp['langs'][$i][0];
			$lang = str_replace("-","_",$lang);
			$tempLangs[$lang] = true;
		}
	} else if (!$asJSON){
		return $content;
	} else {
		$json = new stdClass;
		$json->{$DEFAULT_LANG} = trim(strip_tags($content, basic_html_tags));
		return json_encode($json);
	}

	$filter = new tomobile_langfilter();
	foreach($tempLangs as $k=>$v){
		$CURRENT_LANG = $k;
		if ($strip_tags){
			$tempLangs[$k] = trim(strip_tags($filter->filter($content), basic_html_tags));
		} else {
			$tempLangs[$k] = trim($filter->filter($content));
		}
	}
	
	//reverse array
	$tempLangsRev = array_reverse($tempLangs);
	foreach($tempLangsRev as $k=>$v){
		$MOBILE_LANGS[$k] = true;
	}

	if ($asJSON){
		return json_encode($tempLangsRev);
	} else {
		return $tempLangsRev;
	}
}

function cleanHTMLEntities($text, $replace_br=false){
	$cleantext = trim($text);
	if ($replace_br){
		$cleantext = preg_replace("(<br[[:space:]]*/?>)", "\n", $cleantext);
	}
	$cleantext = preg_replace(regex_html_entities, " ", $cleantext);
	return $cleantext;
}

function cleanTagList($tags){
	$cleantags = trim($tags);
	$cleantags = preg_replace('([[:space:]]*\,[[:space:]])', ',', $tags);
	$cleantags = preg_replace(regex_forbidden_tag_chars, "-", $cleantags);
	
	if (strlen($cleantags) == 0) return $cleantags;
	$strStart = ($cleantags[0] == ',') ? 1 : 0; //avoid first colon
	$strEnd = $strStart + (($cleantags[strlen($cleantags)-1] == ',') ? 1 : 0); //avoid last colon
	$cleantags = substr($cleantags, $strStart, strlen($cleantags) - $strEnd);
	
	return $cleantags;
}

function cleanShortname($shortname){
	$shortname = trim($shortname);
	$shortname = preg_replace(regex_forbidden_dir_chars, "-", $shortname);
	$shortname = preg_replace('(\-+)', "-", $shortname); //clean duplicated hyphens
	return $shortname;
}


function extractImageFile($content, $component, $filearea, $itemid, $contextid, $course_root, $cmid){
	global $CFG;
	//find if any images/links exist
	//preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\.\-\_[:space:]]*)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
	preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[^\"\']*)))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
	
	if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
		return false;
	}	

	$lastimg = false;
	$toreplace = array();
	for($i=0;$i<count($files_tmp['filenames']);$i++){

		$filename = trim($files_tmp['filenames'][$i][0]);
		if($CFG->block_oppia_mobile_export_debug){
			echo "Attempting to export file: ".urldecode($filename)."<br/>";
		}
		$fs = get_file_storage();
		$fileinfo = array(
				'component' => $component,   
				'filearea' => $filearea,     
				'itemid' => $itemid,               
				'contextid' => $contextid,
				'filepath' => '/',           
				'filename' => $filename);
		$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
				$fileinfo['itemid'], $fileinfo['filepath'], urldecode($fileinfo['filename']));
		$result = copyFile($file, $component, $filearea, $itemid, $contextid, $course_root, $cmid);
		if ($result != false){
			$lastimg = $result;
		}
		
	}
	return $lastimg;
}

function copyFile($file, $component, $filearea, $itemid, $contextid, $course_root, $cmid){
	global $CFG;

	$is_image = true;
	if ($file) {

			$filename = $file->get_filename();
			$fullpath = "/$contextid/$component/$filearea/$itemid/$filename";
			$sha1 = sha1($fullpath);
			if (preg_match(regex_resource_extensions, $filename) > 0){
				$is_image = false;
				$filedest = "/resources/".$filename;
			}
			else{
				$filedest = "/images/".$sha1;
			}
			
			$result = $file->copy_content_to($course_root.$filedest);

	} else {
		$link = $CFG->wwwroot."/course/modedit.php?return=0&sr=0&update=".$cmid;
		$message = 'error_'.($is_image?'image':'file').'_edit_page';
		echo "<span style='color:red'>".get_string($message,'block_oppia_mobile_export',$link)."</span><br/>";
		return false;
	}
	
	$tr = new StdClass;
	$tr->originalfilename = $filename;
	$tr->filename = sha1($fullpath);
	if($CFG->block_oppia_mobile_export_debug){
		$message = 'export_'.($is_image?'image':'file').'_success';
		echo get_string($message,'block_oppia_mobile_export',urldecode($filename))."<br/>";

	}
	return ($is_image ? $filedest : false);
}


function resizeImage($image,$image_new_name, $image_width, $image_height, $transparent=false){
	global $CFG;
	
	if($CFG->block_oppia_mobile_export_thumb_crop){
		$filename = resizeImageCrop($image,$image_new_name, $image_width, $image_height, $transparent);
	} else {
		$filename = resizeImageScale($image,$image_new_name, $image_width, $image_height, $transparent);
	}
	// just return the last part of the filename (name + extn... not the dir path)
	$pieces = explode("/",$filename);
	
	return $pieces[count($pieces)-1];
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
	$image_new_name = $image_new_name.".png";
	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
	return $image_new_name;
	
}

function resizeImageCrop($image,$image_new_name, $image_width, $image_height, $transparent=false){
	global $CFG;
	if (!file_exists($image)){
		return false;
	}
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

	$image_new_name = $image_new_name.".png";
	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
	return $image_new_name;
}

function Zip($source, $destination){
	if (!extension_loaded('zip') || !file_exists($source)) {
		echo '<span style="color:red;">Unable to load Zip extension (is it correctly installed and configured in the Moodle server?)</span><br/>';
		return false;
	}

	$zip = new ZipArchive();
	if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
		echo '<span style="color:red;">Couldn\'t create Zip archive</span><br/>';
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

function create_default_course_gamification(&$xmlDoc,&$node){
	$default_gamification_events = [
			'quiz_first_attempt' => 20,                     
			'quiz_attempt' => 10,                          
			'quiz_first_attempt_threshold'  => 100,         
			'quiz_first_attempt_bonus' => 50,               
			'activity_completed' => 10,                    
			'media_started' => 20,                         
			'media_playing_interval' => 30,                
			'media_playing_points_per_interval' => 5,      
			'media_max_points' => 200,                     
			'course_downloaded' => 50
			
		];
	foreach($default_gamification_events as $event => $points){
		$temp = $xmlDoc->createElement("event");
		$temp->appendChild($xmlDoc->createAttribute("name"))->appendChild($xmlDoc->createTextNode($event));
		$temp->appendChild($xmlDoc->createTextNode($points));
		$node->appendChild($temp);
		
	}
}

?>
