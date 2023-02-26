<?php 

require_once(dirname(__FILE__) . '/constants.php');

const REGEX_FORBIDDEN_DIR_CHARS = '([\\/?%*:|"<>\. ]+)'; //Catches any sequence of forbidden UNIX dir chars
const REGEX_FORBIDDEN_TAG_CHARS = '([^a-zA-z0-9\_]+)'; //Catches any character not allowed inside an XML tag
const REGEX_HTML_ENTITIES = '(&nbsp;|&amp;|&quot;)'; //Catches HTML entities after urlencoding text contents
const REGEX_RESOURCE_EXTENSIONS = '/\.(mp3|mp4|avi)/'; //Catches media resource supported extensions
const REGEX_IMAGE_EXTENSIONS = '/\.(png|jpg|jpeg|gif)/'; //Catches image supported extensions
const BASIC_HTML_TAGS = '<strong><b><i><em>'; // Basic HTML tags allowed for the strip_tags() method 
const REGEX_LANGS = '((lang=[\'|\"](?P<langs>[\w\-]*)[\'|\"]))'; //Extracts the lang attribute
const REGEX_BR = '(<br[[:space:]]*/?>)'; //Catches <br> tags in all its possible ways

const MEDIAFILE_PREFIX = '@@PLUGINFILE@@';
const MEDIAFILE_REGEX = '(('.MEDIAFILE_PREFIX.'/(?P<filenames>[^\"\'\?<>]*)))'; // Catches the filenames for Moodle embeded files in the content
// Detects any number of spaces or <br> or <p> tags (in any of its forms) 
const SPACES_REGEX = '([[:space:]]|\<br\/?[[:space:]]*\>|\<\/?p\>)*';
// Captures the old media embed method code ( [[media object="..."]])
const EMBED_MEDIA_REGEX = '((\[\['.SPACES_REGEX . 'media'.SPACES_REGEX.'object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-\/,[:space:]]*)([[:space:]]|\<br\/?[[:space:]]*\>)*[\"|\']'.SPACES_REGEX.'\]\]))';
// Captures the filename of images inside old media embed method code ( [[media object="..."]])
const EMBED_MEDIA_IMAGE_REGEX = '(\]\]'.SPACES_REGEX.'\<img[[:space:]]src=[\"|\\\']images/(?P<filenames>[\w\W_\-.]*?)[\"|\\\'])';
const COURSE_EXPORT_FILEAREA = 'course_export';

