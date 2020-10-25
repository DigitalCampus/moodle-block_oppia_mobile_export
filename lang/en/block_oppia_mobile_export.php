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
$string['release'] = 'v1.1.3';

$string['oppia_mobile_export:addinstance'] = 'Add a new Oppia export block';

$string['oppia_block_api'] = 'Current OppiaMobile API:';
$string['oppia_block_style'] = 'Course design:';
$string['oppia_block_export_button'] = 'Export to Oppia Package';
$string['oppia_block_export2print_button'] = 'Export to print';

$string['oppia_block_export_servers'] = 'OppiaMobile Servers';

$string['course_status'] = 'Course Status';
$string['course_status_live'] = 'Live';
$string['course_status_draft'] = 'Draft/testing';

$string['course_icon_width'] = 'Course icon width';
$string['course_icon_height'] = 'Course icon height';

$string['default_server'] = 'Default server';

$string['default_lang'] = 'Default language';
$string['default_lang_info'] = 'Language code in ISO-639 format';

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

$string['cleanup_start'] = 'Starting cleanup now...';
$string['cleanup_end'] = 'Cleanup completed';

$string['error_connection'] = 'Error connecting to OppiaMobile server, please check the your API url, username and API key.';
$string['error_creating_quiz'] = 'There was an error creating the quiz, please try exporting this course again (refresh this page)';
$string['error_edit_page'] = 'Image not found, <a target="_blank" href="{$a}">please edit the activity</a>';
$string['error_feedback_no_questions'] = 'Not exporting feedback as doesn\'t contain any supported questions.';
$string['error_file_delete'] = 'Unable to delete the file';
$string['error_file_not_found'] = 'File not found: {$a}';
$string['error_media_attributes'] = 'You must supply digest, download_url and filename for every media object';
$string['error_quiz_no_questions'] = 'Not exporting quiz as doesn\'t contain any supported questions.';
$string['error_section_no_activities'] = 'Not exporting section as doesn\'t contain any supported activities.';
$string['error_xml_invalid'] = 'Errors in course XML Found!';
$string['error_style_copy'] = 'Failed to copy stylesheet.';
$string['error_exporting'] = 'Error exporting course';
$string['error_exporting_no_sections'] = 'The course cannot be exported as there are no sections. This can happen if the section has the summary field empty or if none of the sections contain any supported activities, that is usually because of them not having the summary field completed too. Please check your activities\' summaries and try again.';

$string['export_title'] = 'Export - step {$a->stepno}: {$a->coursename}';
$string['export2print_title'] = 'Export to Print - step {$a->stepno}: {$a->coursename}';
$string['export_contains_quizzes'] = 'Since this course contains quizzes, please select which quizzes (if any) should be a random selection of the questions available';

$string['export_quiz_sectionname'] = 'Section Name';
$string['export_quiz_title'] = 'Quiz Title';
$string['export_quiz_norandom'] = 'No random questions';
$string['export_quiz_feedback'] = 'Show feedback';
$string['export_quiz_passthreshold'] = 'Pass Threshold (%)';
$string['export_quiz_max_attempts'] = 'Max number of attempts';

$string['export_quiz_norandom_all'] = 'Use all questions (don\'t randomise)';
$string['export_quiz_norandom_selectx'] = 'Select {$a} random questions';
$string['export_quiz_maxattempts_unlimited'] = 'Unlimited';

$string['export_priority_title'] = 'Course Priority';
$string['export_priority_label'] = 'Priority';
$string['export_priority_desc'] = 'This is the relative weight given to a course to help determine the ordering in which it will appear on the mobile (10 = highest priority)';

$string['export_course_tags_title'] = 'Course Categories';
$string['export_course_tags_desc'] = 'Categories that will be used to classify the course on the OppiaMobile server, separate each category by a comma';

$string['export_lang_title'] = 'Course default language';
$string['export_lang_desc'] = 'The default language for this course, using the ISO 639 code';

$string['export_sequencing_title'] = 'Course Sequencing';
$string['export_sequencing_desc'] = 'Set the sequencing mode of the course. Here you can specify that the course activities must be attempted sequentially, section or course-wise.';
$string['export_sequencing_none'] = 'None';
$string['export_sequencing_course'] = 'Sequencing through whole course';
$string['export_sequencing_section'] = 'Sequencing within a section';
$string['export_sequencing_label'] = 'Sequencing mode';

