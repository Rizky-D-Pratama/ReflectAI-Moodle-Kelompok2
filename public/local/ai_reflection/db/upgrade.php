<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_ai_reflection_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 1) Upgrade payloadjson to mediumtext.
    if ($oldversion < 2026051600) {
        $table = new xmldb_table('local_ai_reflection_payload');
        $field = new xmldb_field(
            'payloadjson',
            XMLDB_TYPE_TEXT,
            'medium',
            null,
            XMLDB_NOTNULL,
            null,
            null
        );

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
    }

    // 2) Create or repair the result table.
    if ($oldversion < 2026051805) {
        $table = new xmldb_table('local_ai_reflection_result');

        $fielddefs = [
            ['id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['payloadid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null],
            ['userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null],
            ['assignmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null],
            ['submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null],
            ['status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'done'],
            ['analysismode', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, 'text'],
            ['cananalyze', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'],
            ['visionstatus', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, ''],
            ['needsseparatehandling', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'],
            ['imagecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'],
            ['reflection', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['strengths', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['improvements', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['reason', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['prompt', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['responsejson', XMLDB_TYPE_TEXT, 'medium', null, null, null, null],
            ['timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'],
            ['timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'],
        ];

        if (!$dbman->table_exists($table)) {
            foreach ($fielddefs as $def) {
                $table->add_field($def[0], $def[1], $def[2], $def[3], $def[4], $def[5], $def[6]);
            }

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('submissionid_uix', XMLDB_KEY_UNIQUE, ['submissionid']);
            $table->add_index('assignmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assignmentid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        } else {
            foreach ($fielddefs as $def) {
                $field = new xmldb_field($def[0], $def[1], $def[2], $def[3], $def[4], $def[5], $def[6]);
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }

            $submissionuni = new xmldb_index('submissionid_uix', XMLDB_INDEX_UNIQUE, ['submissionid']);
            if (!$dbman->index_exists($table, $submissionuni)) {
                $dbman->add_index($table, $submissionuni);
            }

            $assignmentindex = new xmldb_index('assignmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assignmentid']);
            if (!$dbman->index_exists($table, $assignmentindex)) {
                $dbman->add_index($table, $assignmentindex);
            }

            $useridindex = new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            if (!$dbman->index_exists($table, $useridindex)) {
                $dbman->add_index($table, $useridindex);
            }
        }

        upgrade_plugin_savepoint(true, 2026051805, 'local', 'ai_reflection');
    }

    // 3) Tambah kolom teachernote.
    if ($oldversion < 2026052000) {
        $table = new xmldb_table('local_ai_reflection_result');
        $field = new xmldb_field('teachernote', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $field->setPrevious('responsejson');
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052000, 'local', 'ai_reflection');
    }

    return true;
}