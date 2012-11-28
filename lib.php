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
	$pos = strpos_r($content,'lang="');
	if(count($pos) == 0){
		return $content;
	}
	$tempLangs = array();
	foreach($pos as $p){
		$len = strpos($content,'"',($p+6))-($p+6);
		$lang = substr($content,$p+6,$len);
		$tempLangs[$lang] = true;
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
	$pos = strpos_r($content,'src="@@PLUGINFILE@@/');
	if(count($pos) == 0){
		return false;
	}
	foreach($pos as $p){
		$len = strpos($content,'"',($p+20))-($p+20);
		$filename = substr($content,$p+20,$len);
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

function strpos_r($haystack, $needle)
{
	if(strlen($needle) > strlen($haystack)){
		return array();
	}

	$seeks = array();
	while($seek = strrpos($haystack, $needle))
	{
		array_push($seeks, $seek);
		$haystack = substr($haystack, 0, $seek);
	}
	return $seeks;
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


?>