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
        global $USER, $CFG, $COURSE, $OUTPUT;
        
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

        $settings = array(
            'id' => $COURSE->id,
            'sesskey' => sesskey(),
            'wwwplugin' => $CFG->wwwroot.PLUGINPATH,
            'servers' => $servers,
            'styles' => $this->getStyles(),
            'default_server' => $CFG->block_oppia_mobile_export_default_server
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
    
    
    private function getStyles(){
        $styles = array();
        if ($handle = opendir(dirname(__FILE__).'/styles/')) {
            while (false !== ($file = readdir($handle))) {
                if($file!="." && $file!=".." && !is_dir(dirname(__FILE__).'/styles/'.$file)){
                    array_push($styles, $file);
                }
            }
        }
        return $styles;
    }
}

?>