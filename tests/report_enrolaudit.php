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


class adhoc_tasks_testcase extends advanced_testcase {

    public function test_insert_enrol_create_records() {

    }

    /**
     * Test report_log_supports_logstore.
     */
    public function test_report_participation_supports_logstore() {
        $logmanager = get_log_manager();
        $allstores = \core_component::get_plugin_list_with_class('logstore', 'log\store');

        $supportedstores = array(
            'logstore_legacy' => '\logstore_legacy\log\store',
            'logstore_standard' => '\logstore_standard\log\store'
        );

        // Make sure all supported stores are installed.
        $expectedstores = array_keys(array_intersect($allstores, $supportedstores));
        $stores = $logmanager->get_supported_logstores('report_participation');
        $stores = array_keys($stores);
        foreach ($expectedstores as $expectedstore) {
            $this->assertContains($expectedstore, $stores);
        }
    }
}