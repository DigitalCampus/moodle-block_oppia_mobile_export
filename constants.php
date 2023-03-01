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

define('PLUGINPATH', '/blocks/oppia_mobile_export/');
define('PLUGINNAME', 'block_oppia_mobile_export'); 
define('OPPIA_SERVER_TABLE', 'block_oppia_mobile_server');
define('OPPIA_CONFIG_TABLE', 'block_oppia_mobile_config');
define('OPPIA_PUBLISH_LOG_TABLE', 'block_oppia_publish_log');
define('OPPIA_DIGEST_TABLE', 'block_oppia_activity_digest');
define('OPPIA_GRADE_BOUNDARY_TABLE', 'block_oppia_grade_boundary');

define('OPPIA_OUTPUT_DIR', 'output/');
define('OPPIA_MODULE_XML', '/module.xml');

// Constants for style compiling
define('STYLES_DIR', 'styles/');
define('STYLES_THEMES_DIR', 'themes/');
define('STYLES_BASE_SCSS', 'base.scss');
define('STYLES_EXTRA_SUFFIX', '_extra');
define('COMMON_STYLES_RESOURCES_DIR', 'common-resources/');
define('COURSE_STYLES_RESOURCES_DIR', '/style_resources/');
define('STYLESHEET_DEFAULT', 'default');

// constants for html output
define('OPPIA_HTML_SPAN_ERROR_START', '<span class="export-error">');
define('OPPIA_HTML_SPAN_END', '</span>');
define('OPPIA_HTML_BR', '<br/>');
define('OPPIA_HTML_STRONG_END', '</strong>');
define('OPPIA_HTML_LI_END', '</li>');
define('OPPIA_HTML_H2_START', '<h2>');
define('OPPIA_HTML_H2_END', '</h2>');
