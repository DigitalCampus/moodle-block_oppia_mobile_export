<?php

require(__DIR__ . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

function remove_duplicated_servers() {
    global $DB, $logger;

    // Get servers list
    $serversInfo = $DB->get_records(OPPIA_SERVER_TABLE);

    $updatedServers = array();
    foreach($serversInfo as $serverA){
        if (!array_key_exists($serverA->id, $updatedServers)) {
            foreach ($serversInfo as $serverB) {
                $trimmedUrlA = rtrim($serverA->url, '/');
                $trimmedUrlB = rtrim($serverB->url, '/');
                if (($serverA->id != $serverB->id) && (strcmp($trimmedUrlA, $trimmedUrlB) === 0)) {
                    # If two different servers have the same URL, include them in the updatedServers array
                    $updatedServers[$serverB->id] = $serverA->id;
                }
            }
        }
    }

    foreach ($updatedServers as $key => $value) {
        $logger->log_message("Replace server ID=$key with ID=$value");
    }

    update_records_for_table(OPPIA_DIGEST_TABLE, $updatedServers);
    update_records_for_table(OPPIA_GRADE_BOUNDARY_TABLE, $updatedServers);
    update_records_for_table(OPPIA_CONFIG_TABLE, $updatedServers);

    $logger->log_message("Deleting duplicated server definitions...");
    $DB->delete_records_list(OPPIA_SERVER_TABLE, 'id', array_keys($updatedServers));
    $logger->log_message("Duplicated server definitions deleted.");
}

function update_records_for_table($tableName, $updatedServers) {
    // Update records based on the new server ID
    global $DB, $logger;
    $logger->log_message("Updating server IDs for mdl_$tableName table");
    $tableRecords = $DB->get_records($tableName);
    foreach($tableRecords as $entry){
        if (array_key_exists($entry->serverid, $updatedServers)) {
            $newId = $updatedServers[$entry->serverid];
            $records = list_duplicates($DB, $entry, $tableName, $newId);

            for ($i = 0; $i < count($records); $i++){
                if ($i == 0) {
                    // The first value is the one that week keep, and update its server ID
                    $logger->log_message("Update entry ID=".$records[$i]->id . " from ServerID: $entry->serverid to ServerId:$newId");
                    $params = new stdclass;
                    $params->id = $records[$i]->id;
                    $params->serverid = $newId;
                    $DB->update_record($tableName, $params);
                } else {
                    // The rest of the values (if any) are deleted, as they are considered duplicated and older
                    $logger->log_message("Delete older duplicate serverid: " . $records[$i]->id);
                    $DB->delete_records_list($tableName, 'id', array($records[$i]->id));
                }
            }
        }
    }
}

function list_duplicates($DB, $item, $tableName, $newId) {
    // List all the entries that will be duplicated when updating the server id and sort them from the most recent to the oldest
    $sql = "SELECT * FROM mdl_$tableName";

    if ($tableName == OPPIA_DIGEST_TABLE) {
        $sql .= " WHERE courseid = $item->courseid AND modid = $item->modid AND serverid IN ($newId, $item->serverid) ORDER BY updated DESC";
    } else if ($tableName == OPPIA_GRADE_BOUNDARY_TABLE) {
        $sql .= " WHERE courseid = $item->courseid AND modid = $item->modid AND grade = $item->grade AND serverid IN ($newId, $item->serverid) ORDER BY id DESC";
    } else if ($tableName == OPPIA_CONFIG_TABLE) {
        $sql .= " WHERE modid = $item->modid AND name = '$item->name' AND serverid IN ($newId, $item->serverid) ORDER BY id DESC";
    }
    return array_values($DB->get_records_sql($sql));
}