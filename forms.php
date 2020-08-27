<?php 
require_once("$CFG->libdir/formslib.php");
 
class oppiaserver_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('text', 'server_ref', get_string('server_form_name','block_oppia_mobile_export')); 
        $mform->setType('server_ref', PARAM_NOTAGS);                  

        $mform->addElement('text', 'server_url', get_string('server_form_url','block_oppia_mobile_export'));
        $mform->setType('server_url', PARAM_NOTAGS);
        $mform->setDefault('server_url', 'https://demo.oppia-mobile.org/');
        
        $this->add_action_buttons($cancel=false);

    }
    //Custom validation should be added here
    function validation($data, $files) {
    	$errors= array();
    	
    	if(trim($data['server_ref']) == ""){
    		$errors['server_ref'] = get_string('server_form_name_error_none','block_oppia_mobile_export');
    	}
    	if(trim($data['server_url']) == ""){
    		$errors['server_url'] = get_string('server_form_url_error_none','block_oppia_mobile_export');
    	}    	
        return $errors;
    }
}