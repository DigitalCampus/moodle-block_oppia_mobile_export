<?php 

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../constants.php');
require_once(dirname(__FILE__) . '/../migrations/populate_digests.php');

global $CFG;
$CFG->block_oppia_mobile_export_debug = false;

$starttime = microtime(true);

echo 'Starting populate digest function';
populate_digests_published_courses(null, false);

$timediff = microtime(true) - $starttime;
echo 'Completed in ' . $timediff . ' seconds.';

?>