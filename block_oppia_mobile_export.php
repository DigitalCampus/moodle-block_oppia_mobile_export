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
 * Oppia Mobile Export
 * @author Alex Little
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package block_oppia_mobile_export
 */

require_once($CFG->dirroot.'/config.php');
require_once(dirname(__FILE__) . '/constants.php');
$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'version.php');

class block_oppia_mobile_export extends block_base {

    public function init() {
        $this->title = get_string('pluginname', PLUGINNAME);
    }

    public function instance_allow_config() {
        return false;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $CFG, $COURSE, $OUTPUT, $pluginroot;

        if ($this->content !== null || !isset($COURSE->id) || $COURSE->id == 1) {
            return $this->content;
        }

        $this->content = new stdClass;

        if (!has_capability('block/oppia_mobile_export:addinstance', context_course::instance($COURSE->id))) {
            return $this->content;
        }

        $servers = array();
        foreach (get_oppiaservers() as $s) {
            array_push($servers, $s);
        }

        $currentstyle = get_oppiaconfig($COURSE->id, 'stylesheet', STYLESHEET_DEFAULT, true);

        $settings = array(
            'id' => $COURSE->id,
            'sesskey' => sesskey(),
            'wwwplugin' => $CFG->wwwroot.PLUGINPATH,
            'servers' => $servers,
            'styles' => $this->get_styles($currentstyle),
            'default_server' => $CFG->block_oppia_mobile_export_default_server,
            'current_style' => $currentstyle,
        );

        $this->content->text = $OUTPUT->render_from_template(PLUGINNAME.'/block', $settings);

        require($pluginroot . 'version.php'); // To get release no.
        $this->content->footer = $OUTPUT->render_from_template(PLUGINNAME.'/block_footer',
            array( 'release' => $plugin->release));

        if (empty($this->instance)) {
            return $this->content;
        }

        return $this->content;
    }

    private function get_styles($currentstyle) {

        $stylesdir = dirname(__FILE__).'/'.STYLES_DIR.STYLES_THEMES_DIR;
        $styles = array();
        if ($handle = opendir($stylesdir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == "." || $file == ".." || is_dir($stylesdir.$file)) {
                    continue;
                }

                list($theme, $extn) = explode('.', $file);
                $endsextrasuffix = substr($theme, -strlen(STYLES_EXTRA_SUFFIX)) === STYLES_EXTRA_SUFFIX;
                if ($extn == 'scss' && !$endsextrasuffix) {

                    array_push($styles, array(
                        'theme' => $theme,
                        'name' => ucwords($theme, " -"),
                        'selected' => ($theme == $currentstyle)
                    ));
                }
            }
        }
        return $styles;
    }
}
