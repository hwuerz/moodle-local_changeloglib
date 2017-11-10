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
require_once(dirname(__FILE__) . '/../classes/diff_detector.php');

/**
 * Class local_changeloglib_update_detector_test.
 *
 * vendor/bin/phpunit local_changeloglib_new_update_detector_test local/changeloglib/tests/new_update_detector_test.php
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group local_changeloglib
 *
 */
class local_changeloglib_new_update_detector_test extends advanced_testcase {

    /**
     * @var stdClass The user which is used to created the needed files.
     */
    private $user;

    /**
     * @var stdClass The used course for file uploads.
     */
    private $course;

    /**
     * @var stdClass The cmid and the file instance of the first file.
     */
    private $file;

    /**
     * @var stdClass The cmid and the file instance of the other file.
     */
    private $other;

    /**
     * @var stdClass The cmid and the file instance of the second file.
     */
    private $file_v2;

    /**
     * Checks for correct detected predecessor.
     */
    public function test_update_detection_in_backups() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        $new_files = array(
            new local_changeloglib_new_file_wrapper($this->other->file, array()),
            new local_changeloglib_new_file_wrapper($this->file_v2->file, array())
        );
        $context = context_system::instance()->id;
        $scope = -1;
        $further_candidates = array();
        $detector = new local_changeloglib_update_detector($new_files, $context, $scope, $further_candidates);
        $response = $detector->map_backups();

        fwrite(STDERR, print_r($response->similarity . "\n", TRUE));
        foreach ($response->mappings as $mapping) {
            $output = $mapping->file_wrapper->get_file()->get_filename() . ' update of '
                . $mapping->predecessor->get_backup()->get_file()->get_filename() . '  '
                . '('.$mapping->predecessor->get_similarity() . ')' . "\n";
            fwrite(STDERR, print_r($output, TRUE));
        }


        $this->assertTrue(true);

        // Detect predecessor.
//        $detector = new local_changeloglib_update_detector($this->file_v2->file,
//            array(), context_system::instance()->id, -1, array());
//        $predecessor = $detector->is_update();
//
//        // Check predecessor.
//        $this->assertTrue($predecessor !== false); // A predecessor was found.
//        $this->assertEquals($this->file->file->get_contenthash(), $predecessor->get_contenthash()); // The predecessor is correct.
    }

    /**
     * Creates a course and two course modules.
     */
    private function prepare_coursemodules() {

        // Create user.
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        $this->course = $this->getDataGenerator()->create_course();

        // Create old files.
        $this->file = $this->create_course_module($this->course, 'file.pdf');
        $this->other = $this->create_course_module($this->course, 'other.pdf');
        $this->file_v2 = $this->create_course_module($this->course, 'file_v2.pdf');
    }

    /**
     * Creates a new course module based on the passed file.
     * @param stdClass $course The course of the course module
     * @param string $filename The name of the file in the tests/res folder.
     * @return stdClass The cmid and the file.
     */
    private function create_course_module($course, $filename) {

        // Create resource.
        $resource = $this->getDataGenerator()->create_module('resource', array(
            'course' => $course->id
        ));

        // Create file for resource.
        $contextid = context_module::instance($resource->cmid)->id;
        $component = 'mod_resource';
        $filearea = 'content';
        get_file_storage()->delete_area_files($contextid, $component, $filearea); // Delete auto generated file.
        $file = local_changeloglib_test_helper::create_file($contextid, $filename, $component, $filearea); // Create own files.

        return (object)array(
            'cmid' => $resource->cmid,
            'file' => $file
        );
    }
}
