<?php 

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/constants.php');

if ($ADMIN->fulltree) {
	
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_default_server', get_string('default_server', PLUGINNAME), 
			'', 'https://demo.oppia-mobile.org/', PARAM_TEXT));

	$settings->add(new admin_setting_configtext(PLUGINNAME.'_default_lang', get_string('default_lang', PLUGINNAME),
			get_string('default_lang_info', PLUGINNAME), 'en', PARAM_TEXT));
	
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_height', get_string('thumbheight', PLUGINNAME),'', 90, PARAM_INT));
	 
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_width', get_string('thumbwidth', PLUGINNAME),'', 135, PARAM_INT));
	
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_r', get_string('thumb_bg_r', PLUGINNAME),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_g', get_string('thumb_bg_g', PLUGINNAME),'', 51, PARAM_INT));
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_b', get_string('thumb_bg_b', PLUGINNAME),'', 51, PARAM_INT));
	
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_course_icon_width', get_string('course_icon_width', PLUGINNAME),'', 500, PARAM_INT));
	$settings->add(new admin_setting_configtext(PLUGINNAME.'_course_icon_height', get_string('course_icon_height', PLUGINNAME),'', 500, PARAM_INT));
	
	$settings->add(new admin_setting_configcheckbox(PLUGINNAME.'_thumb_crop', get_string('thumbcrop', PLUGINNAME),
			get_string('thumbcrop_info', PLUGINNAME), 1));
	
	$settings->add(new admin_setting_configcheckbox(PLUGINNAME.'_debug', get_string('debug', PLUGINNAME),
			get_string('debug_info', PLUGINNAME), 1));
}
?>