$string['export_section_title'] = 'Exporting Section: {$a}';
$string['export_xml_valid_start'] = 'Validating course XML file...';
$string['export_xml_validated'] = 'validated';
$string['export_course_xml_created'] = 'Exported course XML file';
$string['export_style_start'] = 'Adding style sheet';
$string['export_style_resources'] = 'Copying style resources';
$string['export_export_complete'] = 'Course export complete';
$string['export_export_compressed'] = 'Compressed file';
$string['export_download_intro'] = 'You can also download the course zip file here, but this should only be used for testing/development purposes. For live deployment, publish the file to the Oppia server first and download the course zip from there.';
$string['export_download'] = 'Download exported course: <a href="{$a->zip}">{$a->coursename}</a>';

$string['export_cleanup'] = 'Cleanup files';

$string['export_advice_desc'] = 'Although your course has been exported you may want to address the following issues to make sure your course is easy to use on mobile devices:';

$string['export_preview_download'] = 'Download exported course preview at <a href="{$a->zip}">{$a->coursename}</a>';
$string['export_preview_quiz'] = '<a href="{$a->link}" target="_blank">View all quiz questions</a>';

$string['export_file_trying'] = 'Trying file: {$a}';
$string['export_file_success'] = 'File: {$a} successfully exported';
$string['export_image_success'] = 'Image: {$a} successfully exported';

$string['export_quiz_skip'] = 'Skipping quiz since contains no questions';
$string['export_quiz_skip_essay'] = 'Skipping essay question';
$string['export_quiz_skip_random'] = 'Skipping random question';

$string['true'] = 'True';
$string['false'] = 'False';
$string['continue'] = 'Continue';

$string['feedback_always'] = 'After question and end of quiz';
$string['feedback_never'] = 'Never show feedback';
$string['feedback_endonly'] = 'At end of quiz only';

$string['publish_error_password'] = 'No password entered, please go back and enter your password';
$string['publish_error_tags'] = 'No categories have been entered, please go back and enter at least one category';
$string['publish_error_username'] = 'No username entered, please go back and enter your username';
$string['publish_field_draft'] = 'Is draft';
$string['publish_field_draft_info'] = 'Tick the box if the course is to be a \'draft\' (only availble to selected groups), or untick the box if it should be published publically';
$string['publish_field_password'] = 'Password';
$string['publish_field_tags'] = 'Categories (for classifying course)';
$string['publish_field_username'] = 'Username';
$string['publish_message_201'] = 'Your course has now been published on the OppiaMobile server';
$string['publish_message_400'] = 'Bad request. This is likely due to missing fields/data. Your course has not been published.';
$string['publish_message_401'] = 'Unauthorized. Either you entered an incorrect username/password, or you do not have permissions to publish on the specified OppiaMobile server. Your course has not been published.';
$string['publish_message_405'] = 'Invalid HTTP request type - this probably needs a programmer to look into. Your course has not been published.';
$string['publish_message_500'] = 'A server error occured during publishing. This could be a permissions issue, please refer to your OppiaMobile server administrator. Your course has not been published.';
$string['publish_heading'] = 'Publish to OppiaMobile server';
$string['publish_text'] = 'To publish your course directly on OppiaMobile ({$a}), please complete your OppiaMobile server login details below.<br/>Check with your OppiaMobile administrator if you are unsure whether you have permissions to publish on the server';
$string['publish_heading_draft'] = 'Push draft course to OppiaMobile server';
$string['publish_text_draft'] = 'To push your draft course directly on OppiaMobile ({$a}), please complete your OppiaMobile server login details below.<br/>Check with your OppiaMobile administrator if you are unsure whether you have permissions to publish on the server';

$string['publishing_header_live'] = "Publishing Course";
$string['publishing_header_draft'] = "Pushing Draft Course";

$string['settings_avoid_push_quizzes'] = 'Don\'t push quizzes info to the Oppia server in the export process';
$string['settings_avoid_push_quizzes_info'] = 'Avoid pushing quizzes info to the Oppia server in the export process. If enabled, be sure that the Oppia server is capable of processing the full course including quizzes.';

