<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_url', get_string('oppiaurl', 'block_oppia_mobile_export'),
			get_string('oppiaurlfull', 'block_oppia_mobile_export'), 'http://demo.oppia-mobile.org/', PARAM_TEXT));

	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_username', get_string('oppiausername', 'block_oppia_mobile_export'),
			get_string('oppiausernamefull', 'block_oppia_mobile_export'), '', PARAM_TEXT));

	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_api_key', get_string('oppiaapikey', 'block_oppia_mobile_export'),
			get_string('oppiaapikeyfull', 'block_oppia_mobile_export'), '', PARAM_TEXT));

	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_height', get_string('thumbheight', 'block_oppia_mobile_export'),'', 90, PARAM_INT));
	 
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_width', get_string('thumbwidth', 'block_oppia_mobile_export'),'', 135, PARAM_INT));
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_r', get_string('thumb_bg_r', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_g', get_string('thumb_bg_g', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_b', get_string('thumb_bg_b', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_course_icon_width', get_string('course_icon_width', 'block_oppia_mobile_export'),'', 60, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_course_icon_height', get_string('course_icon_height', 'block_oppia_mobile_export'),'', 60, PARAM_INT));
	
	$settings->add(new admin_setting_configcheckbox('block_oppia_mobile_export_thumb_crop', get_string('thumbcrop', 'block_oppia_mobile_export'),get_string('thumbcrop_info', 'block_oppia_mobile_export'),1));
}
?>