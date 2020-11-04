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
 * Manages the data for the enrol audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit;

defined('MOODLE_INTERNAL') || die;

use context;

/**
 * Class that manages selected values as well as generates SQL for
 * the enrol audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolaudit {

    const ENROLMENT_DELETED = 0;
    const ENROLMENT_INITIAL = 1;
    const ENROLMENT_CREATED = 2;
    const ENROLMENT_UPDATED = 3;
    const ENROLMENT_STATUS_SUSPENDED = 4;
    const ENROLMENT_STATUS_ACTIVE = 5;

    /** @var context context of the report */
    protected $context;

    /** @var int course id to filter results by */
    protected $courseid = 0;

    /** @var int user id to filter results by */
    protected $userid = 0;

    /** @var string firstname that gets set by search form */
    protected $firstname = '';

    /** @var string lastname that gets set by search form */
    protected $lastname = '';

    /** @var string coursename that gets set by search form */
    protected $coursename = '';

    /** @var \moodle_url baseurl of the report */
    protected $baseurl;

    /** @var array parameters for filtering the report */
    protected $params;

    /**
     * Set up the enrolaudit class.
     *
     * @param object $course course object if we are in course level view.
     * @param context $context context the report is running in.
     * @param int $userid user id if report is filtered by user.
     * @param \moodle_url $baseurl base url for the report.
     */
    public function __construct($course, $context, $userid, $baseurl) {
        $this->courseid = $course ? $course->id : 0;
        $this->context = $context;
        $this->baseurl = $baseurl;
        $this->userid = $userid;
    }

    /**
     * Gets the fields to SELECT for the SQL query.
     *
     * @return string
     */
    public function get_fields_sql() {
        $userfields = get_all_user_name_fields(true, 'u');
        $modifierfields = get_all_user_name_fields(true, 'm', '', 'modifier');

        return "
            re.id,
            u.id AS userid,
            courseid,
            c.fullname AS coursename,
            re.change,
            modifierid,
            re.timemodified,
            $userfields,
            $modifierfields
        ";
    }

    /**
     * Fetches the FROM SQL for the query.
     *
     * @return string
     */
    public function get_from_sql() {
        return "{report_enrolaudit} re
                    JOIN {user} u ON u.id = re.userid
                    JOIN {user} m ON m.id = re.modifierid
                    JOIN {course} c ON c.id = re.courseid";
    }

    /**
     * Get the params based on any filters that have been set.
     * Should only be called after get_where_sql.
     *
     * @return array
     */
    public function get_params() {
        return $this->params;
    }

    /**
     * Gets the WHERE clause and sets up report parameters.
     *
     * @return string
     */
    public function get_where_sql() {
        $where = "re.change != :initialrecord";
        $this->params['initialrecord'] = self::ENROLMENT_INITIAL;

        if ($this->courseid) {
            $where .= " AND c.id = :courseid";
            $this->params['courseid'] = $this->courseid;
        }
        if ($this->userid) {
            $where .= " AND u.id = :userid";
            $this->params['userid'] = $this->userid;
        }
        if ($this->firstname) {
            $where .= " AND LOWER(u.firstname) LIKE :firstname";
            $this->params['firstname'] = '%'.strtolower($this->firstname).'%';
        }
        if ($this->lastname) {
            $where .= " AND LOWER(u.lastname) LIKE :lastname";
            $this->params['lastname'] = '%'.strtolower($this->lastname).'%';
        }
        if ($this->coursename) {
            $where .= " AND LOWER(c.fullname) LIKE :coursename";
            $this->params['coursename'] = '%'.strtolower($this->coursename).'%';
        }

        return $where;
    }

    /**
     * Getter for baseurl.
     *
     * @return \moodle_url
     */
    public function get_baseurl() {
        return $this->baseurl;
    }

    /**
     * Getter for context.
     *
     * @return context
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Getter for courseid.
     *
     * @return int
     */
    public function get_courseid() {
        return $this->courseid;
    }

    /**
     * Getter for userid.
     *
     * @return int
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * Generates and returns the filename for report downloads.
     *
     * @param $userenrolmentid
     * @return string
     */
    public function get_filename() {
        return 'enrolaudit_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
    }

    /**
     * Get the current status for a given user enrolment.
     *
     * @param $userenrolmentid
     * @return string
     */
    public static function get_current_status($userenrolmentid) {
        global $DB;

        return $DB->get_field('user_enrolments', 'status', ['id' => $userenrolmentid]);
    }

    /**
     * Get the previous status for a given user enrolment.
     *
     * @return string
     */
    public static function get_previous_status($userenrolmentid) {
        global $DB;

        $records = $DB->get_records(
            'report_enrolaudit',
            ['userenrolmentid' => $userenrolmentid],
            'timemodified DESC',
            'status'
        );

        // We are only interested in the latest record.
        $previousrecord = array_shift($records);

        return $previousrecord->status;
    }

    /**
     * Is there a difference between the current status in the user_enrolments table
     * and the last the the enrol audit report logged?
     *
     * @param $userenrolmentid
     * @return bool
     */
    public static function status_has_changed($userenrolmentid) {

        $currentstatus = self::get_current_status($userenrolmentid);
        $previousstatus = self::get_previous_status($userenrolmentid);

        return $currentstatus != $previousstatus;
    }

    /**
     * Setter for firstname.
     *
     * @param string $firstname
     */
    public function set_firstname($firstname) {
        $this->firstname = $firstname;
    }

    /**
     * Setter for coursename.
     *
     * @param string $coursename
     */
    public function set_coursename($coursename) {
        $this->coursename = $coursename;
    }

    /**
     * Setter for lastname.
     *
     * @param string $lastname
     */
    public function set_lastname($lastname) {
        $this->lastname = $lastname;
    }

    /**
     * Converts the numerical representation of a change to text.
     *
     * @param $change
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public static function get_change_description($change) {
        switch ($change) {
            case self::ENROLMENT_DELETED:
                return get_string('enrolmentdeleted', 'report_enrolaudit');
            case self::ENROLMENT_CREATED:
                return get_string('enrolmentcreated', 'report_enrolaudit');
            case self::ENROLMENT_STATUS_SUSPENDED:
                return get_string('enrolmentsuspended', 'report_enrolaudit');
            case self::ENROLMENT_STATUS_ACTIVE:
                return get_string('enrolmentactive', 'report_enrolaudit');
            case self::ENROLMENT_UPDATED:
                return get_string('enrolmentupdated', 'report_enrolaudit');
            default:
                return '';
        }
    }
}
