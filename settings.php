<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_default_server', get_string('default_server', 'block_oppia_mobile_export'),'', 'https://demo.oppia-mobile.org/', PARAM_TEXT));

	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_default_lang', get_string('default_lang', 'block_oppia_mobile_export'),get_string('default_lang_info', 'block_oppia_mobile_export'), 'en', PARAM_TEXT));
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_height', get_string('thumbheight', 'block_oppia_mobile_export'),'', 90, PARAM_INT));
	 
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_width', get_string('thumbwidth', 'block_oppia_mobile_export'),'', 135, PARAM_INT));
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_r', get_string('thumb_bg_r', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_g', get_string('thumb_bg_g', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_thumb_bg_b', get_string('thumb_bg_b', 'block_oppia_mobile_export'),'', 51, PARAM_INT));
	
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_course_icon_width', get_string('course_icon_width', 'block_oppia_mobile_export'),'', 500, PARAM_INT));
	$settings->add(new admin_setting_configtext('block_oppia_mobile_export_course_icon_height', get_string('course_icon_height', 'block_oppia_mobile_export'),'', 500, PARAM_INT));
	
	$settings->add(new admin_setting_configcheckbox('block_oppia_mobile_export_thumb_crop', get_string('thumbcrop', 'block_oppia_mobile_export'),get_string('thumbcrop_info', 'block_oppia_mobile_export'),1));
	
	$settings->add(new admin_setting_configcheckbox('block_oppia_mobile_export_debug', get_string('debug', 'block_oppia_mobile_export'),
			get_string('debug_info', 'block_oppia_mobile_export'), 1));
}
?>