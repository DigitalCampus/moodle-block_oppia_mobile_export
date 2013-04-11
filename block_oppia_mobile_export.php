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

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        
        if (!has_capability('block/oppia_mobile_export:addinstance', get_context_instance(CONTEXT_COURSE, $COURSE->id))) {
        	return $this->content;
        }
        $this->content->text = "Current MQuiz API: ".$CFG->block_oppia_mobile_export_mquiz_url;
        $this->content->text .= "<form action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export.php' method='post'>";
        $this->content->text .= "<input type='hidden' name='id' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        $this->content->text .= "<input type='submit' name='submit' value='Export to Oppia Package'>";
        $this->content->text .= "</form>";
        
        $this->content->footer = '<a href="http://mquiz.org">Mquiz</a>';
        if (empty($this->instance)) {
            return $this->content;
        }
           

        return $this->content;
    }
    
}

?>