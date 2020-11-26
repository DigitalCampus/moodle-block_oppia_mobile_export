<?php

//This is the regex for detecting any number of spaces or <br> or <p> tags (in any of its forms) 

const SPACES_REGEX = '([[:space:]]|\<br\/?[[:space:]]*\>|\<\/?p\>)*';
const MEDIAFILE_REGEX = '((@@PLUGINFILE@@/(?P<filenames>[^\"\'\?<>]*)))';

class MobileActivityPage extends MobileActivity {	

	private $act = array();
	private $page_media = array();
	private $page_related = array();
	private $page_local_media = array();


	public function __construct(){ 
		$this->component_name = 'mod_page';
    }

	
	function generate_md5($page){
		$contents = $page->name . $page->intro . $page->content;
		$this->md5 = md5($contents);
	}

	function process(){
		global $DB, $CFG, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm = get_coursemodule_from_id('page', $this->id);
		$page = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
		$context = context_module::instance($cm->id);
		$this->generate_md5($page);

		$content = $this->extractAndReplaceLocalMedia($page->content, 'mod_page', 'content',
										0, $context->id, $this->courseroot, $cm->id);
		$content = $this->extractAndReplaceFiles($content, 'mod_page', 'content',
										0, $context->id, $this->courseroot, $cm->id);

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
			if($this->extractMediaImage($pre_content, 'mod_page', 'content', $context->id)){
				$this->saveResizedThumbnail($this->thumbnail_image, $mod_id);
			}
		} else if ($this->thumbnail_image == null){
			// If it does not have an image, we try to extract it from the contents
			$this->extractThumbnailFromContents($pre_content, $mod_id);
		}
		
