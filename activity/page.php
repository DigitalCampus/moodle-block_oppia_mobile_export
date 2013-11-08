<?php


class mobile_activity_page extends mobile_activity {
	
	private $act = array();
	private $page_media = array();
	private $page_image = null;
	
	function process(){
		global $DB, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('page', $this->id);
		$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$context = get_context_instance(CONTEXT_MODULE, $cm->id);

		$content = $this->extractFiles($page->content, $context->id, 'content', $page->revision, $this->courseroot);
		$this->md5 =  md5($page->content);
		
		// find all the langs on this page
		$langs = extractLangs($content);
		
		// get the image from the intro section
		$eiffilename = extractImageFile($page->intro,$context->id,'mod_page/intro','0',$this->courseroot);
		if($eiffilename){
			$this->page_image = $eiffilename;
			resizeImage($this->courseroot."/".$this->page_image,$this->courseroot."/images/".$cm->id);
			$this->page_image = "/images/".$cm->id;
			//delete original image
			unlink($this->courseroot."/".$eiffilename) or die('Unable to delete the file');
		}
		unset($eiffilename);
		
		if(is_array($langs) && count($langs)>0){
			foreach($langs as $l=>$t){
				
				$pre_content = $t;
				$t = $this->extractMedia($t);
				// if page has media and no special icon for page, extract the image for first video
				if (count($this->page_media) > 0 && $this->page_image == null){
					if($this->extractMediaImage($pre_content,$context->id,'mod_page/content')){
						resizeImage($this->courseroot."/".$this->page_image,$this->courseroot."/images/".$cm->id);
						$this->page_image = "/images/".$cm->id;
					}
				}
				
				// add html header tags etc
				// need to do this to ensure it all has the right encoding when loaded in android webview
				$webpage = '<body>'.$t.'</body>';
					
				$mpffilename = $this->makePageFilename($this->section,$cm->id,$l);
				$index = $this->courseroot."/".$mpffilename;
				$fh = fopen($index, 'w');
				fwrite($fh, $webpage);
				fclose($fh);
				$o = new stdClass();
				$o->lang = $l;
				$o->filename = $mpffilename;
				array_push($this->act,$o);
				unset($mpffilename);
			}
		} else {
			$pre_content = $content;
			$content = $this->extractMedia($content);
			// if page has media and no special icon for page, extract the image for first video
			if (count($this->page_media) > 0 && $this->page_image == null){
				if($this->extractMediaImage($pre_content,$context->id,'mod_page/content')){
					resizeImage($this->courseroot."/".$this->page_image,$this->courseroot."/images/".$cm->id);
					$this->page_image = "/images/".$cm->id;
				}
			} else if ($this->page_image == null){
				$piffilename = extractImageFile($page->content,$context->id,'mod_page/content','0',$this->courseroot);	
				if($piffilename){
					$this->page_image = $piffilename;
					resizeImage($this->courseroot."/".$this->page_image,$this->courseroot."/images/".$cm->id);
					$this->page_image = "/images/".$cm->id;
					unlink($this->courseroot."/".$piffilename) or die('Unable to delete the file');
				}
			}
			
			// add html header tags etc
			// need to do this to ensure it all has the right encoding when loaded in android webview
			$webpage = '<body>'.$content.'</body>';
		
			$mpf2filename = $this->makePageFilename($this->section,$cm->id,$DEFAULT_LANG);
			$index = $this->courseroot."/".$mpf2filename;
			$fh = fopen($index, 'w');
			fwrite($fh, $webpage);
			fclose($fh);
			$o = new stdClass();
			$o->lang = $DEFAULT_LANG;
			$o->filename = $mpf2filename;
			array_push($this->act,$o);
		}
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		global $DEFAULT_LANG;
		if($activity){
			$struct = $xmlDoc->createElement("activity");
			$struct->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($mod->modname));
			$struct->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
			$struct->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));
			$node->appendChild($struct);
		} else {
			$struct = $xmlDoc->createElement("page");
			$struct->appendChild($xmlDoc->createAttribute("id"))->appendChild($xmlDoc->createTextNode($this->id));
			$node->appendChild($struct);
		}
		$title = extractLangs($mod->name);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement("title");
				$temp->appendChild($xmlDoc->createTextNode(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$struct->appendChild($temp);
			}
		} else {
			$temp = $xmlDoc->createElement("title");
			$temp->appendChild($xmlDoc->createTextNode(strip_tags($mod->name)));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$struct->appendChild($temp);
		}
		// add in page media
		if(count($this->page_media) > 0){
			$media = $xmlDoc->createElement("media");
			foreach ($this->page_media as $m){
				$temp = $xmlDoc->createElement("file");
				foreach($m as $var => $value) {
					$temp->appendChild($xmlDoc->createAttribute($var))->appendChild($xmlDoc->createTextNode($value));
				}
				$media->appendChild($temp);
			}
			$struct->appendChild($media);
		}
		if($this->page_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->page_image));
			$struct->appendChild($temp);
		}
		foreach($this->act as $act){
			$temp = $xmlDoc->createElement("location",$act->filename);
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($act->lang));
			$struct->appendChild($temp);
		}
	}
	
	private function extractFiles($content, $contextid, $filearea, $itemid, $course_root){
		global $CFG;
		
		preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[\w\W]*?)[\"|\']))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return $content;
		}	

		$toreplace = array();
		for($i=0;$i<count($files_tmp['filenames']);$i++){
			$filename = urldecode($files_tmp['filenames'][$i][0]);
			
			echo "\t\t trying file: ".$filename."\n";
			$fullpath = "/$contextid/mod_page/$filearea/0/". $filename;
			$fs = get_file_storage();
			$file = $fs->get_file_by_hash(sha1($fullpath));
			$fh = $file->get_content_file_handle();
	
			$originalfilename = $files_tmp['filenames'][$i][0];
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
			// check all the required attrs exist
			if(!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)){
				echo "You must supply digest, download_url and filename for every media object\n";
				die;
			}
			
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$mediajson->digest] = $mediajson;
			$this->page_media[$mediajson->digest] = $mediajson;
		}
		//replace all [[/media]] with </a>
		$content = str_replace("[[/media]]", "</a>", $content);
		return $content;
	}
	
	private function extractMediaImage($content,$contextid, $filearea){
		$regex = '((\]\])([[:space:]]*)(\<img[[:space:]]src=[\"|\']images/(?P<filenames>[\w\W]*?)[\"|\']))';
		
		preg_match_all($regex,$content,$files_tmp, PREG_OFFSET_CAPTURE);
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			echo "\t\tNo image file found:\n";
			return false;
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
		return true;
	}
	
	private function makePageFilename($sectionno, $name, $lang){
		return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
	}
}