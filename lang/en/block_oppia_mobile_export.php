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
$string['oppia_block_style'] = 'Course design:';
$string['oppia_block_export_button'] = 'Export to Oppia Package';

$string['oppia_block_export_servers'] = 'OppiaMobile Servers';

$string['course_status'] = 'Course Status';
$string['course_status_live'] = 'Live';
$string['course_status_draft'] = 'Draft/testing';

$string['course_icon_width'] = 'Course icon width';
$string['course_icon_height'] = 'Course icon height';

$string['default_server'] = 'Default server';

$string['default_lang'] = 'Default language';
$string['default_lang_info'] = 'Language code in ISO-639 format';

$string['thumbheight'] = 'Activity thumbnail height';
$string['thumbwidth'] = 'Activity thumbnail width';
$string['thumbcrop'] = 'Crop Icon';
$string['thumbcrop_info'] = 'Whether to crop the icon, untick to just scale the icon (which may leave borders around the image)';

$string['section_icon_width'] = 'Section icon width';
$string['section_icon_height'] = 'Section icon height';

$string['thumb_bg_r'] = 'Background colour for images (red channel)';
$string['thumb_bg_g'] = 'Background colour for images (green channel)';
$string['thumb_bg_b'] = 'Background colour for images (blue channel)';

$string['debug'] = 'Debug mode';
$string['debug_info'] = 'Show extended output info when exporting';

$string['servers_current'] = 'Your current OppiaMobile servers';
$string['servers_add'] = 'Add OppiaMobile server connection';
$string['servers_none'] = 'You don\'t current have any OppiaMobile server connections set up. Add one using the form below.';
$string['servers_block_none'] = 'You don\'t current have any OppiaMobile server connections set up. <a href="{$a}">Add one now</a>.';
$string['servers_block_add'] = 'Add/delete server connection';
$string['servers_block_select_connection'] = 'Select connection:';
$string['server_not_owner'] = 'The selected OppiaMobile server connection does not belong to your account.';
$string['server_delete'] = 'delete';
$string['server_duplicated'] = 'There is already a server with the provided URL.';

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
$string['error_file_not_found'] = 'File not found: <code>{$a}</code>';
$string['error_media_attributes'] = 'You must supply digest, download_url and filename for every media object';
$string['error_quiz_no_questions'] = 'Not exporting quiz as doesn\'t contain any supported questions.';
$string['error_section_no_activities'] = 'Not exporting section as doesn\'t contain any supported activities.';
$string['error_xml_invalid'] = 'Errors in course XML Found!';
$string['error_xml_notfound'] = 'XML module for the course not found.';
$string['error_style_copy'] = 'Failed to copy stylesheet.';
$string['error_exporting'] = 'Error exporting course';
$string['error_not_supported'] = 'Activity not supported';
$string['error_parsing_html'] = 'Error parsing HTML contents';
$string['error_exporting_no_sections'] = 'The course cannot be exported as there are no sections. This can happen if the section has the summary field empty or if none of the sections contain any supported activities, that is usually because of them not having the summary field completed too. Please check your activities\' summaries and try again.';

$string['export_step1_title'] = 'Export - step 1: Course configuration';
$string['export_step2_title'] = 'Export - step 2: Quizzes and Feedback configuration';
$string['export_step3_title'] = 'Export - step 3: Configure password protection';
$string['export_step4_title'] = 'Export - step 4: Activities export and local media management';
$string['export_step5_title'] = 'Export - step 5: Preserve activity identifiers';
$string['export_step6_title'] = 'Export - step 6: XML validation and create the course package';
$string['export_contains_quizzes'] = 'Since this course contains quizzes, please configure the quizzes';
$string['export_contains_feedback_activities'] = 'Configure the feedback activities in the following section.';
$string['export_feedback_config_instructions'] = <<<'NOTE'
<strong>NOTE:</strong></br>
- Use the + button to add new rows, and the X button to remove the row.</br>
- Only the rows containing a message will be saved.</br>
- The order is important. Grades should be set in descending order for each feedback activity.</br>
- There can not be two or more rows with the same grade value for each feedback activity.</br>
- You can use the following placeholders that will be replaced with the real value in the app:</br>
&emsp;·  <strong>{{user_score}}</strong> - Final score the learner obtained in the feedback activity.</br>
&emsp;·  <strong>{{max_score}}</strong> - Maximum score possible for the feedback activity.</br>
&emsp;·  <strong>{{score_percentage}}</strong> - Percentage value of the score the learner obtained.</br>
- For multilingual messages use one line for each language and use the following format:</br>
&emsp;en=Thank you for your feedback!
NOTE;

