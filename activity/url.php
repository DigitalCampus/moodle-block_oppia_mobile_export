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

/**
 * MobileActivityUrl class file
 *
 *
 *
 * @package    block_oppia_mobile_export
 * @copyright  2023 Digital Campus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class MobileActivityUrl extends MobileActivity {

    private $url;

    public function __construct($params=array()) {
        parent::__construct($params);
        $this->componentname = 'mod_url';
    }

    private function generate_md5($activity) {
        $md5contents = $activity->intro . $activity->externalurl;
        $this->md5 = md5($md5contents);
    }

    public function process() {
        global $DB;
        $cm = get_coursemodule_from_id('url', $this->id);
        $this->url = $DB->get_record('url', array('id' => $cm->instance), '*', MUST_EXIST);
        $this->generate_md5($this->url);
        // Get the image from the intro section.
        $this->extract_thumbnail_from_intro($this->url->intro, $cm->id);
    }

    public function get_xml($mod, $counter, &$node, &$xmldoc, $activity) {
        global $DEFAULTLANG;

        if (!$activity) {
            return;
        }

        $act = $this->get_activity_node($xmldoc, $mod, $counter);
        $this->add_lang_xml_nodes($xmldoc, $act, $mod->name, "title");
        $this->add_lang_xml_nodes($xmldoc, $act, $this->url->intro, "description");

        $temp = $xmldoc->createElement("location", $this->url->externalurl);
        $temp->appendChild($xmldoc->createAttribute("lang"))->appendChild($xmldoc->createTextNode($DEFAULTLANG));
        $act->appendChild($temp);

        $this->add_thumbnail_xml_node($xmldoc, $act);
        $node->appendChild($act);
    }

    public function get_no_questions() {
        return null;
    }
}
