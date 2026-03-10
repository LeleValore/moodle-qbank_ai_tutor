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

namespace qbank_genai\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for qbank_genai.
 *
 * @package    qbank_genai
 * @copyright  2025 Niko Hoogeveen <nikohoogeveen@catalyst-ca.net>, Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('qbank_genai_openai_settings', [
            'userid' => 'privacy:metadata:qbank_genai_openai_settings:userid',
        ], 'privacy:metadata:qbank_genai_openai_settings');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
            FROM {context} c
            INNER JOIN {qbank_genai_openai_settings} s ON c.instanceid = s.courseid AND c.contextlevel = :contextlevel
            WHERE s.userid = :userid
        ";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid'       => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT s.userid
            FROM {qbank_genai_openai_settings} s
            WHERE s.courseid = :instanceid
        ";

        $params = [
            'instanceid' => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Retrieves the records related to a given user and context.
     *
     * @param int $userid       The ID of the user.
     * @param int $contextid    The ID of the context.
     *
     * @return stdClass[] The retrieved records.
     */
    private static function get_records(int $userid, int $contextid) {
        global $DB;

        $data = [];

        $sql = "SELECT s.id, s.courseid, s.userid, s.openaiapikey
            FROM {qbank_genai_openai_settings} s
            INNER JOIN {context} c ON c.instanceid = s.courseid AND c.contextlevel = :contextlevel
            WHERE s.userid = :userid AND c.id = :contextid
        ";

        $params = [
            'userid' => $userid,
            'contextid' => $contextid,
            'contextlevel' => CONTEXT_COURSE,
        ];

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $record) {
            $data[] = (object) [
                'id' => $record->id,
                'courseid' => $record->courseid,
                'userid' => transform::user($record->userid),
                'openaiapikey' => $record->openaiapikey,
            ];
        }

        $rs->close();

        return $data;
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            $data = self::get_records($user->id, $context->id);

            if (!empty($data)) {
                $subcontext = get_string('pluginname', 'qbank_genai');
                writer::with_context($context)
                    ->export_data([$subcontext], (object) [
                        'settings' => $data,
                    ]);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context the context to delete from.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $sql = "SELECT s.id
            FROM {qbank_genai_openai_settings} s
            INNER JOIN {context} c ON c.instanceid = s.courseid AND c.contextlevel = :contextlevel
            WHERE c.id = :contextid
        ";

        $params = [
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE,
        ];

        $recordids = [];

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $record) {
            $recordids[] = $record->id;
        }

        $rs->close();

        foreach ($recordids as $recordid) {
            $DB->delete_records('qbank_genai_openai_settings', ['id' => $recordid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            $data = self::get_records($user->id, $context->id);

            foreach ($data as $record) {
                $DB->delete_records('qbank_genai_openai_settings', ['id' => $record->id]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        foreach ($userids as $userid) {
            $data = self::get_records($userid, $context->id);

            foreach ($data as $record) {
                $DB->delete_records('qbank_genai_openai_settings', ['id' => $record->id]);
            }
        }
    }
}
