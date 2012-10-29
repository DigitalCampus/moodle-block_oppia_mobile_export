<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_export_mobile_package_mquiz_url', get_string('mquizurl', 'block_export_mobile_package'),
                   get_string('mquizurlfull', 'block_export_mobile_package'), 'http://mquiz.org/api/', PARAM_TEXT));
    
}
?>