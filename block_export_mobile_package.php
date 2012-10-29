<?php 
/**
 * Export to Mobile Package
 * @author Alex Little
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package block_block_export_mobile_package
 */
 

class block_export_mobile_package extends block_base {
	
    function init() {
        $this->title = get_string('pluginname','block_export_mobile_package');
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
        
        if (!has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
        	return $this->content;
        }
        $this->content->text = "Current MQuiz API: ".$CFG->block_export_mobile_package_mquiz_url;
        $this->content->text .= "<form action='".$CFG->wwwroot."/blocks/export_mobile_package/export.php' method='post'>";
        $this->content->text .= "MQuiz username/email:<input type='text' name='mquizuser'>";
        $this->content->text .= "<br/>";
        $this->content->text .= "MQuiz password:<input type='password' name='mquizpass'>";
        $this->content->text .= "<input type='hidden' name='id' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        $this->content->text .= "<input type='submit' name='submit' value='Export to mobile'>";
        $this->content->text .= "</form>";
        
        $this->content->footer = '<a href="http://mquiz.org">Mquiz</a>';
        if (empty($this->instance)) {
            return $this->content;
        }
           

        return $this->content;
    }
    
}

?>