$string['export_quiz_sectionname'] = 'Section Name';
$string['export_quiz_title'] = 'Quiz Title';
$string['export_quiz_norandom'] = 'No random questions';
$string['export_quiz_feedback'] = 'Show feedback';
$string['export_quiz_passthreshold'] = 'Pass Threshold (%)';
$string['export_quiz_max_attempts'] = 'Max number of attempts';
$string['export_quiz_password_protected'] = 'Password protected';

$string['export_quiz_norandom_all'] = 'Use all questions (don\'t randomise)';
$string['export_quiz_norandom_selectx'] = 'Select {$a} random questions';
$string['export_quiz_maxattempts_unlimited'] = 'Unlimited';

$string['export_priority_title'] = 'Course Priority';
$string['export_priority_label'] = 'Priority';
$string['export_priority_desc'] = 'This is the relative weight given to a course to help determine the ordering in which it will appear on the mobile (10 = highest priority)';

$string['export_keep_tags_title'] = 'Quiz Question Formatting';
$string['export_keep_tags_desc'] = 'support use of HTML tags (&lt;em&gt;, &lt;b&gt;, &lt;strong&gt;, etc) in quiz question/response options. <br/><strong>Important</strong>:This is only supported for users with v7.3.2 or higher of the Oppia app';

$string['export_server_error'] = 'Unable to get server info (is it correctly configured and running?)';

$string['server_info_name'] = 'Server name';
$string['server_info_version'] = 'Current server version';
$string['server_info_max_upload'] = 'Max. upload size';

$string['export_videooverlay_title'] = 'Video overlay';
$string['export_videooverlay_desc'] = 'Add a "play" button overlay on top of the poster image for all the embedded videos in the course';

$string['export_course_tags_title'] = 'Course Categories';
$string['export_course_tags_desc'] = 'Categories that will be used to classify the course on the OppiaMobile server, separate each category by a comma';

$string['export_lang_title'] = 'Course default language';
$string['export_lang_desc'] = 'The default language for this course, using the ISO 639 code';

$string['export_sequencing_title'] = 'Course Sequencing';
$string['export_sequencing_desc'] = 'Set the sequencing mode of the course. Here you can specify that the course activities must be attempted sequentially, section or course-wide.';
$string['export_sequencing_none'] = 'None';
$string['export_sequencing_course'] = 'Sequencing through whole course';
$string['export_sequencing_section'] = 'Sequencing within a section';
$string['export_sequencing_label'] = 'Sequencing mode';

$string['export_thumbnail_sizes_title'] = 'Thumbnail icon sizes';
$string['export_thumbnail_sizes_desc'] = 'Specify the dimensions for the thumbnail images for activities and sections';
$string['export_thumbnail_sizes_section'] = 'Section';
$string['export_thumbnail_sizes_activity'] = 'Activity';
$string['export_thumbnail_sizes_width'] = 'Width';
$string['export_thumbnail_sizes_height'] = 'Height';

$string['export_sections_start'] = 'Exporting course Sections...';
$string['export_section_title'] = 'Exporting Section: {$a}';
$string['export_sections_finish'] = 'Finished exporting activities and sections';
$string['export_xml_valid_start'] = 'Validating course XML file...';
$string['export_xml_validated'] = 'validated';
$string['export_course_xml_created'] = 'Exported course XML file';
$string['export_style_start'] = 'Adding style sheet';
$string['export_style_resources'] = 'Copying style resources';
$string['export_export_complete'] = 'Course export complete';
$string['export_export_compressed'] = 'Compressed file';
$string['export_download_intro'] = 'You can also download the course zip file here, but this should only be used for testing/development purposes. For live deployment, publish the file to the Oppia server first and download the course zip from there.';
$string['export_download'] = 'Download exported course';

$string['export_cleanup'] = 'Cleanup files';

$string['export_advice_desc'] = 'Although your course has been exported you may want to address the following issues to make sure your course is easy to use on mobile devices:';

$string['export_preview_download'] = 'Download exported course preview at <a href="{$a->zip}">{$a->coursename}</a>';
$string['export_preview_quiz'] = '<a href="{$a->link}" target="_blank">View all quiz questions</a>';

