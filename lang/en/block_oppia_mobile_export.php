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

/**
 * Strings for component 'block_oppia_mobile_export'
 *
 * @package   block_oppia_mobile_export
 * @copyright 2012 Alex Little {@link http://alexlittle.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['pluginname'] = 'Oppia Mobile Export';
$string['oppia_mobile_export:addinstance'] = 'Add a new Oppia export block';

$string['oppia_block_api'] = 'Current OppiaMobile API:';
$string['oppia_block_style'] = 'Stylesheet to use:';
$string['oppia_block_export_button'] = 'Export to Oppia Package';
$string['oppia_block_export2print_button'] = 'Export to print';

$string['oppia_block_export_servers'] = 'OppiaMobile Servers';

$string['course_icon_width'] = 'Course icon width';
$string['course_icon_height'] = 'Course icon height';

$string['thumbheight'] = 'Icon height';
$string['thumbwidth'] = 'Icon width';
$string['thumbcrop'] = 'Crop Icon';
$string['thumbcrop_info'] = 'Whether to crop the icon, untick to just scale the icon (which may leave borders around the image)';

$string['thumb_bg_r'] = 'Background colour for images (red channel)';
$string['thumb_bg_g'] = 'Background colour for images (green channel)';
$string['thumb_bg_b'] = 'Background colour for images (blue channel)';

$string['debug'] = 'Debug mode';
$string['debug_info'] = 'Show extended output info when exporting';

$string['servers_current'] = 'Your current OppiaMobile servers';
$string['servers_add'] = 'Add new OppiaMobile server connection';
$string['servers_none'] = 'You don\'t current have any OppiaMobile server connections set up. Add one using the form below.';
$string['servers_block_none'] = 'You don\'t current have any OppiaMobile server connections set up. <a href="{$a}">Add one now</a>.';
$string['servers_block_add'] = '<a href="{$a}">Add new server connection</a>.';
$string['servers_block_select_connection'] = 'Select connection:';
$string['server_not_owner'] = 'The selected OppiaMobile server connection does not belong to your account.';

$string['server_form_name'] = 'Server Name (your reference)';
$string['server_form_name_error_none'] = 'Please enter a reference name for your server.';
$string['server_form_url'] = 'URL';
$string['server_form_url_error_none'] = 'Please enter the url to the server.';
$string['server_form_username'] = 'Username';
$string['server_form_username_error_none'] = 'Please enter your OppiaMobile username for the server';
$string['server_form_apikey'] = 'API Key';
$string['server_form_apikey_error_none'] = 'Please enter your OppiaMobile API key for the server';

$string['cleanup_start'] = 'Starting cleanup now...';
$string['cleanup_end'] = 'Cleanup completed';

$string['error_feedback_no_questions'] = 'Not exporting feedback as doesn\'t contain any supported questions.';
$string['error_quiz_no_questions'] = 'Not exporting quiz as doesn\'t contain any supported questions.';
$string['error_section_no_activities'] = 'Not exporting section as doesn\'t contain any activities.';
$string['error_xml_invalid'] = 'Errors in course XML Found!';
$string['error_style_copy'] = 'Failed to copy stylesheet.';

$string['export_title'] = 'Export - step {$a->stepno}: {$a->coursename}';

$string['export_contains_quizzes'] = 'Since this course contains quizzes, please select which quizzes (if any) should be a random selection of the questions available';

$string['export_quiz_sectionname'] = 'Section Name';
$string['export_quiz_title'] = 'Quiz Title';
$string['export_quiz_norandom'] = 'No random questions';
$string['export_quiz_feedback'] = 'Show feedback';
$string['export_quiz_tryagain'] = 'Allow try-again?';

$string['export_quiz_norandom_all'] = 'Use all questions (don\'t randomise)';
$string['export_quiz_norandom_selectx'] = 'Select {$a} random questions';

$string['export_priority_title'] = 'Course Priority';
$string['export_priority_desc'] = 'This is the relative weight given to a course to help determine the ordering in which it will appear on the mobile (10 = highest priority)';


$string['export_section_title'] = 'Exporting Section: {$a}';
$string['export_xml_valid_start'] = 'Validating course XML file...';
$string['export_xml_validated'] = 'validated';
$string['export_course_xml_created'] = 'Exported coursee XML file';
$string['export_style_start'] = 'Adding style sheet';
$string['export_style_resources'] = 'Copying style resources';
$string['export_export_complete'] = 'Course export complete';
$string['export_export_compressed'] = 'Compressed file';
$string['export_download'] = 'Download exported course at <a href="{$a->zip}">{$a->coursename}</a>';

$string['export_cleanup'] = 'Cleanup files';

$string['export_advice_desc'] = 'Although your course has been exported you may want to address the following issues to make sure your course is easy to use on mobile devices:';

$string['true'] = 'True';
$string['false'] = 'False';
$string['continue'] = 'Continue';

