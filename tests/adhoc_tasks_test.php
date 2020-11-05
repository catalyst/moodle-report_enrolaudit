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

class report_enrolaudit_testcase extends advanced_testcase {

    public function test_import_enrolment_created_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        foreach ($users as $user) {
            $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);
        }

        // Add one of our users to the log store as well.
        $this->add_logstore_entry($course, $users[0], '\core\event\user_enrolment_created');

        // Delete one user's enrolment.
        // This is to test we can still get an enrolment from the logs.
        $manual->unenrol_user($enrolinstance, $users[1]->id);
        $this->add_logstore_entry($course, $users[1], '\core\event\user_enrolment_created');

        $this->run_adhoc_task();

        // Check all our users have been added to the log table.
        $this->assertEquals(sizeof($users), $DB->count_records('report_enrolaudit'));

        foreach ($users as $user) {
            $enrolaudit = $DB->get_record('report_enrolaudit', ['userid' => $user->id]);
            $userenrolment = $DB->get_record('user_enrolments', ['userid' => $user->id]);

            $this->assertNotFalse($enrolaudit);
            $this->assertEquals(\report_enrolaudit\enrolaudit::ENROLMENT_CREATED, $enrolaudit->change);
            $this->assertEquals($course->id, $enrolaudit->courseid);

            if ($userenrolment) {
                // User was already un-enrolled.
                $this->assertEquals($userenrolment->id, $enrolaudit->userenrolmentid);
            }
        }
    }

    public function test_import_enrolment_updated_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        foreach ($users as $user) {
            $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);

            // Set time created to be in the past.
            $DB->set_field('user_enrolments', 'timecreated', time() - 10, ['userid' => $user->id]);
            // Add created log to log store.
            $this->add_logstore_entry($course, $user, '\core\event\user_enrolment_created', time() - 10);

            // Update the user enrolment.
            $manual->update_user_enrol($enrolinstance, $user->id, ENROL_USER_SUSPENDED);
            // Add update to log store.
            // Should be ignored as it will match the latest user enrolment record.
            $this->add_logstore_entry($course, $user, '\core\event\user_enrolment_updated');
        }

        // Add an update in between record creation and last time the user enrolment was modified.
        $this->add_logstore_entry($course, $users[1], '\core\event\user_enrolment_updated', time() - 5);

        // Delete one user's enrolment.
        // This is to test we can still get an enrolment from the logs.
        $manual->unenrol_user($enrolinstance, $users[2]->id);

        $this->run_adhoc_task();

        $updatedrecordsql = "SELECT COUNT(*)
                               FROM {report_enrolaudit}
                              WHERE userid = :userid
                                AND change <> :change";

        // Test the first user. They have a record in user enrolments and the log.
        $user1enrolment = $DB->get_record('user_enrolments', ['userid' => $users[0]->id]);
        $user1enrolaudit = $DB->get_record('report_enrolaudit', [
            'userid' => $users[0]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED
        ]);

        $user1updatedrecordcount = $DB->count_records_sql($updatedrecordsql, [
            'userid' => $users[0]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]);

        $this->assertNotFalse($user1enrolaudit);
        $this->assertEquals($course->id, $user1enrolaudit->courseid);
        $this->assertEquals(1, $user1updatedrecordcount);

        $this->assertEquals($user1enrolment->id, $user1enrolaudit->userenrolmentid);
        $this->assertEquals(ENROL_USER_SUSPENDED, $user1enrolaudit->status);

        // Test the second user. They have an additional update to add from the log table.
        $user2enrolment = $DB->get_record('user_enrolments', ['userid' => $users[1]->id]);
        $user2enrolaudit = $DB->get_record('report_enrolaudit', [
            'userid' => $users[1]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED
        ]);

        $user2updatedrecordcount = $DB->count_records_sql($updatedrecordsql, [
            'userid' => $users[1]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]);

        $this->assertNotFalse($user2enrolaudit);
        $this->assertEquals($course->id, $user2enrolaudit->courseid);
        $this->assertEquals(2, $user2updatedrecordcount);

        $this->assertEquals($user2enrolment->id, $user2enrolaudit->userenrolmentid);
        $this->assertEquals(ENROL_USER_SUSPENDED, $user2enrolaudit->status);

        // Test the third user. They have been un-enrolled.
        $user3enrolment = $DB->get_record('user_enrolments', ['userid' => $users[2]->id]);
        $user3enrolaudit = $DB->get_record('report_enrolaudit', [
            'userid' => $users[2]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_UPDATED
        ]);

        $user3updatedrecordcount = $DB->count_records_sql($updatedrecordsql, [
            'userid' => $users[2]->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]);

        $this->assertNotFalse($user3enrolaudit);
        $this->assertFalse($user3enrolment);
        $this->assertEquals($course->id, $user3enrolaudit->courseid);
        $this->assertEquals(1, $user3updatedrecordcount);

        // Is a historic record so we cannot know what the status was.
        $this->assertEquals(ENROL_USER_ACTIVE, $user3enrolaudit->status);
    }

    public function test_import_enrolment_deleted_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        $user = $users[0];

        // Add enrolment records.
        $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);
        $this->add_logstore_entry($course, $user, '\core\event\user_enrolment_created');

        // Delete user enrolment.
        $manual->unenrol_user($enrolinstance, $user->id);
        $this->add_logstore_entry($course, $user, '\core\event\user_enrolment_deleted');

        $this->run_adhoc_task();

        // Check the record has been added.
        $deletedenrolmentrecord = $DB->get_record('report_enrolaudit', [
            'userid' => $user->id,
            'change' => \report_enrolaudit\enrolaudit::ENROLMENT_DELETED,
        ]);

        $this->assertNotFalse($deletedenrolmentrecord);

    }

    private function get_test_values() {
        global $DB;
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(array(
            'shortname' => 'course1',
            'category' => $category->id,
        ));

        $enrolinstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manual = enrol_get_plugin('manual');

        return [
            $users,
            $course,
            $enrolinstance,
            $manual,
            $studentrole
        ];
    }

    private function run_adhoc_task() {
        global $DB;
        // Simulate fresh install and run adhoc task.
        $DB->delete_records('report_enrolaudit');
        $adhoctask = new \report_enrolaudit\task\populate_enrolaudit_log_table();
        $adhoctask->execute();
    }

    private function add_logstore_entry($course, $user, $eventname, $timecreated = 0) {
        global $DB;
        $context = context_course::instance($course->id);

        if (!$timecreated) {
            $timecreated = time();
        }

        $record = (object)[
            'objectid' => $DB->get_field('user_enrolments', 'id', ['userid' => $user->id]),
            'eventname' => $eventname,
            'edulevel' => 0,
            'courseid' => $course->id,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => $user->id,
            'relateduserid' => $user->id,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('logstore_standard_log', $record);
    }
}
