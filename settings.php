<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_mquiz_url', get_string('mquizurl', 'block_oppia_mobile_export'),
                   get_string('mquizurlfull', 'block_oppia_mobile_export'), 'http://mquiz.org/api/v1/', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_mquiz_username', get_string('mquizusername', 'block_oppia_mobile_export'),
    		get_string('mquizusernamefull', 'block_oppia_mobile_export'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_mquiz_api_key', get_string('mquizapikey', 'block_oppia_mobile_export'),
    		get_string('mquizapikeyfull', 'block_oppia_mobile_export'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_height', get_string('thumbheight', 'block_oppia_mobile_export'),'', 80, PARAM_INT));
   
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_width', get_string('thumbwidth', 'block_oppia_mobile_export'),'', 140, PARAM_INT));
}
?>