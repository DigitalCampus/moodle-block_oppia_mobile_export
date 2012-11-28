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
				
				$t = $this->extractMedia($t);
				
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
			$content = $this->extractMedia($content);
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
	}
	
	function getXML($mod,$counter){
		global $DEFAULT_LANG;
		$structure_xml = "<activity type='".$mod->modname."' id='".$counter."' digest='".$this->md5."'>";
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
	
	private function extractMedia($content){
		global $MEDIA;
		$pos = strpos_r($content,'[[media object=\'');
		if(count($pos) == 0){
			return $content;
		}
		foreach($pos as $p){
			$len = strpos($content,'\']]',($p+16))-($p+16);
			$media_object = substr($content,$p+16,$len);
			$media_json = json_decode($media_object);
			
			// replace [[media]] with <a href
			$r = "<a href='/video/".$media_json->filename."'>";
			$content = substr_replace($content, $r, $p, $len+19);

			$m = new StdClass;
			$m->filename = $media_json->filename;
			$m->download_url = $media_json->download_url;
			$m->digest = $media_json->digest;
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$m->digest] = $m;
			$this->page_media[$m->digest] = $m;
		}
		//replace all [[/media]] with </a>
		$content = str_replace("[[/media]]", "</a>", $content);
		return $content;
	}
	
	private function makePageFilename($sectionno, $name, $lang){
		return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
	}
}