function deleteDir($dirPath) {
	if (! is_dir($dirPath)) {
		return;
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
	
	if ($servid !== null){
		$record = $DB->get_record_select(OPPIA_CONFIG_TABLE,
	    "modid=$modid and `name`='$name' and serverid='$servid'");	
	}
	else{
		$record = $DB->get_record(OPPIA_CONFIG_TABLE, array('modid'=>$modid,'name'=>$name));
	}
	
	if ($record){
		$DB->update_record(OPPIA_CONFIG_TABLE,
			array('id'=>$record->id,'value'=>$value));
	} else {
		$DB->insert_record(OPPIA_CONFIG_TABLE, 
			array('modid'=>$modid,'name'=>$name,'value'=>$value,'serverid'=>$servid));
	}
}

function remove_oppiaconfig_if_exists($modid, $name, $servid="default"){
	global $DB;

	if ($servid !== null){
		$record = $DB->delete_records_select(OPPIA_CONFIG_TABLE,
	    "modid=$modid and `name`='$name' and serverid='$servid'");	
	}
	else{
		$record = $DB->delete_records(OPPIA_CONFIG_TABLE, array('modid'=>$modid,'name'=>$name));
	}
}

function get_oppiaconfig($modid, $name, $default, $servid="default", $use_non_server_value=true){
	global $DB;
	$record = $DB->get_record_select(OPPIA_CONFIG_TABLE, 
		"modid=$modid and `name`='$name' and serverid='$servid'");
	if ($record){
		return $record->value;
	} else {
		if ($use_non_server_value){
			//Try if there is a non-server value saved
			$record = $DB->get_record(OPPIA_CONFIG_TABLE, array('modid'=>$modid,'name'=>$name));
			if ($record){
				return $record->value;
			} 
		}
		return $default;
	}
}

function get_oppiaservers(){
	global $DB, $USER;
	return $DB->get_records(OPPIA_SERVER_TABLE, array('moodleuserid'=>$USER->id));
}

function add_publishing_log($server, $userid, $courseid, $action, $data){
    global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $DB->insert_record(OPPIA_PUBLISH_LOG_TABLE,
        array('server'=>$server,
                'logdatetime'=>$timestamp,
                'moodleuserid'=>$userid,
                'courseid'=>$courseid,
                'action'=>$action,
                'data'=>$data)
        );
}

function get_section_title($section){

	$defaultSectionTitle = false;
	$sectionTitle = strip_tags(format_string($section->summary));
	$title = extractLangs($section->summary);

	// If the course has no summary, we try to use the section name
	if ($sectionTitle == "") {
		$sectionTitle = strip_tags(format_string($section->name));
		$title = extractLangs($section->name);
	}
	// If the course has neither summary nor name, use the default topic title
	if ($sectionTitle == "") {
		$sectionTitle = get_string('sectionname', 'format_topics') . ' ' . $section->section;
		$title = $sectionTitle;
		$defaultSectionTitle = true;
	}

	return array(
		'using_default' => $defaultSectionTitle,
		'display_title' => $sectionTitle,
		'title' => $title,
	);
}

function extractLangs($content, $asJSON = false, $strip_tags = false, $strip_basic_tags = false){
    global $MOBILE_LANGS, $CURRENT_LANG, $DEFAULT_LANG;
	preg_match_all(REGEX_LANGS, $content, $langs_tmp, PREG_OFFSET_CAPTURE);
	$tempLangs = array();
	if(isset($langs_tmp['langs']) && count($langs_tmp['langs']) > 0){
		for($i=0;$i<count($langs_tmp['langs']);$i++){
			$lang = $langs_tmp['langs'][$i][0];
			$lang = str_replace("-","_",$lang);
			$tempLangs[$lang] = true;
		}
	} else{
		if ($strip_tags){
			if ($strip_basic_tags){
				$content = trim(strip_tags($content));
			}
			else{
				$content = trim(strip_tags($content, BASIC_HTML_TAGS));
			}
		} 

		if (!$asJSON){
			return $content;
		} else {
			$json = new stdClass;
			$json->{$DEFAULT_LANG} = $content;
			return json_encode($json);
		}
	} 

	$filter = new tomobile_langfilter();
	foreach($tempLangs as $k=>$v){
		$CURRENT_LANG = $k;
		if ($strip_tags){
			if ($strip_basic_tags){
				$tempLangs[$k] = trim(strip_tags($filter->filter($content)));
			}
			else{
				$tempLangs[$k] = trim(strip_tags($filter->filter($content), BASIC_HTML_TAGS));
			}
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
		$cleantext = preg_replace(REGEX_BR, "\n", $cleantext);
	}
	return preg_replace(REGEX_HTML_ENTITIES, " ", $cleantext);
}

function cleanTagList($tags){
    // split on comma
    $tagList = explode (",", $tags); 
    $cleanTags = array();
    
    // clean each tag separately
    foreach($tagList as $tag){
        $cleanTag = trim($tag);
        $cleanTag = preg_replace(REGEX_FORBIDDEN_TAG_CHARS, "-", $cleanTag);
        if (strlen($cleanTag) > 0){
            array_push($cleanTags, $cleanTag);
        }
    }
	// combine cleanTags to string and return
    return implode(", ", $cleanTags);
}

function cleanShortname($shortname){
	$shortname = trim($shortname);
	$shortname = preg_replace(REGEX_FORBIDDEN_DIR_CHARS, "-", $shortname);
	return preg_replace('(\-+)', "-", $shortname); //clean duplicated hyphens
}

function removeIDsFromJSON($jsonString){
	$jsonString = preg_replace("(\"courseversion\":\"[0-9]+\",?)", "", $jsonString);
	$jsonString = preg_replace("(\"moodle_question_id\":\"[0-9]+\",?)", "", $jsonString);
	return preg_replace("(\"id\":[0-9]+,?)", "", $jsonString);
}


function extractImageFile($content, $component, $filearea, $itemid, $contextid, $course_root, $cmid){
	global $CFG;
	//find if any images/links exist
	
	preg_match_all(MEDIAFILE_REGEX, $content, $files_tmp, PREG_OFFSET_CAPTURE);
	
	if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
		return false;
	}	

	$lastimg = false;
	$toreplace = array();
	for($i=0;$i<count($files_tmp['filenames']);$i++){

		$filename = trim($files_tmp['filenames'][$i][0]);

		if (!IsFileAnImage($course_root . "/" . $filename)){
			// If the file is not an image, we pass on it
			continue;
		}
		
		if($CFG->block_oppia_mobile_export_debug){
			echo 'Attempting to export thumbnail image: <code>'.urldecode($filename).'</code><br/>';
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

		if ($result){
			$lastimg = $result;
		}
		
	}
	return $lastimg;
}

function getFileInfo($filename, $component, $filearea, $itemid, $contextid){

	$fs = get_file_storage();
	$path = '/';
	$file = $fs->get_file($contextid, $component, $filearea, $itemid, $path, $filename);

	if ($file) {
		return array(
			'filename' => $file->get_filename(),
			'digest' => md5($file->get_content()),
			'filesize' => $file->get_filesize(),
			'moodlefile' => $contextid.';'.$component.';'.$filearea.';'.$itemid.';'.$path.';'.$filename
		);
	}
	return false;

}

// Returns the filename without special or non-ASCII characters, replacing them with underscores 
function cleanFilename($filename){
	$clean = preg_replace(
        '([^\x1F-\x7F]|'.	// non-ASCII characters
        '[[:space:]]|' .	// spaces
        '[<>:"/\\|?*]|'.  	// file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        '[\x00-\x1F]|'. 	// control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        '[\x7F\xA0\xAD]|'. 	// non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        '[{}^\~`])',       	// URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
    	'_', $filename);

	$clean = preg_replace('(_+)', '_', $clean); // Remove multiple repeated underscores
	return $clean;
}

function copyFile($file, $component, $filearea, $itemid, $contextid, $course_root, $cmid){
	global $CFG;

	$is_image = true;
	if ($file) {

			$filename = $file->get_filename();
			$fullpath = '/'. $contextid .'/'. $component .'/'. $filearea .'/'. $itemid .'/'. $filename;
			$sha1 = sha1($fullpath);
			if (preg_match(REGEX_RESOURCE_EXTENSIONS, $filename) > 0){
				$is_image = false;
				$filedest = "/resources/".$filename;
			}
			else{
				$filedest = "/images/".$sha1;
			}
			
			$file->copy_content_to($course_root.$filedest);

	} else {
		$link = $CFG->wwwroot.'/course/modedit.php?return=0&sr=0&update='.$cmid;
		$message = 'error_'.($is_image?'image':'file').'_edit_page';
		echo '<span class="export-error">'.get_string($message, PLUGINNAME, $link).'</span><br/>';
		return false;
	}
	
	$tr = new StdClass;
	$tr->originalfilename = $filename;
	$tr->filename = sha1($fullpath);
	if($CFG->block_oppia_mobile_export_debug){
		$message = 'export_'.($is_image?'image':'file').'_success';
		echo get_string($message, PLUGINNAME, urldecode($filename))."<br/>";

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
	$image_new_name = $image_new_name.'.png';
	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
	return $image_new_name;
	
}


function IsFileAnImage($filepath){ 
    return (preg_match(REGEX_IMAGE_EXTENSIONS, $filepath) > 0); 
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

	$image_new_name = $image_new_name.'.png';
	imagepng($image_new,$image_new_name,9);

	imagedestroy($image_new);
	imagedestroy($image_src);
	return $image_new_name;
}

function Zip($source, $destination){
	if (!extension_loaded('zip') || !file_exists($source)) {
		echo '<span class="export-error">Unable to load Zip extension (is it correctly installed and configured in the Moodle server?)</span><br/>';
		return false;
	}

	$zip = new ZipArchive();
	if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
		echo '<span class="export-error">Couldn\'t create Zip archive</span><br/>';
		return false;
	}

	$source = str_replace('\\', '/', realpath($source));

	if (is_dir($source) === true){
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

		foreach ($files as $file){
			$file = str_replace('\\', '/', realpath($file));

			if (is_file($file) === true){
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
		    $return .= '<strong>Warning'.$error->code.OPPIA_HTML_STRONG_END.': ';
			break;
		case LIBXML_ERR_ERROR:
		    $return .= '<strong>Error'.$error->code.OPPIA_HTML_STRONG_END.': ';
			break;
		case LIBXML_ERR_FATAL:
		    $return .= '<strong>Fatal Error'.$error->code.OPPIA_HTML_STRONG_END.': ';
			break;
	}
	$return .= trim($error->message);
	if ($error->file) {
		$return .= " in <strong>$error->file</strong>";
	}
	$return .= " on line <strong>$error->line</strong>\n";

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


function createDOMElemFromTemplate($doc, $template_name, $params){
	global $OUTPUT;

	$elemHTML = $OUTPUT->render_from_template($template_name, $params);
	$dom = new DOMDocument();
	$dom->loadHTML($elemHTML, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	return $doc->importNode($dom->documentElement, true);
}


function getCompiledCSSTheme($pluginroot, $theme){
	$styles_root = $pluginroot.STYLES_DIR;
	$theme_scss = file_get_contents($styles_root.STYLES_THEMES_DIR.$theme.".scss");
	$scss_path = $styles_root.STYLES_BASE_SCSS;

	$compiler = new core_scss();
	$compiler->prepend_raw_scss($theme_scss);
	$compiler->set_file($scss_path);

	$extra_filename = $styles_root.STYLES_THEMES_DIR.$theme.STYLES_EXTRA_SUFFIX .'.scss';
	if (file_exists($extra_filename)){
		$extra_scss = file_get_contents($extra_filename);
		$compiler->append_raw_scss($extra_scss);
	}

	$css = $compiler->to_css();
	return $css;
}

/**
 * Serve the files from the block file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function block_oppia_mobile_export_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
	if ($context->contextlevel != CONTEXT_COURSE) {
		return false;
	}

	// Make sure the filearea is one of those used by the block.
	if ($filearea !== COURSE_EXPORT_FILEAREA) {
		return false;
	}

	require_login($course, true, $cm);

	$userid = array_shift($args); // The first item in the $args array.

	// Use the itemid to retrieve any relevant data records and perform any security checks to see if the
	// user really does have access to the file in question.

	$filename = array_pop($args); // The last item in the $args array.
	if (!$args) {
		$filepath = '/'; // $args is empty => the path is '/'
	} else {
		$filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
	}

	// Retrieve the file from the Files API.
	$fs = get_file_storage();
	$file = $fs->get_file($context->id, PLUGINNAME, $filearea, $userid, $filepath, $filename);
	if (!$file) {
		return false; // The file does not exist.
	}

	// We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
	send_stored_file($file, 86400, 0, $forcedownload, $options);
}

function cleanUpExportedFiles($context, $userid) {
	$fs = get_file_storage();
	$files = $fs->get_area_files(
		$context->id,
		PLUGINNAME,
		COURSE_EXPORT_FILEAREA,
		$userid
	);
	foreach($files as $file) {
		$file->delete();
		unlink($file->get_filepath());
	}
}


function add_or_update_grade_boundary($modid, $grade, $message, $servid="default"){
	global $DB;

	if ($servid !== null){
		$record = $DB->get_record_select(OPPIA_GRADE_BOUNDARY_TABLE,
			"modid=$modid and `grade`='$grade' and serverid='$servid'");
	}
	else{
		$record = $DB->get_record(OPPIA_GRADE_BOUNDARY_TABLE, array('modid'=>$modid,'grade'=>$grade));
	}

	if ($record){
		$DB->update_record(OPPIA_GRADE_BOUNDARY_TABLE,
			array('id'=>$record->id,'message'=>$message));
	} else {
		$DB->insert_record(OPPIA_GRADE_BOUNDARY_TABLE,
			array('modid'=>$modid,'grade'=>$grade,'message'=>$message,'serverid'=>$servid));
	}
}
function delete_grade_boundary($modid, $grade, $servid="default"){
	global $DB;
	$DB->delete_records(OPPIA_GRADE_BOUNDARY_TABLE,
		array(
			'modid' => $modid,
			'grade' => $grade,
			'serverid' => $servid
		)
	);
}
function get_grade_boundaries($modid, $servid="default"){
	global $DB;
	$records = $DB->get_records_select(OPPIA_GRADE_BOUNDARY_TABLE,
		"modid=$modid and serverid='$servid'");
	if ($records){
		return $records;
	} else {
		//Try if there is a non-server value saved
		$records = $DB->get_records(OPPIA_GRADE_BOUNDARY_TABLE,
			array('modid'=>$modid));
		if ($records){
			return $records;
		} else {
			return array();
		}
	}
}

function sort_grade_boundaries_descending($gb1, $gb2) {
	return $gb2->grade - $gb1->grade;
}
?>
