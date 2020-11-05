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

require_once(__DIR__ . '/base.php');

class report_enrolaudit_events_testcase extends advanced_testcase {
    use report_enrolaudit_test_helper;

    public function test_enrolment_created_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        foreach ($users as $user) {
            $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);
        }

        // Delete one user's enrolment.
        // This is to test we can still get an enrolment from the logs.
        $manual->unenrol_user($enrolinstance, $users[1]->id);

        // Check all our users have been added to the log table.
        $this->assertEquals(count($users), $DB->count_records('report_enrolaudit', [
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]));

        foreach ($users as $user) {
            $enrolaudit = $DB->get_record('report_enrolaudit', [
                'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED,
                'userid' => $user->id
            ]);
            $userenrolment = $DB->get_record('user_enrolments', ['userid' => $user->id]);

            $this->assertNotFalse($enrolaudit);
            $this->assertEquals(\report_enrolaudit\enrolaudit::ENROLMENT_CREATED, $enrolaudit->changetype);
            $this->assertEquals($course->id, $enrolaudit->courseid);

            if ($userenrolment) {
                // User was already un-enrolled.
                $this->assertEquals($userenrolment->id, $enrolaudit->userenrolmentid);
            }
        }
    }

    public function test_enrolment_updated_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        foreach ($users as $user) {
            $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);
            // Set time created to be in the past.
            $DB->set_field('user_enrolments', 'timecreated', time() - 10, ['userid' => $user->id]);
            $DB->set_field('report_enrolaudit', 'timemodified', time() - 10,
                [
                    'userid' => $user->id,
                    'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
                ]
            );

            // Update the user enrolment.
            $manual->update_user_enrol($enrolinstance, $user->id, ENROL_USER_SUSPENDED);
            $DB->set_field('report_enrolaudit', 'timemodified', time() - 5,
                [
                    'userid' => $user->id,
                    'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_UPDATED
                ]
            );
        }

        // Add an additional update.
        $manual->update_user_enrol($enrolinstance, $users[1]->id, null, time() + 10);

        $updatedrecordsql = "SELECT COUNT(*)
                               FROM {report_enrolaudit}
                              WHERE userid = :userid
                                AND changetype <> :changetype";

        // Test the first user.
        $user1enrolment = $DB->get_record('user_enrolments', ['userid' => $users[0]->id]);
        $user1enrolaudit = $DB->get_record('report_enrolaudit', [
            'userid' => $users[0]->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED
        ]);

        $user1recordcount = $DB->count_records_sql($updatedrecordsql, [
            'userid' => $users[0]->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]);

        $this->assertNotFalse($user1enrolaudit);
        $this->assertEquals(ENROL_USER_SUSPENDED, $user1enrolaudit->status);

        // Test the second user. They have an additional update.
        $user2enrolment = $DB->get_record('user_enrolments', ['userid' => $users[1]->id]);
        $user2suspended = $DB->get_record('report_enrolaudit', [
            'userid' => $users[1]->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED
        ]);

        $user2recordcount = $DB->count_records_sql($updatedrecordsql, [
            'userid' => $users[1]->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED
        ]);

        $this->assertEquals(2, $user2recordcount);
        $this->assertEquals(ENROL_USER_SUSPENDED, $user2suspended->status);

        $user2updated = $DB->get_record('report_enrolaudit', [
            'userid' => $users[1]->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_UPDATED
        ]);

        $this->assertNotFalse($user2updated);
    }

    public function test_enrolment_deleted_records() {
        global $DB;

        $this->resetAfterTest();

        list($users, $course, $enrolinstance, $manual, $studentrole) = $this->get_test_values();

        $user = $users[0];

        // Add enrolment records.
        $manual->enrol_user($enrolinstance, $user->id, $studentrole->id);

        // Delete user enrolment.
        $manual->unenrol_user($enrolinstance, $user->id);

        // Check the record has been added.
        $deletedrecord = $DB->get_record('report_enrolaudit', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'changetype' => \report_enrolaudit\enrolaudit::ENROLMENT_DELETED,
        ]);

        $this->assertNotFalse($deletedrecord);

    }
}
