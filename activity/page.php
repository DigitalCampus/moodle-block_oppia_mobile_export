<?php


class mobile_activity_page extends mobile_activity {
	
	private $act = "";
	private $page_media = array();
	private $page_image = null;
	
	function process(){
		global $DB, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('page', $this->id);
		$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$context = get_context_instance(CONTEXT_MODULE, $cm->id);

		$content = $this->extractFiles($page->content, $context->id, 'mod_page', 'content', $page->revision, $this->courseroot);
		$this->md5 =  md5($page->content);
		
		// find all the langs on this page
		$langs = extractLangs($content);
		
		$filename = extractImageFile($page->intro,$context->id,'mod_page/intro','0',$this->courseroot);
		if($filename){
			$this->page_image = $filename;
		}
		
		if(is_array($langs) && count($langs)>0){
			foreach($langs as $l=>$t){
				
				$pre_content = $t;
				$t = $this->extractMedia($t);
				// if page has media and no special icon for page, extract the image for first video
				if (count($this->page_media) > 0 && $this->page_image == null){
					$this->extractMediaImage($pre_content,$context->id,'mod_page/content');
				}
				
				// add html header tags etc
				// need to do this to ensure it all has the right encoding when loaded in android webview
				$webpage = '<html>
							<head>
							<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
							</head>
							<body>'.$t.'
							</body>
							</html>';
					
				$filename = $this->makePageFilename($this->section,$cm->id,$l);
				$index = $this->courseroot."/".$filename;
				$fh = fopen($index, 'w');
				fwrite($fh, $webpage);
				fclose($fh);
				$this->act .= "<location lang='".$l."'>".$filename."</location>";
			}
		} else {
			$pre_content = $content;
			$content = $this->extractMedia($content);
			// if page has media and no special icon for page, extract the image for first video
			if (count($this->page_media) > 0 && $this->page_image == null){
				$this->extractMediaImage($pre_content,$context->id,'mod_page/content');
			}
			
			// add html header tags etc
			// need to do this to ensure it all has the right encoding when loaded in android webview
			$webpage = '<html>
						<head>
						<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
						</head>
						<body>'.$content.'
						</body>
						</html>';
		
			$filename = $this->makePageFilename($this->section,$cm->id,$DEFAULT_LANG);
			$index = $this->courseroot."/".$filename;
			$fh = fopen($index, 'w');
			fwrite($fh, $webpage);
			fclose($fh);
			$this->act .= "<location lang='".$DEFAULT_LANG."'>".$filename."</location>";
		}
		
		// resize page image
		if($this->page_image){
			resizeImage($this->courseroot."/".$this->page_image,$this->courseroot."/images/".$cm->id);
			$this->page_image = "/images/".$cm->id;
		}
		
	}
	
	function getXML($mod,$counter){
		global $DEFAULT_LANG;
		$structure_xml = "<activity type='".$mod->modname."' order='".$counter."' digest='".$this->md5."'>";
		$title = extractLangs($mod->name);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$structure_xml .= "<title lang='".$l."'>".strip_tags($t)."</title>";
			}
		} else {
			$structure_xml .= "<title lang='".$DEFAULT_LANG."'>".strip_tags($mod->name)."</title>";
		}
		// add in page media
		if(count($this->page_media) > 0){
			$structure_xml .= "<media>";
			foreach ($this->page_media as $m){
				$structure_xml .= "<file filename='".$m->filename."' download_url='".$m->download_url."' digest='".$m->digest."'/>";
			}
			$structure_xml .= "</media>";
		}
		if($this->page_image){
			$structure_xml .= "<image filename='".$this->page_image."'/>";
		}
		$structure_xml .= $this->act;
		$structure_xml .= "</activity>";
		
		return $structure_xml;
	}
	
	private function extractFiles($content, $contextid, $component, $filearea, $itemid, $course_root){
		global $CFG;
		
		preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\W]*?)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return $content;
		}	

		$toreplace = array();
		for($i=0;$i<count($files_tmp['filenames']);$i++){
			$filename = $files_tmp['filenames'][$i][0];
			
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
	
	private function extractMedia($content){
		global $MEDIA;
		
		$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-/,[:space:]]*)[[:space:]]?[\"|\']\]\]))';
		
		preg_match_all($regex,$content,$media_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($media_tmp['mediaobject']) || count($media_tmp['mediaobject']) == 0){
			return $content;
		}

		for($i=0;$i<count($media_tmp['mediaobject']);$i++){
			$mediajson = json_decode($media_tmp['mediaobject'][$i][0]);
			$toreplace = $media_tmp[0][$i][0];
			
			// replace [[media]] with <a href
			$r = "<a href='/video/".$mediajson->filename."'>";
			$content = str_replace($toreplace, $r, $content);

			$m = new StdClass;
			$m->filename = $mediajson->filename;
			$m->download_url = $mediajson->download_url;
			$m->digest = $mediajson->digest;
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$m->digest] = $m;
			$this->page_media[$m->digest] = $m;
		}
		//replace all [[/media]] with </a>
		$content = str_replace("[[/media]]", "</a>", $content);
		return $content;
	}
	
	private function extractMediaImage($content,$contextid, $filearea){
		$regex = '((\]\])([[:space:]]*)(\<img[[:space:]]src=[\"|\']images/(?P<filenames>[\w\W]*?)[\"|\']))';
		
		preg_match_all($regex,$content,$files_tmp, PREG_OFFSET_CAPTURE);
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			echo "\t\tNo image file found\n";
			return;
		}
		$filename = $files_tmp['filenames'][0][0];
			
		echo "\t\t trying file: ".$filename."\n";
		$fullpath = "/$contextid/$filearea/0/$filename";
		$fs = get_file_storage();
		$file = $fs->get_file_by_hash(sha1($fullpath));
		$fh = $file->get_content_file_handle();
		
		$originalfilename = $filename;
		//hack to get around the possibilty of the filename being in a directory structure
		$tmp = explode("/",$filename);
		$filename = $tmp[count($tmp)-1];
		
		//copy file
		$imgfile = $this->courseroot."/images/".$filename;
		$ifh = fopen($imgfile, 'w');
		
		while(!feof($fh)) {
			$data = fgets($fh, 1024);
			fwrite($ifh, $data);
		}
		fclose($ifh);
		fclose($fh);
		echo "\t\tImage for Media file: ".$filename." successfully exported\n";
		$this->page_image = "images/".$filename;
	}
	
	private function makePageFilename($sectionno, $name, $lang){
		return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
	}
}