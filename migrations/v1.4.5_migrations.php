<?php

define('CLI_SCRIPT', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require(__DIR__ . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');
require_once(dirname(__FILE__) . '/update_servers.php');
require_once(dirname(__FILE__) . '/../scripts/logging.php');
global $CFG;

$CFG->block_oppia_mobile_export_debug = false;

$logFile = "./logs/" . date("YmdHis") . "_v.1.4.5_migrations.log";
$logger = init_logging($logFile);

$starttime = microtime(true);

echo "Starting migrations for version 1.4.5\n";
$logger->log_message("Starting migrations for version 1.4.5");
remove_duplicated_servers();

$timediff = microtime(true) - $starttime;
echo "Completed in " . $timediff . " seconds.\n";
$logger->log_message("Completed in " . $timediff . " seconds.");
