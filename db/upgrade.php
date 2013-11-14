<?php 

// This file keeps track of upgrades to 
// the oppia_mobile_export block

function xmldb_block_oppia_mobile_export_upgrade($oldversion) {

    global $CFG, $DB, $OUTPUT;

	$dbman = $DB->get_manager();
	
    if ($oldversion < 2013111402) {
        // block savepoint reached
        upgrade_block_savepoint(true, 2013111402, 'oppia_mobile_export');
    }
     
    return true;
}