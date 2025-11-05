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

/**
 * Upgrade steps for Generative AI Question Bank
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    qbank_genai
 * @category   upgrade
 * @copyright  2024 2023 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_qbank_genai_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024121205) {
        // Define table qbank_genai_openai_settings to be created.
        $table = new xmldb_table('qbank_genai_openai_settings');

        // Adding fields to table qbank_genai_openai_settings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('openaiapikey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assistantid', XMLDB_TYPE_CHAR, '100', null, null, null, null);

        // Adding keys to table qbank_genai_openai_settings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('genai-course-foreign-key', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('genai-user-foreign-key', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for qbank_genai_openai_settings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Genai savepoint reached.
        upgrade_plugin_savepoint(true, 2024121205, 'qbank', 'genai');
    }

    if ($oldversion < 2025082601) {
        // Changing precision of field openaiapikey on table qbank_genai_openai_settings to (200).
        $table = new xmldb_table('qbank_genai_openai_settings');
        $field = new xmldb_field('openaiapikey', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null, 'userid');

        // Launch change of precision for field openaiapikey.
        $dbman->change_field_precision($table, $field);

        // Genai savepoint reached.
        upgrade_plugin_savepoint(true, 2025082601, 'qbank', 'genai');
    }

    return true;
}
