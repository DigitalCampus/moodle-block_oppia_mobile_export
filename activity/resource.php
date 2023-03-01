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

class MobileActivityResource extends MobileActivity {
    
    private $resource;
    private $resourcefilename = null;
    private $resourcetype = null;
    

    public function __construct($params=array()) { 
        parent::__construct($params);
        $this->componentname = 'mod_resource';
    }
    

    function generate_md5($file) {
        $resourcefile = $this->courseroot."/resources/".$file->get_filename();
        $md5contents = $file->get_filename() . md5_file($resourcefile);

        $this->md5 = md5($md5contents);
    }
    

    function process() {
        global $DB;
        $cm = get_coursemodule_from_id('resource', $this->id);
        $this->resource = $DB->get_record('resource', array('id'=>$cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $this->extractResource($context->id);
    
        // get the image from the intro section
        $this->extract_thumbnail_from_intro($this->resource->intro, $cm->id);
        
        if ($this->resourcetype == "image/jpeg" && $this->thumbnailimage == null) {
            $this->save_resized_thumbnail($this->resourcefilename, $cm->id, true);
        }
    }

    
    function get_xml($mod, $counter, &$node, &$xmldoc, $activity=true) {
        global $defaultlang;
        
        if (!$activity) {
            return;
        }

        $act = $this->get_activity_node($xmldoc, $mod, $counter);
        $this->add_lang_xml_nodes($xmldoc, $act, $mod->name, "title");
        $this->add_lang_xml_nodes($xmldoc, $act, $this->resource->intro, "description");
        $this->add_thumbnail_xml_node($xmldoc, $act);

        $temp = $xmldoc->createElement("location",$this->resourcefilename);
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($defaultlang));
        $temp->appendChild($xmldoc->createAttribute("type"))->appendChild($xmldoc->createTextNode($this->resourcetype));
        $act->appendChild($temp);

        $node->appendChild($act);
    }
    
    private function extractResource($contextid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = reset($files);

        $filename = $this->filter_filename($file->get_filename());
        $resourcefile = $this->courseroot."/resources/".$filename;
        $success = $file->copy_content_to($resourcefile);

        $finfo = new finfo(FILEINFO_MIME);
        $type = $finfo->file($resourcefile);
        $this->resourcetype = substr($type, 0, strpos($type, ';'));
        
        $this->generate_md5($file);
        $this->resourcefilename = "/resources/".$filename;
    }

    private function filter_filename($filename) {
        return preg_replace(
            '~
            [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
            [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
            [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
            [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
            ~x',
            '_', $filename);
    }

    function get_no_questions() {
        return null;
    }
}
