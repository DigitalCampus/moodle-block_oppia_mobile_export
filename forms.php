<?php 
require_once("$CFG->libdir/formslib.php");
 
class OppiaServerForm extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('text', 'server_ref', get_string('server_form_name', PLUGINNAME)); 
        $mform->setType('server_ref', PARAM_NOTAGS);                  

        $mform->addElement('text', 'server_url', get_string('server_form_url', PLUGINNAME));
        $mform->setType('server_url', PARAM_NOTAGS);
        $mform->setDefault('server_url', 'https://demo.oppia-mobile.org/');
        
        $this->add_action_buttons(false);

    }
    //Custom validation should be added here
    function validation($data, $files) {
    	$errors= array();
    	
    	if(trim($data['server_ref']) == ""){
    		$errors['server_ref'] = get_string('server_form_name_error_none', PLUGINNAME);
    	}
    	if(trim($data['server_url']) == ""){
    		$errors['server_url'] = get_string('server_form_url_error_none', PLUGINNAME);
    	}    	
        return $errors;
    }
}