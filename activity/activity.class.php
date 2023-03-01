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

abstract class MobileActivity {

    public $courseroot;
    public $id;
    public $courseid;
    public $server_id;
    public $section;
    public $md5;
    public $password;
    protected $no_questions = 0; // Total no of valid questions.
    public $thumbnailimage = null;
    public $component_name;
    public $print_logs = true;

    public function __construct($params=array()) {
        if (isset($params['id'])) {
            $this->id = $params['id'];
        }
        if (isset($params['courseroot'])) {
            $this->courseroot = $params['courseroot'];
        }
        if (isset($params['server_id'])) {
            $this->server_id = $params['server_id'];
        }
        if (isset($params['course_id'])) {
            $this->courseid = $params['course_id'];
        }
        if (isset($params['section'])) {
            $this->section = $params['section'];
        }
        if (isset($params['password'])) {
            $this->password = $params['password'];
        }
        if (isset($params['print_logs'])) {
            $this->print_logs = $params['print_logs'];
        }
    }

    abstract function process();
    abstract function get_xml($mod, $counter, &$node, &$xmlDoc, $activity=true);

    public function extract_thumbnail_from_intro($content, $moduleid) {
        $this->extract_thumbnail($content, $moduleid, 'intro');
    }

    public function extract_thumbnail_from_contents($content, $moduleid) {
        $this->extract_thumbnail($content, $moduleid, 'content');
    }

    public function extract_thumbnail($content, $moduleid, $filearea) {

        $context = context_module::instance($moduleid);
        // Get the image from the intro section.
        $thumbnail = extractImageFile($content, $this->component_name, $filearea,
                                        0, $context->id, $this->courseroot, $moduleid);

        if ($thumbnail) {
            $this->save_resized_thumbnail($thumbnail, $moduleid);
        }
    }

    public function save_resized_thumbnail($thumbnail, $moduleid, $keeporiginal=false) {
        global $CFG;

        $thumbheight = get_oppiaconfig($this->courseid, 'thumb_height', $CFG->block_oppia_mobile_export_thumb_height, $this->server_id);
        $thumbwidth = get_oppiaconfig($this->courseid, 'thumb_width', $CFG->block_oppia_mobile_export_thumb_width, $this->server_id);

        $this->thumbnailimage = $thumbnail;
        $$imageresized = resizeImage($this->courseroot . "/". $this->thumbnailimage,
                                    $this->courseroot."/images/".$moduleid,
                                    $thumbwidth, $thumbheight);

        if ($$imageresized) {
            $this->thumbnailimage = "/images/" . $$imageresized;
            if (!$keeporiginal) {
                unlink($this->courseroot."/".$thumbnail) or die(get_string('error_file_delete', PLUGINNAME));
            }
        } else {
            $link = $CFG->wwwroot."/course/modedit.php?return=0&sr=0&update=".$moduleid;
            echo '<span class="export-error">'.get_string('error_edit_page', PLUGINNAME, $link).'</span><br/>';
        }
    }

    function has_password() {
        return (($this->password != NULL) && ($this->password != ''));
    }

    protected function get_activity_node($xmldoc, $module, $counter) {
        $act = $xmldoc->createElement("activity");
        $act->appendChild($xmldoc->createAttribute("type"))->appendChild($xmldoc->createTextNode($module->modname));
        $act->appendChild($xmldoc->createAttribute("order"))->appendChild($xmldoc->createTextNode($counter));
        $act->appendChild($xmldoc->createAttribute("digest"))->appendChild($xmldoc->createTextNode($this->md5));

        return $act;
    }

    protected function addTitleXMLNodes($xmldoc, $module, $activitynode) {
        $this->addLangXMLNodes($xmldoc, $activitynode, $module->name, "title");
    }

    protected function addDescriptionXMLNodes($xmldoc, $module, $activitynode) {
        $this->addLangXMLNodes($xmldoc, $activitynode, $module->intro, "description");
    }

    protected function addLangXMLNodes($xmldoc, $activitynode, $content, $propertyname) {
        global $DEFAULT_LANG;

        $title = extractLangs($content);
        if (is_array($title) && count($title)>0) {
            foreach($title as $l=>$t) {
                $temp = $xmldoc->createElement($propertyname);
                $temp->appendChild($xmldoc->createCDATASection(strip_tags($t)));
                $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($l));
                $activitynode->appendChild($temp);
            }
        } else {
            $title = strip_tags($content);
            if ($title != "") {
                $temp = $xmldoc->createElement($propertyname);
                $temp->appendChild($xmldoc->createCDATASection(strip_tags($title)));
                $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULT_LANG));
                $activitynode->appendChild($temp);
            }
        }
    }

    protected function addThumbnailXMLNode($xmldoc, $activitynode) {

        if ($this->thumbnailimage) {
            $temp = $xmldoc->createElement("image");
            $temp->appendChild($xmldoc->createAttribute("filename"))->appendChild($xmldoc->createTextNode($this->thumbnailimage));
            $activitynode->appendChild($temp);
        }
    }

    abstract function get_no_questions();
}
