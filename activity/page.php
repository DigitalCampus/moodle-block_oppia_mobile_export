<?php

//This is the regex for detecting any number of spaces or <br> or <p> tags (in any of its forms) 

const spaces_regex = '([[:space:]]|\<br\/?[[:space:]]*\>|\<\/?p\>)*';

class mobile_activity_page extends mobile_activity {	

	private $act = array();
	private $page_media = array();
	private $page_related = array();
	private $page_local_media = array();

	public function __construct(){ 
		$this->component_name = 'mod_page';
    } 
	
	function process(){
		global $DB, $CFG, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm = get_coursemodule_from_id('page', $this->id);
		$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$this->md5 = md5($page->content).$this->id;
		
		$this->fetchLocalMedia($page->content);

		$content = $this->extractAndReplaceFiles($page->content, 'mod_page', 'content',
										0, $context->id, $this->courseroot, $cm->id);
		//$content = $this->extractRelated($content);

		// get the image from the intro section
		$this->extractThumbnailFromIntro($page->intro, $cm->id);

		$langs = extractLangs($content);
		if(is_array($langs) && count($langs)>0){
			foreach($langs as $lang=>$text){
				//Process individually each language
				$this->process_content($context, $cm->id, $text, $lang);
			}
		} else {
			$this->process_content($context, $cm->id, $content, $DEFAULT_LANG);
		}
	}

	function process_content($context, $mod_id, $content, $lang){
		$pre_content = $content;

		$content = $this->extractAndReplaceMedia($content);
		// if page has media and no special icon for page, extract the image for first video
		if (count($this->page_media) > 0 && $this->thumbnail_image == null){
			if($this->extractMediaImage($pre_content,'mod_page','content',0, $context->id)){
				$this->saveResizedThumbnail($this->thumbnail_image, $mod_id);
			}
		} else if ($this->thumbnail_image == null){
			// If it does not have an image, we try to extract it from the contents
			$this->extractThumbnailFromContents($pre_content, $mod_id);
		}
		
		// add html header tags etc
		// need to do this to ensure it all has the right encoding when loaded in android webview
		$webbpage = '<!DOCTYPE html>';
		$webpage .= '<html><head>';
		$webpage .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
		$webpage .= '<link href="style.css" rel="stylesheet" type="text/css"/>';
		$webpage .= '<script src="js/jquery-1.11.0.min.js"></script>';
		$webpage .= '<script src="js/jquery-ui-1.10.3.custom.min.js"></script>';
		$webpage .= '<script src="js/oppia.js"></script>';
		$webpage .= '</head>';
		$webpage .= '<body>'.$content.'</body></html>';
	
		$page_filename = $this->makePageFilename($this->section, $mod_id, $lang);
		$index = $this->courseroot."/".$page_filename;
		$fh = fopen($index, 'w');
		fwrite($fh, $webpage);
		fclose($fh);

		$o = new stdClass();
		$o->lang = $lang;
		$o->filename = $page_filename;
		array_push($this->act, $o);
		unset($page_filename);
	}
	
