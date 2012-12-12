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

function extractLangs($content){
	global $MOBILE_LANGS, $CURRENT_LANG;
	preg_match_all('((lang=[\'|\"](?P<langs>[\w\-]*)[\'|\"]))',$content,$langs_tmp, PREG_OFFSET_CAPTURE);
	$tempLangs = array();
	if(isset($langs_tmp['langs']) && count($langs_tmp['langs']) > 0){
		for($i=0;$i<count($langs_tmp['langs']);$i++){
			$tempLangs[$langs_tmp['langs'][$i][0]] = true;
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
	preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\.[:space:]]*)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
	if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
		return false;
	}	

	$toreplace = array();
	for($i=0;$i<count($files_tmp['filenames']);$i++){
		$filename = $files_tmp['filenames'][$i][0];
		echo "\t\ttrying file: ".$filename."\n";
		$fullpath = "/$contextid/$contextname/$itemid/$filename";
		
		$fs = get_file_storage();
		$file = $fs->get_file_by_hash(sha1($fullpath));
		$fh = $file->get_content_file_handle();

		$originalfilename = $filename;
		//hack to get around the possibilty of the filename being in a directory structure
		$tmp = explode("/",$filename);
		$filename = $tmp[count($tmp)-1];

		//copy file
		$imgfile = $course_root."/images/".$filename;
		$ifh = fopen($imgfile, 'w');

		while(!feof($fh)) {
			$data = fgets($fh, 1024);
			fwrite($ifh, $data);
		}
		fclose($ifh);
		fclose($fh);
		$tr = new StdClass;
		$tr->originalfilename = $originalfilename;
		$tr->filename = $filename;
		echo "\t\tFile: ".$filename." successfully exported\n";
	}
	return "images/".$filename;
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