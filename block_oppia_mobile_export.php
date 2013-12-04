<?php 
/**
 * Oppia Mobile Export
 * @author Alex Little
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package block_oppia_mobile_export
 */
 

class block_oppia_mobile_export extends block_base {
	
    function init() {
        $this->title = get_string('pluginname','block_oppia_mobile_export');
    }

    function instance_allow_config() {
    	return false;
    }
    
    function has_config() {
    	return true;
    }
    
    function get_content() {
        global $USER, $CFG, $COURSE;

        if ($this->content !== NULL || !isset($COURSE->id) || $COURSE->id == 1) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        if (!has_capability('block/oppia_mobile_export:addinstance', get_context_instance(CONTEXT_COURSE, $COURSE->id))) {
        	return $this->content;
        }
        $this->content->text = "<p>".get_string('oppia_block_api','block_oppia_mobile_export')." <a href='".$CFG->block_oppia_mobile_export_url."'>".$CFG->block_oppia_mobile_export_url."</a></p>";
        $this->content->text .= "<form action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export1.php' method='post'>";
        $this->content->text .= "<input type='hidden' name='id' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        if ($handle = opendir(dirname(__FILE__).'/styles/')) {
	        $this->content->text .= "<p>".get_string('oppia_block_style','block_oppia_mobile_export')."<br/>";
	        $this->content->text .= "<select name='stylesheet'>";
	        while (false !== ($file = readdir($handle))) {
	        	if($file!="." && $file!=".." && !is_dir(dirname(__FILE__).'/styles/'.$file)){
	        		$this->content->text .= "<option value='".$file."'>".$file."</option>";
	        	}
	        }
	        $this->content->text .= "</select>";
	        $this->content->text .= "</p>";
        }
        $this->content->text .= "<input type='submit' name='submit' value='".get_string('oppia_block_export_button','block_oppia_mobile_export')."'>";
        $this->content->text .= "</form>";
        
        $this->content->footer = '<a href="http://oppia-mobile.org">OppiaMobile</a>';
        if (empty($this->instance)) {
            return $this->content;
        }
           

        return $this->content;
    }
    
}

?>