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

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../constants.php');
require_once(dirname(__FILE__) . '/../migrations/populate_digests.php');

// This file keeps track of upgrades to the oppia_mobile_export block.

function xmldb_block_oppia_mobile_export_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013111402) {
        // Block savepoint reached.
        upgrade_block_savepoint(true, 2013111402, 'oppia_mobile_export');
    }

    if ($oldversion < 2014032100) {

        // Define table block_oppia_mobile_server to be created.
        $table = new xmldb_table(OPPIA_SERVER_TABLE);

        // Adding fields to table block_oppia_mobile_server.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('servername', XMLDB_TYPE_CHAR, '50', null, null, null, '');
        $table->add_field('url', XMLDB_TYPE_CHAR, '50', null, null, null, '');
        $table->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('username', XMLDB_TYPE_CHAR, '50', null, null, null, '');
        $table->add_field('apikey', XMLDB_TYPE_CHAR, '50', null, null, null, '');
        $table->add_field('defaultserver', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table block_oppia_mobile_server.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_oppia_mobile_server.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2014032100, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2015021802) {

        // Changing type of field value on table block_oppia_mobile_config to text.
        $table = new xmldb_table(OPPIA_CONFIG_TABLE);
        $field = new xmldb_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');

        // Launch change of type for field value.
        $dbman->change_field_type($table, $field);

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2015021802, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2016021500) {

        // Add the field serverid to table block_oppia_mobile_config.
        $table = new xmldb_table(OPPIA_CONFIG_TABLE);
        if (!$dbman->field_exists($table, 'serverid')) {
            $field = new xmldb_field('serverid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'value');
            $dbman->add_field($table, $field);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2016021500, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2016041301) {

        // Update the size for field value to support longer tag values.
        $table = new xmldb_table(OPPIA_CONFIG_TABLE);
        $field = new xmldb_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');

        // Launch change of type for field value.
        $dbman->change_field_type($table, $field);

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2016041301, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2019102702) {

        // Define table block_oppia_publish_log to be created.
        $table = new xmldb_table(OPPIA_PUBLISH_LOG_TABLE);

        // Adding fields to table block_oppia_mobile_server.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('logdatetime', XMLDB_TYPE_INTEGER, '18', null, null, null, '0');
        $table->add_field('server', XMLDB_TYPE_CHAR, '200', null, null, null, '');
        $table->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '50', null, null, null, '');
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');

        // Adding keys to table block_oppia_publish_log.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_oppia_publish_log.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2019102702, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2020082701) {

        // Add the field serverid to table block_oppia_mobile_config.
        $table = new xmldb_table(OPPIA_SERVER_TABLE);
        $field = new xmldb_field('username');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('apikey');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2020082701, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2022091501) {

        // Define table block_oppia_activity_digest to be created.
        $table = new xmldb_table(OPPIA_DIGEST_TABLE);

        // Adding fields to table block_oppia_activity_digest.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('digest', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('updated', XMLDB_TYPE_INTEGER, '18', null, null, null, '0');
        $table->add_field('serverid', XMLDB_TYPE_CHAR, '200', null, null, null, '');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, '');
        $table->add_field('nquestions', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table block_oppia_activity_digest.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_oppia_activity_digest.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2022091501, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2022102700) {
        // Rename field 'digest' to 'oppiaserverdigest'
        // Add the field 'moodleactivitymd5' to table 'block_oppia_activity_digest'
        //
        // oppiaserverdigest - Digest that is currently in use by the Oppia server
        // moodleactivitymd5 - Real digest of the last published Moodle activity.
        $table = new xmldb_table(OPPIA_DIGEST_TABLE);

        $field = new xmldb_field('digest');
        $field->set_attributes(XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '', 'digest');
        $dbman->rename_field($table, $field, 'oppiaserverdigest');

        if (!$dbman->field_exists($table, 'moodleactivitymd5')) {
            $field = new xmldb_field('moodleactivitymd5', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0');
            $dbman->add_field($table, $field);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2022102700, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2022113100) {

        // Define table block_oppia_grade_boundary to be created.
        $table = new xmldb_table(OPPIA_GRADE_BOUNDARY_TABLE);

        // Adding fields to table block_oppia_grade_boundary.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('message', XMLDB_TYPE_CHAR, '200', null, null, null, '');
        $table->add_field('serverid', XMLDB_TYPE_CHAR, '200', null, null, null, '');

        // Adding keys to table block_oppia_grade_boundary.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_oppia_grade_boundary.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2022113100, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2023022801) {

        $table = new xmldb_table(OPPIA_GRADE_BOUNDARY_TABLE);
        $field = new xmldb_field('message');
        if ($dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_CHAR, '1000', null, null, null, '', );
            $dbman->change_field_precision($table, $field);
        }

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2023022801, 'block', 'oppia_mobile_export');
    }

    if ($oldversion < 2023063000) {
        $table = new xmldb_table(OPPIA_SERVER_TABLE);
        $field = new xmldb_field('moodleuserid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Don't forget to run v1.4.5_migrations.php

        // Blocks savepoint reached.
        upgrade_plugin_savepoint(true, 2023063000, 'block', 'oppia_mobile_export');

    }

    return true;

}
