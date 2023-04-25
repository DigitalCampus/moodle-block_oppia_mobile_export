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

define('CLI_SCRIPT', true);

global $CFG;
require_once($CFG->dirroot.'/config.php');
require_once(dirname(__FILE__) . '/../constants.php');
require_once(dirname(__FILE__) . '/../migrations/populate_digests.php');


$CFG->block_oppia_mobile_export_debug = false;

$starttime = microtime(true);

echo 'Starting populate digest function';
populate_digests_published_courses();

$timediff = microtime(true) - $starttime;
echo 'Completed in ' . $timediff . ' seconds.';
