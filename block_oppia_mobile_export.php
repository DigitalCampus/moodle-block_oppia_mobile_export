<?php
/**
 * Oppia Mobile Export
 * @author Alex Little
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package block_oppia_mobile_export
 */

require_once(dirname(__FILE__) . '/constants.php');
$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'version.php');

class block_oppia_mobile_export extends block_base {
    
    function init() {
        $this->title = get_string('pluginname', PLUGINNAME);
    }

    function instance_allow_config() {
        return false;
    }
    
    function has_config() {
        return true;
    }
    
    function get_content() {
        global $USER, $CFG, $COURSE, $OUTPUT, $pluginroot;
        
        if ($this->content !== NULL || !isset($COURSE->id) || $COURSE->id == 1) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        if (!has_capability('block/oppia_mobile_export:addinstance', context_course::instance($COURSE->id))) {
            return $this->content;
        }

        $servers = array();
        foreach (get_oppiaservers() as $s){
            array_push($servers, $s);
        }

        $current_style = get_oppiaconfig($COURSE->id, 'stylesheet', STYLESHEET_DEFAULT);

        $settings = array(
            'id' => $COURSE->id,
            'sesskey' => sesskey(),
            'wwwplugin' => $CFG->wwwroot.PLUGINPATH,
            'servers' => $servers,
            'styles' => $this->getStyles($current_style),
            'default_server' => $CFG->block_oppia_mobile_export_default_server,
            'current_style' => $current_style,
        );
        
        $this->content->text = $OUTPUT->render_from_template(PLUGINNAME.'/block', $settings);
        
        require($pluginroot . 'version.php'); // to get release no
        $this->content->footer = $OUTPUT->render_from_template(PLUGINNAME.'/block_footer',
            array( 'release' => $plugin->release));

        if (empty($this->instance)) {
            return $this->content;
        }

        return $this->content;
    }
    
    
    private function getStyles($current_style){

        $styles_dir = dirname(__FILE__).'/'.STYLES_DIR.STYLES_THEMES_DIR;
        $styles = array();
        if ($handle = opendir($styles_dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == "." || $file == ".." || is_dir($styles_dir.$file)){
                    continue;
                }

                list($theme, $extn) = explode('.', $file);
                $ends_extra_suffix = substr($theme, -strlen(STYLES_EXTRA_SUFFIX)) === STYLES_EXTRA_SUFFIX;
                if ($extn == 'scss' && !$ends_extra_suffix){

                    array_push($styles, array(
                        'theme' => $theme,
                        'name' => ucwords($theme, " -"),
                        'selected' => ($theme == $current_style)
                    ));    
                }
                
            }
        }
        return $styles;
    }
}
