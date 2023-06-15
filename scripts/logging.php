<?php

function init_logging($logFile) {
    $directory = dirname($logFile);
    if (!file_exists($directory)) {
        // Create an empty log file
        mkdir($directory, 0777, true);
    }

    $logger = new Logger();
    $logger->logFile = $logFile;
    return $logger;
}

class Logger {
    public $logFile;

    public function log_message($message) {
        global $logFile;
        $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}