$string['export_file_trying'] = 'Trying file: <code>{$a}</code>';
$string['export_file_success'] = 'File: <code>{$a}</code> successfully exported';
$string['export_image_success'] = 'Image: <code>{$a}</code> successfully exported';

$string['export_media_missing'] = 'Some media files included in your course have not been uploaded to the OppiaMobile server yet. To be able to upload the contents on OppiaMobile ({$a}), please complete your OppiaMobile server login details below:';

$string['export_quiz_skip'] = 'Skipping quiz since contains no questions';
$string['export_quiz_skip_essay'] = 'Skipping essay question';
$string['export_quiz_skip_random'] = 'Skipping random question';

$string['export_preserve_activity_id_title'] = 'Preserve activity IDs';
$string['export_preserve_activity_id_desc'] = 'By selecting the \'Preserve ID\' option for an activity, the OppiaMobile server will not create a new version of that activity.';
$string['export_preserve_activity_id_header'] = 'Preserve ID';
$string['export_no_content_changes_message'] = 'No content changes detected in previously published activities. Please, click the continue button to move to the next step.';
$string['export_quizzes_nor_feedback_message'] = 'This course does not have any quiz or feedback activities containing any question of type "Multiple choice (Rated)". Please, click the continue button to move to the next step.';

$string['export_renewing_digests_in_section'] = 'Renewing digests in section: {$a}';

$string['section_password_title'] = 'Password protection';
$string['section_password_added'] = '🔒 Section protected by password';
$string['section_password_desc'] = 'If you want to lock a topic or activity with password, please enter it in the table below.';
$string['section_password_label'] = 'Password';
$string['section_password_invalid'] = '<strong>{$a}</strong>: Section doesn\'t contain any supported activities.';

$string['activity_password_added'] = '🔒 Activity protected by password';

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
$string['publish_message_400'] = 'Your course has not been published. Please see below for more detail from the Oppia server';
$string['publish_message_401'] = 'Unauthorized. Either you entered an incorrect username/password, or you do not have permissions to publish on the specified OppiaMobile server. Your course has not been published.';
$string['publish_message_405'] = 'Invalid HTTP request type - this probably needs a programmer to look into. Your course has not been published.';
$string['publish_message_500'] = 'A server error occured during publishing. This could be a permissions issue, please refer to your OppiaMobile server administrator. Your course has not been published.';
$string['publish_heading'] = 'Publish to OppiaMobile server';
$string['publish_text'] = 'To publish your course directly on OppiaMobile ({$a}), please complete your OppiaMobile server login details below.<br/>Check with your OppiaMobile administrator if you are unsure whether you have permissions to publish on the server';
$string['publish_heading_draft'] = 'Push draft course to OppiaMobile server';
$string['publish_text_draft'] = 'To push your draft course directly on OppiaMobile ({$a}), please complete your OppiaMobile server login details below.<br/>Check with your OppiaMobile administrator if you are unsure whether you have permissions to publish on the server';

$string['publish_btn'] = "Publish";
$string['publishing_header_live'] = "Publishing Course";
$string['publishing_header_draft'] = "Pushing Draft Course";

$string['settings_avoid_push_quizzes'] = 'Don\'t push quizzes info to the Oppia server in the export process';
$string['settings_avoid_push_quizzes_info'] = 'Avoid pushing quizzes info to the Oppia server in the export process. If enabled, be sure that the Oppia server is capable of processing the full course including quizzes.';

$string['missing_video_poster'] = 'Warning: Missing "poster" image for media element.';
$string['video_included'] = 'Video included:';

$string['media_files_size'] = 'Size';
$string['media_files_digest'] = 'Digest';
$string['media_files_length'] = 'Length';
$string['media_files_title'] = 'Pushing local media files';
$string['media_files_not_uploaded'] = 'Not uploaded to the server yet.';
$string['media_files_request_error'] = 'Error processing request.';

$string['duplicated_digest_title'] = 'Error: Duplicated digests';
$string['duplicated_digest_description'] = 'There are identical activities in your course that have the same digest:';
$string['duplicated_digest_footer'] = 'Please, correct these issues and export your course again, see <a href="https://oppiamobile.readthedocs.io/en/latest/support/troubleshooting/block.html">Troubleshooting Moodle Oppia Export Block</a> for more help.';