		// add html header tags etc
		// need to do this to ensure it all has the right encoding when loaded in android webview
		$webpage = '<!DOCTYPE html>';
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
		global $DB;
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
				if (count($this->page_media) > 0 && $this->thumbnail_image == null
				    && $this->extractMediaImage($pre_content, 'mod_page', 'content', $context->id)){
						$this->saveResizedThumbnail($this->thumbnail_image, $cm->id);
				}
				$return_content .= $t;
					
			}
		} else {
			$pre_content = $content;
			$content = $this->extractAndReplaceMedia($content);
			// if page has media and no special icon for page, extract the image for first video
			if (count($this->page_media) > 0 && $this->thumbnail_image == null){
				if($this->extractMediaImage($pre_content, 'mod_page', 'content', $context->id)){
						$this->saveResizedThumbnail($this->thumbnail_image, $cm->id);
				}
			} else if ($this->thumbnail_image == null){
				$this->extractThumbnailFromContents($pre_content, $cm->id);
			}
			$return_content = $content;
				
		}
		return $return_content;
		
	}

	function getLocalMedia(){
		return $this->page_local_media;
	}
	
	function getXML($mod, $counter, &$node, &$xmlDoc, $activity=true){
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
		if(count($this->page_media) > 0 || count($this->page_local_media) > 0){
			$media = $xmlDoc->createElement("media");
			foreach ($this->page_media as $m){
				$temp = $xmlDoc->createElement("file");
				foreach($m as $var => $value) {
					$temp->appendChild($xmlDoc->createAttribute($var))->appendChild($xmlDoc->createTextNode($value));
				}
				$media->appendChild($temp);
			}

			foreach ($this->page_local_media as $m){
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
		
		preg_match_all(MEDIAFILE_REGEX, $content, $files_tmp, PREG_OFFSET_CAPTURE);
		
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return $content;
		}	
		$toreplace = array();

		for($i=0;$i<count($files_tmp['filenames']);$i++){

			$orig_filename = $files_tmp['filenames'][$i][0];
			$filename = urldecode($orig_filename);
			if ( !$this->isLocalMedia($orig_filename) ){
				
				$filepath = '/';
				$fs = get_file_storage();
				$file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
				
				if ($file) {
					$imgfile = $this->courseroot."/images/".urldecode($orig_filename);
					$file->copy_content_to($imgfile);
				} else {
					if($CFG->block_oppia_mobile_export_debug){
					    echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found', PLUGINNAME, $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
						return null;
					}
				}

				if($CFG->block_oppia_mobile_export_debug){
				    echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
				}
			}
			
			$filenameReplace = new StdClass;
			$filenameReplace->filename = $filename;
			$filenameReplace->orig_filename = $orig_filename;
			array_push($toreplace, $filenameReplace);
		}

		foreach($toreplace as $tr){
			$content = str_replace('src="@@PLUGINFILE@@/'.$tr->orig_filename, 'src="images/'.urldecode($tr->orig_filename), $content);
			$content = str_replace('src="@@PLUGINFILE@@/'.urlencode($tr->filename), 'src="images/'.urldecode($tr->orig_filename), $content);
		}
		
		return $content;
	}
	
	private function extractAndReplaceMedia($content){
		global $MEDIA;

		$regex = '((\[\[' . SPACES_REGEX . 'media' . SPACES_REGEX . 'object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-\/,[:space:]]*)([[:space:]]|\<br\/?[[:space:]]*\>)*[\"|\']' . SPACES_REGEX . '\]\]))';

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
			    echo get_string('error_media_attributes', PLUGINNAME).OPPIA_HTML_BR;
				die;
			}
			
			// put the media in both the structure for page ($this->page_media) and for module ($MEDIA)
			$MEDIA[$mediajson->digest] = $mediajson;
			$this->page_media[$mediajson->digest] = $mediajson;
		}
		return str_replace("[[/media]]", "</a>", $content);
	}

	private function extractAndReplaceLocalMedia($content, $component, $filearea, $itemid, $contextid){

		$html = new DOMDocument();
		$parsed = $html->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		if (!$parsed){
		    echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_parsing_html', PLUGINNAME).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
			return null;
		}

		$videos = $html->getElementsByTagName('video');

		foreach ($videos as $video) {
			$video_params = array();
			foreach ($video->childNodes as $source){
				if (($source->nodeName == 'source') && ($source->hasAttribute('src'))){
					$source = $source->getAttribute('src');
					preg_match_all(MEDIAFILE_REGEX, $source, $files_tmp, PREG_OFFSET_CAPTURE);
		
					if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
						continue;
					}
					$filename = $files_tmp['filenames'][0][0];

					if (!$this->isLocalMedia($filename)){
						//If it hasn't been added yet, we include it
						$fileinfo = getFileInfo(urldecode($filename), $component, $filearea, $itemid, $contextid);
						array_push($this->page_local_media, $fileinfo);
					}
					
					$video_params['filename'] = $filename;
					echo get_string('video_included', PLUGINNAME).'<code>'. $filename .'</code>'.OPPIA_HTML_BR;
				}
			}

			if (!$video->hasAttribute('poster')){
			    echo OPPIA_HTML_SPAN_ERROR_START.get_string('missing_video_poster', PLUGINNAME).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
			}
			else{
				$video_params['poster'] = $video->getAttribute('poster');
			}

			$embed = createDOMElemFromTemplate($html, block_oppia_mobile_export.'/video_embed', $video_params);
			$video->parentNode->replaceChild($embed, $video);
        } 

        if (count($this->page_local_media) > 0){
			$content = $html->saveHTML();		
		}
		return $content;
        
	}

	private function isLocalMedia($filename){
		$exists = false;
		foreach ($this->page_local_media as $localMedia){
			if (strpos($localMedia['filename'], $filename) !== false){
				$exists = true;
			}
			if (strpos($localMedia['filename'], urldecode($filename)) !== false){
				$exists = true;
			}
		}
		return $exists;
	}
	
	private function extractMediaImage($content, $component, $filearea, $contextid){
		global $CFG;
		$regex = '(\]\]'.SPACES_REGEX.'\<img[[:space:]]src=[\"|\\\']images/(?P<filenames>[\w\W]*?)[\"|\\\'])';
		
		preg_match_all($regex,$content,$files_tmp, PREG_OFFSET_CAPTURE);
		if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
			return false;
		}
		$filename = $files_tmp['filenames'][0][0];
			
		if($CFG->block_oppia_mobile_export_debug){
		    echo '<span>' . get_string('export_file_trying', PLUGINNAME, $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
		}
		
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
			    echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found', PLUGINNAME, $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
			}
		}
		
		if($CFG->block_oppia_mobile_export_debug){
		    echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
		}
		$this->thumbnail_image = "images/".$filename;
		return true;
	}
	
	private function makePageFilename($sectionno, $name, $lang){
		return sprintf('%02d',$sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
	}
}
