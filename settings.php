<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_export_mobile_package_mquiz_url', get_string('mquizurl', 'block_export_mobile_package'),
                   get_string('mquizurlfull', 'block_export_mobile_package'), 'http://mquiz.org/api/', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_export_mobile_package_mquiz_username', get_string('mquizusername', 'block_export_mobile_package'),
    		get_string('mquizusernamefull', 'block_export_mobile_package'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_export_mobile_package_mquiz_api_key', get_string('mquizapikey', 'block_export_mobile_package'),
    		get_string('mquizapikeyfull', 'block_export_mobile_package'), '', PARAM_TEXT));
}
?>