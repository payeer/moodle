<?php

function xmldb_enrol_payeer_upgrade($oldversion=0) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Add instanceid field to enrol_payeer_transactions table
    if ($oldversion < 2011121300) {
        $table = new xmldb_table('enrol_payeer_transactions');
        $field = new xmldb_field('instanceid');
        if (!$dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'userid');
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011121300, 'enrol', 'payeer');
    }

    return true;
}