	function export2print(){
		global $DB, $CFG, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('page', $this->id);
		$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$content = $this->extractAndReplaceFiles($page->content,
				'mod_page', 'content', 0, $context->id, $this->courseroot);
		$langs = extractLangs($content);
		
		// get the image from the intro section
		$this->extractThumbnailFromIntro($page->intro, $cm->id);

		$return_content = "";
		if(is_array($langs) && count($langs)>0){
			foreach($langs as $l=>$t){
		
				$pre_content = $t;
				$t = $this->extractAndReplaceMedia($t);
				// if page has media and no special icon for page, extract the image for first video
				if (count($this->page_media) > 0 && $this->thumbnail_image == null){
					if($this->extractMediaImage($pre_content,'mod_page','content',0, $context->id)){
						$this->saveResizedThumbnail($this->thumbnail_image, $cm->id);
					}
				}
				$return_content .= $t;
					
			}
		} else {
			$pre_content = $content;
			$content = $this->extractAndReplaceMedia($content);
			// if page has media and no special icon for page, extract the image for first video
			if (count($this->page_media) > 0 && $this->thumbnail_image == null){
				if($this->extractMediaImage($pre_content,'mod_page','content',0, $context->id)){
						$this->saveResizedThumbnail($this->thumbnail_image, $cm->id);
				}
			} else if ($this->thumbnail_image == null){
				$this->extractThumbnailFromContents($pre_content, $cm->id);
			}
			$return_content = $content;
				
		}
		return $return_content;
		
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		global $DEFAULT_LANG;
		if($activity){
			$struct = $this->getActivityNode($xmlDoc, $mod, $counter);
			$node->appendChild($struct);
		} else {
			$struct = $xmlDoc->createElement("page");
			$struct->appendChild($xmlDoc->createAttribute("id"))->appendChild($xmlDoc->createTextNode($this->id));
			$node->appendChild($struct);
		}

		$this->addLangXMLNodes($xmlDoc, $struct, $mod->name, "title");
		$this->addThumbnailXMLNode($xmlDoc, $struct);

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
		if(count($this->page_related) > 0){
			$related = $xmlDoc->createElement("related");
			foreach ($this->page_related as $r){
				$temp = $xmlDoc->createElement("activity");
				$temp->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($r->order));
				$temp->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($r->digest));
				foreach($r->activity as $a) {
					$title = $xmlDoc->createElement("title");
					$title->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($a->lang));
					$title->appendChild($xmlDoc->createTextNode(strip_tags($a->title)));
					$temp->appendChild($title);
				}
				$related->appendChild($temp);
			}
			$struct->appendChild($related);
		}

		foreach($this->act as $act){
			$temp = $xmlDoc->createElement("location",$act->filename);
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($act->lang));
			$struct->appendChild($temp);
		}
	}



	private function extractAndReplaceFiles($content, $component, $filearea, $itemid, $contextid){
		global $CFG;
		
		preg_match_all('((@@PLUGINFILE@@/(?P<filenames>[^\"\'\?<>]*)))',$content,$files_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return $content;
		}	
		$toreplace = array();

		for($i=0;$i<count($files_tmp['filenames']);$i++){

			$orig_filename = $files_tmp['filenames'][$i][0];
			$filename = urldecode($orig_filename);
			if ( $this->isLocalMedia($orig_filename) ){
				continue;
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
				$imgfile = $this->courseroot."/images/".urldecode($orig_filename);
				$file->copy_content_to($imgfile);
			} else {
				if($CFG->block_oppia_mobile_export_debug){
					echo '<span class="export-error">'.get_string('error_file_not_found','block_oppia_mobile_export',$filename).'</span><br/>';
					return;
				}
			}
			
			$tr = new StdClass;
			$tr->filename = $filename;
			$tr->orig_filename = $orig_filename;
			array_push($toreplace, $tr);
			if($CFG->block_oppia_mobile_export_debug){
				echo get_string('export_file_success','block_oppia_mobile_export',$filename)."<br/>";
			}
		}
		foreach($toreplace as $tr){
			$content = str_replace('src="@@PLUGINFILE@@/'.$tr->orig_filename, 'src="images/'.urldecode($tr->orig_filename), $content);
			$content = str_replace('src="@@PLUGINFILE@@/'.urlencode($tr->filename), 'src="images/'.urldecode($tr->orig_filename), $content);
		}
		
		return $content;
	}
	
	private function extractAndReplaceMedia($content){
		global $MEDIA;
		
		//$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>[\{\}\'\"\:0-9\._\-/,[:space:]\w\W]*)[[:space:]]?[\"|\']\]\]))';
		//$regex = '((\[\[[[:space:]]?media[[:space:]]?object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-/,[:space:]]*)[[:space:]]?[\"|\']\]\]))';
		
		$regex = '((\[\[' . spaces_regex . 'media' . spaces_regex . 'object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-\/,[:space:]]*)([[:space:]]|\<br\/?[[:space:]]*\>)*[\"|\']' . spaces_regex . '\]\]))';

		preg_match_all($regex,$content,$media_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($media_tmp['mediaobject']) || count($media_tmp['mediaobject']) == 0){
			return $content;
		}

		for($i=0;$i<count($media_tmp['mediaobject']);$i++){
			$mediajson = json_decode($media_tmp['mediaobject'][$i][0]);
			$toreplace = $media_tmp[0][$i][0];

			$r = "<a href='/video/".$mediajson->filename."'>";
			$content = str_replace($toreplace, $r, $content);
			// check all the required attrs exist
			if(!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)){
				echo get_string('error_media_attributes','block_oppia_mobile_export')."<br/>";
				die;
			}
			
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$mediajson->digest] = $mediajson;
			$this->page_media[$mediajson->digest] = $mediajson;
		}
		$content = str_replace("[[/media]]", "</a>", $content);
		return $content;
	}

	private function fetchLocalMedia($content){

		$html = new DOMDocument();
		$parsed = $html->loadHTML($content);
		if ($parsed == false){
			echo '<span class="export-error">'.get_string('error_parsing_html','block_oppia_mobile_export').'</span><br/>';
			return;
		}

		$videos = $html->getElementsByTagName('video');
		foreach ($videos as $video) {
			foreach ($video->childNodes as $source){
				if (($source->nodeName == 'source') && ($source->hasAttribute('src'))){
					$filename = $source->getAttribute('src');
					array_push($this->page_local_media, $filename);
					echo 'Video included: <code>' . $filename . '</code><br/>';
				}
			}

			if (!$video->hasAttribute('poster')){
				echo '<span class="export-error">'.get_string('missing_video_poster','block_oppia_mobile_export').'</span><br/>';
			}
        } 
	}

	private function isLocalMedia($filename){
		$is_video = false;
		foreach ($this->page_local_media as $localMedia){
			if (strpos($localMedia, $filename) !== false){
				$is_video = true;
			}
			if (strpos($localMedia, urldecode($filename)) !== false){
				$is_video = true;
			}
		}
		return $is_video;
	}
	
	private function extractAndReplaceRelated($content){
		global $DB, $RELATED;
		$regex = '((\[\[[[:space:]]?related=[\"|\'](?P<relatedobject>[\{\}\'\"\:0-9[:space:]]*)[[:space:]]?[\"|\']\]\]))';
		preg_match_all($regex,$content,$related_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($related_tmp['relatedobject']) || count($related_tmp['relatedobject']) == 0){
			return $content;
		}
		
		for($i=0;$i<count($related_tmp['relatedobject']);$i++){
			$related = new stdClass();
			$related->order = $i+1;
			$related->activity = array();
			$cm= get_coursemodule_from_id('page', $related_tmp['relatedobject'][$i][0]);
			$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
			$related->digest = md5($page->content).$related_tmp['relatedobject'][$i][0];
			
			$activity = new stdClass();
			$activity->lang = "en";
			$activity->title = $page->intro;
			array_push($related->activity,$activity);
			
			array_push($this->page_related,$related);
			
			$toreplace = $related_tmp[0][$i][0];
			$content = str_replace($toreplace, "", $content);
		}
		return $content;
	}
	
	private function extractMediaImage($content,$component, $filearea, $itemid, $contextid){
		global $CFG;
		$regex = '(\]\]'.spaces_regex.'\<img[[:space:]]src=[\"|\\\']images/(?P<filenames>[\w\W]*?)[\"|\\\'])';
		
		preg_match_all($regex,$content,$files_tmp, PREG_OFFSET_CAPTURE);
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return false;
		}
		$filename = $files_tmp['filenames'][0][0];
			
		if($CFG->block_oppia_mobile_export_debug){
			echo '<span>' . get_string('export_file_trying','block_oppia_mobile_export',$filename).'</span><br/>';
		}
		
		$fullpath = "/$contextid/$component/$filearea/$itemid/$filename";
		$fs = get_file_storage();
		$fileinfo = array(
				'component' => $component,
				'filearea' => $filearea,
				'itemid' => 0,
				'contextid' => $contextid,
				'filepath' => '/',
				'filename' => $filename);
		$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
				$fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
			
		if ($file) {
			$imgfile = $this->courseroot."/images/".$filename;
			$file->copy_content_to($imgfile);
		} else {
			if($CFG->block_oppia_mobile_export_debug){
				echo '<span class="export-error">'.get_string('error_file_not_found','block_oppia_mobile_export',$filename).'</span><br/>';
			}
		}
		
		if($CFG->block_oppia_mobile_export_debug){
			echo get_string('export_file_success','block_oppia_mobile_export',$filename)."<br/>";
		}
		$this->thumbnail_image = "images/".$filename;
		return true;
	}
	
	private function makePageFilename($sectionno, $name, $lang){
		return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
	}
}