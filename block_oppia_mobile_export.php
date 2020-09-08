<?php 
/**
 * Oppia Mobile Export
 * @author Alex Little
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package block_oppia_mobile_export
 */
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/lib.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/version.php');

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
        
        if (!has_capability('block/oppia_mobile_export:addinstance', context_course::instance($COURSE->id))) {
        	return $this->content;
        }
        $this->content->text .= '<ul class="nav nav-tabs" role="tablist">';
        $this->content->text .= '<li class="nav-item"><a class="nav-link active" href="#topackage" data-toggle="tab" role="tab" aria-expanded="true">Oppia package</a></li>';
            $this->content->text .= '<li class="nav-item"><a class="nav-link" href="#toprint" data-toggle="tab" role="tab" aria-expanded="false">To print</a></li>';
        $this->content->text .= '</ul>';

        $this->content->text .= '<div class="tab-content"><div class="tab-pane active" id="topackage" role="tabpanel" aria-expanded="true">';
        $this->content->text .= "<form action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export1.php' method='post'>";
        $this->content->text .= "<input type='hidden' name='id' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        // show the OppiaServer options
        $servers = get_oppiaservers();
       	$this->content->text .= "<br/><p>".get_string('servers_block_select_connection','block_oppia_mobile_export')."<br/>";
        $this->content->text .= "<select name=\"server\" class=\"custom-select\" style=\"width:100%;\">";
        foreach ($servers as $s){
        	$this->content->text .= "<option value='$s->id' ";
        	if ($s->defaultserver != 0){
        		$this->content->text .= "selected='selected'";
        	}
        	$this->content->text .= ">".$s->servername. " (".$s->url.")</option>";
        }
        if (count($servers) == 0){
        	$this->content->text .= "<option value='default' selected='selected'>". $CFG->block_oppia_mobile_export_default_server ."</option>";
        }
        $this->content->text .= "</select></p>";
        $this->content->text .= "<p>".get_string('servers_block_add','block_oppia_mobile_export',$CFG->wwwroot."/blocks/oppia_mobile_export/servers.php")."</p>";

       
        // Show the style options
        if ($handle = opendir(dirname(__FILE__).'/styles/')) {
	        $this->content->text .= "<p>".get_string('oppia_block_style','block_oppia_mobile_export')."<br/>";
	        $this->content->text .= "<select name=\"stylesheet\" class=\"custom-select\" style=\"width:100%;\">";
	        while (false !== ($file = readdir($handle))) {
	        	if($file!="." && $file!=".." && !is_dir(dirname(__FILE__).'/styles/'.$file)){
	        		$this->content->text .= "<option value='".$file."'>".$file."</option>";
	        	}
	        }
	        $this->content->text .= "</select>";
	        $this->content->text .= "</p>";
        }
        
        $this->content->text .= "<p>".get_string('course_status','block_oppia_mobile_export')."<br/>";
        $this->content->text .= "<select name=\"course_status\" class=\"custom-select\" style=\"width:100%;\">";
        $this->content->text .= "<option value='draft'>".get_string('course_status_draft','block_oppia_mobile_export')."</option>";
        $this->content->text .= "<option value='live'>".get_string('course_status_live','block_oppia_mobile_export')."</option>";
        $this->content->text .= "</select>";
        $this->content->text .= "</p>";
        
        $this->content->text .= "<p><input type='submit' name='submit' class=\"btn btn-primary\" value='".get_string('oppia_block_export_button','block_oppia_mobile_export')."'>";
        $this->content->text .= "</form>";

        $this->content->text .= '</div><div class="tab-pane" id="toprint" role="tabpanel" aria-expanded="false">';

        $this->content->text .= "<form action='".$CFG->wwwroot."/blocks/oppia_mobile_export/export2print.php' method='post'>";
        $this->content->text .= "<input type='hidden' name='courseid' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        if ($handle = opendir(dirname(__FILE__).'/styles/')) {
        	$this->content->text .= "<br/><p>".get_string('oppia_block_style','block_oppia_mobile_export')."<br/>";
        	$this->content->text .= "<select name='stylesheet' class=\"custom-select\" style=\"width:100%;\">";
        	while (false !== ($file = readdir($handle))) {
        		if($file!="." && $file!=".." && !is_dir(dirname(__FILE__).'/styles/'.$file)){
        			$this->content->text .= "<option value='".$file."'>".$file."</option>";
        		}
        	}
        	$this->content->text .= "</select>";
        	$this->content->text .= "</p>";
        }
        $this->content->text .= "<input type='submit' class=\"btn\" name='submit' value='".get_string('oppia_block_export2print_button','block_oppia_mobile_export')."'>";
        $this->content->text .= "</form></p>";

        $this->content->text .= '</div></div>';
        
        $this->content->text .= "<hr />";
        $this->content->footer = '<a href="https://digital-campus.org/oppiamobile/">OppiaMobile</a> '. get_string('release', 'block_oppia_mobile_export');
        if (empty($this->instance)) {
            return $this->content;
        }

        return $this->content;
    }
    
}

?>