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

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/constants.php');

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_default_server', get_string('default_server', PLUGINNAME),
            '', 'https://demo.oppia-mobile.org/', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_default_lang', get_string('default_lang', PLUGINNAME),
            get_string('default_lang_info', PLUGINNAME), 'en', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_height', get_string('thumbheight', PLUGINNAME), '', 90, PARAM_INT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_width', get_string('thumbwidth', PLUGINNAME), '', 135, PARAM_INT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_r', get_string('thumb_bg_r', PLUGINNAME), '', 51, PARAM_INT));
    $settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_g', get_string('thumb_bg_g', PLUGINNAME), '', 51, PARAM_INT));
    $settings->add(new admin_setting_configtext(PLUGINNAME.'_thumb_bg_b', get_string('thumb_bg_b', PLUGINNAME), '', 51, PARAM_INT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_course_icon_width', get_string('course_icon_width', PLUGINNAME), '', 500, PARAM_INT));
    $settings->add(new admin_setting_configtext(PLUGINNAME.'_course_icon_height', get_string('course_icon_height', PLUGINNAME), '', 500, PARAM_INT));

    $settings->add(new admin_setting_configtext(PLUGINNAME.'_section_icon_width', get_string('section_icon_width', PLUGINNAME), '', 256, PARAM_INT));
    $settings->add(new admin_setting_configtext(PLUGINNAME.'_section_icon_height', get_string('section_icon_height', PLUGINNAME), '', 256, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox(PLUGINNAME.'_thumb_crop', get_string('thumbcrop', PLUGINNAME),
            get_string('thumbcrop_info', PLUGINNAME), 1));

    $settings->add(new admin_setting_configcheckbox(PLUGINNAME.'_debug', get_string('debug', PLUGINNAME),
            get_string('debug_info', PLUGINNAME), 1));
}
