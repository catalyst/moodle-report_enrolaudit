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
 * Privacy provider implementation for report_enrolaudit.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use report_enrolaudit\enrolaudit;

/**
 * Privacy provider implementation for report_enrolaudit.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
\core_privacy\local\metadata\provider,
\core_privacy\local\request\data_provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'report_enrolaudit',
            [
                'userenrolmentid' => 'privacy:metadata:report_enrolaudit:userenrolmentid',
                'courseid' => 'privacy:metadata:report_enrolaudit:courseid',
                'userid' => 'privacy:metadata:report_enrolaudit:userid',
                'modifierid' => 'privacy:metadata:report_enrolaudit:modifierid',
                'changetype' => 'privacy:metadata:report_enrolaudit:changetype',
                'status' => 'privacy:metadata:report_enrolaudit:status',
                'timemodified' => 'privacy:metadata:report_enrolaudit:timemodified'
            ],
            'privacy:metadata:report_enrolaudit'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $params = ['userid' => $userid, 'contextcourse' => CONTEXT_COURSE];
        $sql = "SELECT ctx.id
                FROM {context} ctx
                JOIN {report_enrolaudit} re ON re.courseid = ctx.instanceid AND re.userid = :userid
                WHERE ctx.contextlevel = :contextcourse";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $params = [
            'contextid' => $context->id,
            'contextcourse' => CONTEXT_COURSE,
        ];

        $sql = "SELECT re.userid
                  FROM {report_enrolaudit} re
                  JOIN {context} ctx
                       ON ctx.instanceid = re.courseid
                       AND ctx.contextlevel = :contextcourse
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if ($contextlist->get_component() != 'report_enrolaudit') {
            return;
        }

        $courseids = array_map(function($context) {
            return $context->instanceid;
        }, $contextlist->get_contexts());

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $sql = "SELECT re.*, c.fullname
                  FROM {report_enrolaudit} re
                  JOIN {course} c ON re.courseid = c.id
                 WHERE re.userid = :userid AND c.id $insql
              ORDER BY re.timemodified ASC";

        $params['userid'] = $contextlist->get_user()->id;
        $records = $DB->get_records_sql($sql, $params);

        $enrolauditrecords = [];
        foreach ($records as $record) {
            $context = \context_course::instance($record->courseid);
            if (!isset($enrolauditrecords[$record->courseid])) {
                $enrolauditrecords[$record->courseid] = new \stdClass();
                $enrolauditrecords[$record->courseid]->context = $context;
            }
            $enrolauditrecords[$record->courseid]->entries[] = [
                'course' => format_string($record->fullname, true, ['context' => $context]),
                'userenrolmentid' => $record->userenrolmentid,
                'changetype' => enrolaudit::get_change_description($record->change),
                'status' => $record->status,
                'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
            ];
        }

        foreach ($enrolauditrecords as $enrolauditrecord) {
            \core_privacy\local\request\writer::with_context(
                $enrolauditrecord->context
            )->export_data([get_string('pluginname', 'report_enrolaudit')], (object) $enrolauditrecord->entries);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_course) {
            list($usersql, $userparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $select = "courseid = :courseid AND userid {$usersql}";
            $params = ['courseid' => $context->instanceid] + $userparams;

            $DB->delete_records_select('report_enrolaudit', $select, $params);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_COURSE) {
            static::delete_enrolaudit_records($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if ($contextlist->get_component() != 'report_enrolaudit') {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                static::delete_enrolaudit_records($context->instanceid, $contextlist->get_user()->id);
            }
        }
    }

    /**
     * @param int $courseid
     * @param int|null $userid
     * @throws \dml_exception
     */
    protected static function delete_enrolaudit_records(int $courseid, int $userid = null) {
        global $DB;
        $params = ['courseid' => $courseid];
        if ($userid) {
            $params['userid'] = $userid;
        }

        $DB->delete_records('report_enrolaudit', $params);
    }
}
