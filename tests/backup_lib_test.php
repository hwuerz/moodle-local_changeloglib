<?php
// This file is part of ChangelogLib plugin for Moodle - http://moodle.org/
//
// ChangelogLib is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// ChangelogLib is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with ChangelogLib.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ChangelogLib.
 *
 * A library to support other plugins which have to detect changes in files.
 * This plugin does not do anything of its own. It is only useful as a
 * dependency for other plugins.
 *
 * @package   local_changeloglib
 * @author    Hendrik Wuerz <hendrikmartin.wuerz@stud.tu-darmstadt.de>
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/../definitions.php');
require_once(dirname(__FILE__) . '/helper.php');
require_once(dirname(__FILE__) . '/../classes/backup_lib.php');

/**
 * Class local_changeloglib_backup_test.
 *
 * vendor/bin/phpunit local_changeloglib_backup_lib_test local/changeloglib/tests/backup_lib_test.php
 *
 * Creates a new course module resource.
 * -> Triggers a backup.
 * -> Checks that only the correct amount of backup files was created.
 * -> Checks correct data in Backup table.
 * -> Checks that cleaning old files work correctly.
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group local_changeloglib
 */
class local_changeloglib_backup_lib_test extends advanced_testcase {

    /**
     * Checks that a valid backup can be created.
     */
    public function test_backup() {
        $this->resetAfterTest(true);
        $this->create_backup();
    }

    /**
     * Checks that clean up all really cleans up all files.
     */
    public function test_delete_all() {
        $this->resetAfterTest(true);
        $this->create_backup();
        local_changeloglib_backup_lib::clean_up_all_files();
        $this->ensure_amount_of_backups(0);
    }

    /**
     * Checks that clean up old really cleans up old files and not more.
     */
    public function test_delete_old() {
        global $DB;

        $this->resetAfterTest(true);
        $this->create_backup();
        $this->ensure_amount_of_backups(1);

        // The backup was created right now --> it must not become deleted.
        local_changeloglib_backup_lib::clean_up_old_files();
        $this->ensure_amount_of_backups(1);

        // Manipulate backup timestamp to make it older but not old enough.
        $DB->execute('UPDATE {' . local_changeloglib_backup_lib::BACKUP_TABLE . '}'
            . ' SET timestamp = ' . (time() - 60 * 60 + 10) // 10 seconds before possible deletion.
        );
        local_changeloglib_backup_lib::clean_up_old_files();
        $this->ensure_amount_of_backups(1);

        // Manipulate backup timestamp to make it older but not old enough.
        $DB->execute('UPDATE {' . local_changeloglib_backup_lib::BACKUP_TABLE . '}'
            . ' SET timestamp = ' . (time() - 60 * 60 - 1) // One second after possible deletion.
        );
        local_changeloglib_backup_lib::clean_up_old_files();
        $this->ensure_amount_of_backups(0);
    }

    /**
     * Creates a course module and a backup of it. Checks the created backup to be valid.
     */
    private function create_backup() {
        global $DB;
        $start_time = time();

        // Count existing backups.
        $backups_before = $DB->count_records(local_changeloglib_backup_lib::BACKUP_TABLE);

        // Create user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create resource.
        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', array(
            'course' => $course->id
        ));

        // Create file for resource.
        $contextid = context_module::instance($resource->cmid)->id;
        $component = 'mod_resource';
        $filearea = 'content';
        get_file_storage()->delete_area_files($contextid, $component, $filearea); // Delete auto generated file.
        $file = local_changeloglib_test_helper::create_file($contextid, 'file.pdf', $component, $filearea); // Create own files.

        // Backup the resource.
        $data = array('testid' => 42);
        $context_id_to = context_course::instance($resource->course)->id;
        $scope_id = 99;
        local_changeloglib_test_helper::backup($file, $data, $context_id_to, $scope_id);

        // Assert that exactly one new backup record was created.
        $this->ensure_amount_of_backups($backups_before + 1);

        // Get the new backup record.
        $backups = $DB->get_records(local_changeloglib_backup_lib::BACKUP_TABLE,
            array(),
            'timestamp DESC',
            '*',
            '0',
            '1');

        $last_backup = reset($backups);

        // Check the inserted data.
        $this->assertEquals($context_id_to, $last_backup->context);
        $this->assertEquals($scope_id, $last_backup->scope);
        $this->assertEquals($data, (array)json_decode($last_backup->data));
        $this->assertGreaterThanOrEqual($start_time, $last_backup->timestamp);
        $this->assertLessThanOrEqual(time(), $last_backup->timestamp);

        // Restore the backup and ensure that it is equal to the original file.
        $backup_file = local_changeloglib_backup_lib::get_backup_file($last_backup);
        $this->assertEquals($file->get_contenthash(), $backup_file->get_contenthash());
    }

    /**
     * Ensures that there are exactly as many backups as passed.
     * @param int $amount The exact amount of required backup entries.
     */
    private function ensure_amount_of_backups($amount) {
        global $DB;

        // Backup table.
        $backups = $DB->count_records(local_changeloglib_backup_lib::BACKUP_TABLE);
        $this->assertEquals($amount, $backups);

        // File storage.
        $backups = $DB->count_records_select('files',
            "component LIKE '" . local_changeloglib_backup_lib::BACKUP_COMPONENT . "' "
            . "AND filearea LIKE '" . local_changeloglib_backup_lib::BACKUP_FILEAREA . "' "
            . "AND filesize > 0" // Needed to exclude '.'-files for directory.
        );
        $this->assertEquals($amount, $backups);
    }

}
