<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

class MobileActivityPage extends MobileActivity {

    private $act = array();
    private $pagemedia = array();
    private $pagerelated = array();
    private $pagelocalmedia = array();
    private $videooverlay = false;

    public function __construct($params=array()) {
        parent::__construct($params);
        $this->componentname = 'mod_page';
        if (isset($params['videooverlay'])) {
            $this->videooverlay = $params['videooverlay'];
        }
    }

    private function generate_md5($page) {
        $contents = $page->name . $page->intro . $page->content;
        $this->md5 = md5($contents);
    }

    public function process() {
        global $DB, $DEFAULTLANG;
        $cm = get_coursemodule_from_id('page', $this->id);
        $page = $DB->get_record('page', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $this->generate_md5($page);

        $content = $this->extract_and_replace_local_media($page->content, 'mod_page', 'content',
                                        0, $context->id, $this->courseroot, $cm->id);
        $content = $this->extract_and_replace_files($content, 'mod_page', 'content',
                                        0, $context->id, $this->courseroot, $cm->id);

        // Get the image from the intro section.
        $this->extract_thumbnail_from_intro($page->intro, $cm->id);

        $langs = extract_langs($content, false, false, false);
        if (is_array($langs) && count($langs) > 0) {
            foreach ($langs as $lang => $text) {
                // Process individually each language.
                $this->process_content($context, $cm->id, $text, $lang);
            }
        } else {
            $this->process_content($context, $cm->id, $content, $DEFAULTLANG);
        }
    }

    private function process_content($context, $modid, $content, $lang) {
        $precontent = $content;

        $content = $this->extract_and_replace_media($content);
        // If page has media and no special icon for page, extract the image for first video.
        if ((count($this->pagemedia) > 0 || count($this->pagelocalmedia) > 0) && $this->thumbnailimage == null) {
            if ($this->extract_media_image($precontent, 'mod_page', 'content', $context->id)) {
                $this->save_resized_thumbnail($this->thumbnailimage, $modid, false);
            }
        } else if ($this->thumbnailimage == null) {
            // If it does not have an image, we try to extract it from the contents.
            $this->extract_thumbnail_from_contents($precontent, $modid);
        }

        // Add html header tags etc.
        // Need to do this to ensure it all has the right encoding when loaded in android webview.
        $webpage = '<!DOCTYPE html>';
        $webpage .= '<html><head>';
        $webpage .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $webpage .= '<link href="style.css" rel="stylesheet" type="text/css"/>';
        $webpage .= '<script src="js/jquery-3.6.0.min.js"></script>';
        $webpage .= '<script src="js/oppia.js"></script>';
        $webpage .= '</head>';
        $webpage .= '<body>'.$content.'</body></html>';

        $pagefilename = $this->make_page_filename($this->section, $modid, $lang);
        $index = $this->courseroot."/".$pagefilename;
        $fh = fopen($index, 'w');
        if ($fh !== false) {
            fwrite($fh, $webpage);
            fclose($fh);
        }

        $o = new stdClass();
        $o->lang = $lang;
        $o->filename = $pagefilename;
        array_push($this->act, $o);
        unset($pagefilename);
    }

    public function get_local_media() {
        return $this->pagelocalmedia;
    }

    public function get_xml($mod, $counter, &$node, &$xmldoc, $activity) {
        if ($activity) {
            $struct = $this->get_activity_node($xmldoc, $mod, $counter);
            $node->appendChild($struct);
        } else {
            $struct = $xmldoc->createElement("page");
            $struct->appendChild($xmldoc->createAttribute("id"))->appendChild($xmldoc->createTextNode($this->id));
            $node->appendChild($struct);
        }

        $this->add_lang_xml_nodes($xmldoc, $struct, $mod->name, "title");
        $this->add_thumbnail_xml_node($xmldoc, $struct);

        // Add in page media.
        if (count($this->pagemedia) > 0 || count($this->pagelocalmedia) > 0) {
            $media = $xmldoc->createElement("media");
            foreach ($this->pagemedia as $m) {
                $temp = $xmldoc->createElement("file");
                foreach ($m as $var => $value) {
                    $temp->appendChild($xmldoc->createAttribute($var))->appendChild($xmldoc->createTextNode($value));
                }
                $media->appendChild($temp);
            }

            foreach ($this->pagelocalmedia as $m) {
                $temp = $xmldoc->createElement("file");
                foreach ($m as $var => $value) {
                    $temp->appendChild($xmldoc->createAttribute($var))->appendChild($xmldoc->createTextNode($value));
                }
                $media->appendChild($temp);
            }
            $struct->appendChild($media);
        }
        if (count($this->pagerelated) > 0) {
            $related = $xmldoc->createElement("related");
            foreach ($this->pagerelated as $r) {
                $temp = $xmldoc->createElement("activity");
                $temp->appendChild($xmldoc->createAttribute("order"))->appendChild($xmldoc->createTextNode($r->order));
                $temp->appendChild($xmldoc->createAttribute("digest"))->appendChild($xmldoc->createTextNode($r->digest));
                foreach ($r->activity as $a) {
                    $title = $xmldoc->createElement("title");
                    $title->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($a->lang));
                    $title->appendChild($xmldoc->createTextNode(strip_tags($a->title)));
                    $temp->appendChild($title);
                }
                $related->appendChild($temp);
            }
            $struct->appendChild($related);
        }

        foreach ($this->act as $act) {
            $temp = $xmldoc->createElement("location", $act->filename);
            $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($act->lang));
            $struct->appendChild($temp);
        }
    }

    private function extract_and_replace_files($content, $component, $filearea, $itemid, $contextid) {
        global $CFG;

        preg_match_all(MEDIAFILE_REGEX, $content, $filestmp, PREG_OFFSET_CAPTURE);

        if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
            return $content;
        }
        $toreplace = array();
        $count = count($filestmp['filenames']);

        for ($i = 0; $i < $count; $i++) {

            $origfilename = $filestmp['filenames'][$i][0];
            $filename = urldecode($origfilename);
            $cleanfilename = filename_to_ascii($filename);
            if ( !$this->is_local_media($origfilename) ) {

                $filepath = '/';
                $fs = get_file_storage();
                $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

                if ($file) {
                    $imgfile = $this->courseroot."/images/".$cleanfilename;
                    $file->copy_content_to($imgfile);
                } else {
                    if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                        echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found', PLUGINNAME,
                            $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
                        return null;
                    }
                }

                if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                    echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
                }
            }

            $filenamereplace = new StdClass;
            $filenamereplace->filename = $filename;
            $filenamereplace->origfilename = $origfilename;
            $filenamereplace->cleanfilename = $cleanfilename;
            array_push($toreplace, $filenamereplace);
        }

        foreach ($toreplace as $tr) {
            $content = str_replace(MEDIAFILE_PREFIX.'/'.$tr->origfilename, 'images/'.$tr->cleanfilename, $content);
            $content = str_replace(MEDIAFILE_PREFIX.'/'.urlencode($tr->filename), 'images/'.$tr->cleanfilename, $content);
        }

        return $content;
    }

    private function extract_and_replace_media($content) {
        global $MEDIA;

        preg_match_all(EMBED_MEDIA_REGEX, $content, $mediatmp, PREG_OFFSET_CAPTURE);

        if (!isset($mediatmp['mediaobject']) || count($mediatmp['mediaobject']) == 0) {
            return $content;
        }
        $count = count($mediatmp['mediaobject']);
        for ($i = 0; $i < $count; $i++) {
            $mediajson = json_decode($mediatmp['mediaobject'][$i][0]);
            $toreplace = $mediatmp[0][$i][0];

            $r = "<a href='/video/".$mediajson->filename."'>";
            $content = str_replace($toreplace, $r, $content);
            // Check all the required attrs exist.
            if (!isset($mediajson->digest) || !isset($mediajson->download_url) || !isset($mediajson->filename)) {
                echo get_string('error_media_attributes', PLUGINNAME).OPPIA_HTML_BR;
                die;
            }

            // Put the media in both the structure for page ($this->pagemedia) and for module ($MEDIA).
            $MEDIA[$mediajson->digest] = $mediajson;
            $this->pagemedia[$mediajson->digest] = $mediajson;
        }
        return str_replace("[[/media]]", "</a>", $content);
    }

    private function extract_and_replace_local_media($content, $component, $filearea, $itemid, $contextid) {
        global $CFG;

        $contentstoparse = '<div>'.$content.'</div>'; // We add a fake root element to avoid problems with libxml.
        $contentstoparse = mb_convert_encoding($contentstoparse, 'HTML-ENTITIES', 'UTF-8');
        $contentstoparse = utf8_decode($contentstoparse);

        $html = new DOMDocument('1.0', 'utf-8');
        libxml_use_internal_errors(true);
        $parsed = $html->loadHTML($contentstoparse, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors(false);
        $html->encoding = 'utf-8';

        if (!$parsed) {
            echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_parsing_html', PLUGINNAME).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
            return null;
        }

		$videos = $this->extract_and_replace_video_tags($html, $component, $filearea, $itemid, $contextid);
        $audios = $this->extract_and_replace_audio_tags($html, $component, $filearea, $itemid, $contextid);
		$resources = $this->extract_and_replace_resource_links($html, $component, $filearea, $itemid, $contextid);

		if ($videos > 0 || $resources > 0 || $audios > 0){
			$content = $html->saveHTML($html->documentElement);	
		}

		return $content;
        
	}

	private function extract_and_replace_video_tags($html, $component, $filearea, $itemid, $contextid){
		global $CFG;

		$videos = $html->getElementsByTagName('video');
		$videoslength = $videos->length;
        
		if ($videoslength <= 0){
			return 0;
		}

        for ($i = 0; $i < $videoslength; $i++) {
            $video = $videos->item(0); // We always get the first one, as the previous one would be replaced by now.
            $videoparams = array();

            foreach ($video->childNodes as $source) {
                if (($source->nodeName == 'source') && ($source->hasAttribute('src'))) {
                    $source = $source->getAttribute('src');
                    preg_match_all(MEDIAFILE_REGEX, $source, $filestmp, PREG_OFFSET_CAPTURE);

                    if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
                        continue;
                    }
                    $filename = $filestmp['filenames'][0][0];

                    if (!$this->is_local_media($filename)) {
                        // If it hasn't been added yet, we include it.
                        $fileinfo = get_file_info(urldecode($filename), $component, $filearea, $itemid, $contextid);
                        array_push($this->pagelocalmedia, $fileinfo);
                    }

                    $videoparams['filename'] = $filename;

                    if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                        echo get_string('video_included', PLUGINNAME).'<code>'. $filename .'</code>'.OPPIA_HTML_BR;
                    }
                }
			}
			if (!$video->hasAttribute('poster')) {
                if ($this->printlogs) {
                    echo OPPIA_HTML_SPAN_ERROR_START.get_string('missing_video_poster',
                        PLUGINNAME).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
                }
            }
			else{
				$videoparams['poster'] = $video->getAttribute('poster');
				if ($this->videooverlay){
					$videoparams['video_class'] = 'video-overlay';
				}
			}

			$embed = create_dom_element_from_template($html, PLUGINNAME.'/video_embed', $videoparams);
			$video->parentNode->replaceChild($embed, $video);
        }

        return count($this->pagelocalmedia);
	}

	private function extract_and_replace_resource_links($html, $component, $filearea, $itemid, $contextid){
		global $CFG;

		$links = $html->getElementsByTagName('a');
		$linkslength = $links->length;
		$resourcesfound = 0;

		if ($linkslength <= 0){
			return 0;
		}

		foreach ($links as $link) {
			$href = $link->getAttribute('href');
			preg_match_all(MEDIAFILE_REGEX, $href, $files_tmp, PREG_OFFSET_CAPTURE);
		
			if(!isset($files_tmp['filenames']) || count($files_tmp['filenames']) == 0){
				continue;
			}
			$filename = $files_tmp['filenames'][0][0];

			if (is_file_a_resource($filename)){
				$filename = urldecode($filename);
				$cleanfilename = filename_to_ascii($filename);
					
				$filepath = '/';
				$fs = get_file_storage();
				$file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
				
				if ($file) {
					$resourcefile = $this->courseroot."/resources/".$cleanfilename;
					$file->copy_content_to($resourcefile);
				} else {
					if($CFG->block_oppia_mobile_export_debug and $this->printlogs){
					    echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found', PLUGINNAME, $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
						return null;
					}
				}

				if($CFG->block_oppia_mobile_export_debug and $this->printlogs){
				    echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
				}

				$link->setAttribute('href', 'resources/'.$cleanfilename);

				$version_compat = array('version_name'=>'v7.4.5', 'version_code'=>110);
				$warningmsg = create_dom_element_from_template($html, PLUGINNAME.'/compat_warning', $version_compat);
				$link->parentNode->insertBefore($warningmsg, $link->nextSibling);

				$resourcesfound++;
			}
		}
		return $resourcesfound;
	}

    private function extract_and_replace_audio_tags($html, $component, $filearea, $itemid, $contextid) {
        global $CFG;

        $audios = $html->getElementsByTagName('audio');
        $audioslength = $audios->length;

        if ($audioslength <= 0){
            return 0;
        }

        for ($i = 0; $i < $audioslength; $i++) {
            $audio = $audios->item($i); // We always get the first one, as the previous one would be replaced by now.
            $audioparams = array();

            foreach ($audio->childNodes as $source) {
                if (($source->nodeName == 'source') && ($source->hasAttribute('src'))) {
                    $source = $source->getAttribute('src');
                    preg_match_all(MEDIAFILE_REGEX, $source, $filestmp, PREG_OFFSET_CAPTURE);

                    if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
                        continue;
                    }
                    $filename = $filestmp['filenames'][0][0];

                    if (!$this->is_local_media($filename)) {
                        // If it hasn't been added yet, we include it.
                        $fileinfo = get_file_info(urldecode($filename), $component, $filearea, $itemid, $contextid);
                        array_push($this->pagelocalmedia, $fileinfo);
                    }

                    $audioparams['filename'] = $filename;

                    if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                        echo get_string('audio_included', PLUGINNAME).'<code>'. $filename .'</code>'.OPPIA_HTML_BR;
                    }
                }
            }

            $embed = create_dom_element_from_template($html, PLUGINNAME.'/audio_embed', $audioparams);
            $audio->parentNode->replaceChild($embed, $audio);
        }

        return count($this->pagelocalmedia);
    }

	private function isLocalMedia($filename){
		$exists = false;
		foreach ($this->page_local_media as $local_media){
			if (strpos($local_media['filename'], $filename) !== false){
				$exists = true;
			}
			if (strpos($local_media['filename'], urldecode($filename)) !== false){
				$exists = true;
			}
		}
		return $exists;
	}
	

    private function is_local_media($filename) {
        $exists = false;
        foreach ($this->pagelocalmedia as $localmedia) {
            if (strpos($localmedia['filename'], $filename) !== false) {
                $exists = true;
            }
            if (strpos($localmedia['filename'], urldecode($filename)) !== false) {
                $exists = true;
            }
        }
        return $exists;
    }

    private function extract_media_image($content, $component, $filearea, $contextid) {
        global $CFG;

        preg_match_all(EMBED_MEDIA_IMAGE_REGEX, $content, $filestmp, PREG_OFFSET_CAPTURE);
        if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
            return false;
        }
        $filename = $filestmp['filenames'][0][0];

        if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
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
            if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
                echo OPPIA_HTML_SPAN_ERROR_START.get_string('error_file_not_found',
                    PLUGINNAME, $filename).OPPIA_HTML_SPAN_END.OPPIA_HTML_BR;
            }
        }

        if ($CFG->block_oppia_mobile_export_debug && $this->printlogs) {
            echo get_string('export_file_success', PLUGINNAME, $filename).OPPIA_HTML_BR;
        }
        $this->thumbnailimage = "images/".$filename;
        return true;
    }

    private function make_page_filename($sectionno, $name, $lang) {
        return sprintf('%02d', $sectionno)."_".strtolower(preg_replace("/[^A-Za-z0-9]/i", "_", $name))."_".strtolower($lang).".html";
    }

    public function get_no_questions() {
        return null;
    }
}
