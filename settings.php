<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_url', get_string('oppiaurl', 'block_oppia_mobile_export'),
                   get_string('oppiaurlfull', 'block_oppia_mobile_export'), 'http://demo.oppia-mobile.org/', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_username', get_string('oppiausername', 'block_oppia_mobile_export'),
    		get_string('oppiausernamefull', 'block_oppia_mobile_export'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_api_key', get_string('oppiaapikey', 'block_oppia_mobile_export'),
    		get_string('oppiaapikeyfull', 'block_oppia_mobile_export'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_height', get_string('thumbheight', 'block_oppia_mobile_export'),'', 80, PARAM_INT));
   
    $settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_width', get_string('thumbwidth', 'block_oppia_mobile_export'),'', 140, PARAM_INT));
}
?>