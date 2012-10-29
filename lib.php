<?php 

function getContent ($course_root, $id, $sectionno){
	global $DB, $MOBILE_LANGS, $DEFAULT_LANG;
	$cm= get_coursemodule_from_id('page', $id);
	$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
	
	return $page->content;
}

function toMobilePage($course_root, $id, $sectionno){
	global $DB, $MOBILE_LANGS, $DEFAULT_LANG;
	$cm= get_coursemodule_from_id('page', $id);
	$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);

	$context = get_context_instance(CONTEXT_MODULE, $cm->id);

	//$content = file_rewrite_pluginfile_urls($page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', $page->revision);
	$content = extractFiles($page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', $page->revision, $course_root);

	// find all the langs on this page
	$langs = extractLangs($content);

	$return = "";
	if(is_array($langs) && count($langs)>0){
		foreach($langs as $l=>$t){
			// add html header tags etc
			// need to do this to ensure it all has the right encoding when loaded in android webview
			$content = '<html>
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			</head>
			<body>'.$t.'
			</body>
			</html>';
			
			$filename = makePageFilename($sectionno,$cm->id,$l);
			$index = $course_root."/".$filename;
			$fh = fopen($index, 'w');
			fwrite($fh, $content);
			fclose($fh);
			$return .= "<location lang='".$l."'>".$filename."</location>";
		}
	} else {
		// add html header tags etc
		// need to do this to ensure it all has the right encoding when loaded in android webview
		$content = '<html>
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		</head>
		<body>'.$content.'
		</body>
		</html>';
		
		$filename = makePageFilename($sectionno,$cm->id,$DEFAULT_LANG);
		$index = $course_root."/".$filename;
		$fh = fopen($index, 'w');
		fwrite($fh, $content);
		fclose($fh);
		$return .= "<location lang='".$DEFAULT_LANG."'>".$filename."</location>";
	}
	
	
	
	return $return;
}

function toMobileQuiz($course_root, $id, $shortname, $sectiontitle, $sectionno, $mquizuser, $mquizpass){
	global $DB,$USER,$QUIZ_CACHE,$CFG;

	$cm = get_coursemodule_from_id('quiz', $id);
	$context = get_context_instance(CONTEXT_MODULE, $cm->id);
	$quizobj = quiz::create($cm->instance, $USER->id);
	$qgift = "";
	try {
		$quizobj->preload_questions();
		$quizobj->load_questions();
		$qs = $quizobj->get_questions();
		foreach($qs as $q){
			$qg = new qformat_gift;
			$qgift .= $qg->writequestion($q);
		}
	} catch (moodle_exception $me){
		//echo "no questions in this quiz";
	}

	$post = array('method' => 'create',
			'username' => $mquizuser,
			'password' => $mquizpass,
			'title' => $shortname." ".$sectionno." ".$cm->name,
			'content' => strip_tags($qgift),
			'description' => $shortname.": ".$sectionno.": ".$sectiontitle.": ".$cm->name);
	$pparams = http_build_query($post);
	$pparams = str_replace('&amp;','&',$pparams);
	//post this to mquiz server to create as a new quiz and save the results (also add to quiz cache file)
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,            $CFG->block_export_mobile_package_mquiz_url );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_POST,           1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS,     $pparams);

	$data = curl_exec($ch);
	$json = json_decode($data);
	if(isset($json->qref)){
		echo "\tQuiz exported sucessfully\n";
		return $data;
	} else if(isset($json->login) && !$json->login){
		echo "\tInvalid mquiz login details\n";
		return false;
	} else {
		echo "\tConnection problem with mquiz server\n";
		return false;
	}
}

function makePageFilename($sectionno, $name, $lang){
	return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
}

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

function getTemplate($file){
	$tfile = "template/".$file;
	$fh = fopen($tfile, 'r');
	$template = fread($fh, filesize($tfile));
	fclose($fh);
	return $template;
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

function extractFiles($content, $file, $contextid, $component, $filearea, $itemid, $course_root){
	global $CFG;
	//find if any images/links exist
	$pos = strpos_r($content,'src="@@PLUGINFILE@@/');
	if(count($pos) == 0){
		return $content;
	}
	$toreplace = array();
	foreach($pos as $p){
		$len = strpos($content,'"',($p+20))-($p+20);
		$filename = substr($content,$p+20,$len);
		echo "\t\t trying file: ".$filename."\n";
		$fullpath = "/$contextid/mod_page/$filearea/0/$filename";
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
		array_push($toreplace, $tr);
		echo "\t\tFile: ".$filename." successfully exported\n";
	}
	foreach($toreplace as $tr){
		$content = str_replace('src="@@PLUGINFILE@@/'.$tr->originalfilename, 'src="images/'.$tr->filename, $content);
	}
	return $content;
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