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
        $this->content->text .= '<form action="'.$CFG->wwwroot.PLUGINPATH.'export/step1.php" method="post">';
        $this->content->text .= '<input type="hidden" name="id" value="'.$COURSE->id.'">';
        $this->content->text .= '<input type="hidden" name="sesskey" value="'.sesskey().'">';
        // show the OppiaServer options
        $servers = get_oppiaservers();
        $this->content->text .= '<br/><p>'.get_string('servers_block_select_connection', PLUGINNAME).OPPIA_HTML_BR;
        $this->content->text .= '<select name="server" class="custom-select" style="width:100%;">';
        foreach ($servers as $s){
        	$this->content->text .= '<option value="'.$s->id.'" ';
        	if ($s->defaultserver != 0){
        		$this->content->text .= 'selected="selected"';
        	}
        	$this->content->text .= '>'.$s->servername. ' ('.$s->url.')</option>';
        }
        $this->content->text .= '<option value="default"';
        if (count($servers) == 0){
            $this->content->text .= ' selected="selected">'. $CFG->block_oppia_mobile_export_default_server .'</option>';
        } else {
            $this->content->text .= '>'. $CFG->block_oppia_mobile_export_default_server .'</option>';
        }
        
        $this->content->text .= '</select></p>';
        $this->content->text .= '<p>'.get_string('servers_block_add', PLUGINNAME, $CFG->wwwroot.PLUGINPATH.'servers.php').'</p>';

       
        // Show the style options
        $this->content->text .= $this->getStylesDropDown();
        
        $this->content->text .= "<p>".get_string('course_status', PLUGINNAME).OPPIA_HTML_BR;
        $this->content->text .= "<select name=\"course_status\" class=\"custom-select\" style=\"width:100%;\">";
        $this->content->text .= "<option value='draft'>".get_string('course_status_draft', PLUGINNAME)."</option>";
        $this->content->text .= "<option value='live'>".get_string('course_status_live', PLUGINNAME)."</option>";
        $this->content->text .= "</select>";
        $this->content->text .= "</p>";
        
        $this->content->text .= "<p><input type='submit' name='submit' class=\"btn btn-primary\" value='".get_string('oppia_block_export_button', PLUGINNAME)."'>";
        $this->content->text .= "</form>";

        $this->content->text .= '</div><div class="tab-pane" id="toprint" role="tabpanel" aria-expanded="false">';

        $this->content->text .= "<form action='".$CFG->wwwroot.PLUGINPATH."export2print.php' method='post'>";
        $this->content->text .= "<input type='hidden' name='courseid' value='".$COURSE->id."'>";
        $this->content->text .= "<input type='hidden' name='sesskey' value='".sesskey()."'>";
        $this->content->text .= $this->getStylesDropDown();
        $this->content->text .= "<input type='submit' class=\"btn\" name='submit' value='".get_string('oppia_block_export2print_button', PLUGINNAME)."'>";
        $this->content->text .= "</form></p>";

        $this->content->text .= '</div></div>';
        
        $this->content->text .= "<hr />";
        $this->content->footer = '<a href="https://digital-campus.org/oppiamobile/">OppiaMobile</a> '. get_string('release', PLUGINNAME);
        if (empty($this->instance)) {
            return $this->content;
        }

        return $this->content;
    }
    
    private function getStylesDropDown(){
        $returnString = "";
        if ($handle = opendir(dirname(__FILE__).'/styles/')) {
            $returnString .= "<br/><p>".get_string('oppia_block_style', PLUGINNAME).OPPIA_HTML_BR;
            $returnString .= "<select name='stylesheet' class=\"custom-select\" style=\"width:100%;\">";
            while (false !== ($file = readdir($handle))) {
                if($file!="." && $file!=".." && !is_dir(dirname(__FILE__).'/styles/'.$file)){
                    $returnString .= "<option value='".$file."'>".$file."</option>";
                }
            }
            $returnString .= "</select>";
            $returnString .= "</p>";
        }
        
        return $returnString;
    }
    
}

?>