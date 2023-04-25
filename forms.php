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

require_once($CFG->dirroot.'/config.php');
require_once("$CFG->libdir/formslib.php");

class OppiaServerForm extends moodleform {

    // Add elements to form.
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('text', 'server_ref', get_string('server_form_name', PLUGINNAME));
        $mform->setType('server_ref', PARAM_NOTAGS);

        $mform->addElement('text', 'server_url', get_string('server_form_url', PLUGINNAME));
        $mform->setType('server_url', PARAM_NOTAGS);
        $mform->setDefault('server_url', 'https://demo.oppia-mobile.org/');

        $this->add_action_buttons(false);
    }

    // Custom validation should be added here.
    public function validation($data, $files) {
        $errors = array();
        if (trim($data['server_ref']) == "") {
            $errors['server_ref'] = get_string('server_form_name_error_none', PLUGINNAME);
        }
        if (trim($data['server_url']) == "") {
            $errors['server_url'] = get_string('server_form_url_error_none', PLUGINNAME);
        }
        return $errors;
    }
}
