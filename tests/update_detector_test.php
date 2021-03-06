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
 * vendor/bin/phpunit local_changeloglib_update_detector_test local/changeloglib/tests/update_detector_test.php
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group local_changeloglib
 *
 */
class local_changeloglib_update_detector_test extends advanced_testcase {

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

        // Detect predecessor.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // Check predecessor to exist and be correct.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array($this->file->file)));
        $this->assertTrue($mappings[0]->has_changed()); // This is a real update (file has changed).
    }

    /**
     * Ensure hard check of the context.
     */
    public function test_update_detection_different_context() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        // Detect predecessor.
        // !!! Use user context for the new file --> original documents were backuped in system context.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_user::instance($this->user->id)->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // No predecessor was found.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array(null)));
    }

    /**
     * Ensure hard check of the scope.
     */
    public function test_update_detection_different_scope() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        // Detect predecessor.
        // !!! Use scope 42 for the new file --> original documents were backuped in scope -1.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, 42, array());
        $mappings = $detector->map_backups()->mappings;

        // No predecessor was found.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array(null)));
    }

    /**
     * Ensure usage of further_candidates.
     */
    public function test_update_detection_further_candidates() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Detect predecessor.
        // !!! No backups were created. Only further candidates are available.
        $further_candidates = array($this->file->file, $this->other->file);
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, $further_candidates);
        $mappings = $detector->map_backups()->mappings;

        // Check predecessor to exist and be correct.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array($this->file->file)));
    }

    /**
     * No available candidates
     */
    public function test_update_detection_no_candidates() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Detect predecessor.
        // !!! No backups were created and no further candidates are available.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // No predecessor was found.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array(null)));
    }

    /**
     * Checks for min similarity.
     */
    public function test_update_detection_min_similarity() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        // Detect predecessor.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $detector->set_min_similarity(1);
        $mappings = $detector->map_backups()->mappings;

        // No predecessor was found because min similarity of 1 is not possible.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array(null)));
    }

    /**
     * No results if no fitting file type exists.
     */
    public function test_update_detection_no_correct_file_type() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Overwrite course modules with text files.
        $this->file = $this->create_course_module($this->course, 'file.txt');
        $this->other = $this->create_course_module($this->course, 'other.txt');

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        // Detect predecessor.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // No predecessor was found because min similarity of 1 is not possible.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array(null)));
    }

    /**
     * Find results with other file type if enabled
     */
    public function test_update_detection_enable_other_file_type() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Overwrite course modules with text files.
        $this->file = $this->create_course_module($this->course, 'file.txt');
        $this->other = $this->create_course_module($this->course, 'other.txt');

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->other->file);

        // Detect predecessor.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file_v2->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        // Enable other file types. !!!
        $detector->set_ensure_mime_type(false);
        $detector->set_min_similarity(0);
        $mappings = $detector->map_backups()->mappings;

        // Check predecessor to exist and be correct.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array($this->file->file)));
    }

    /**
     * The file itself is not found as a predecessor.
     * If the same file is a candidate, the detector must return false.
     * Otherwise simple save-operations would trigger unwanted updates.
     */
    public function test_update_detection_find_itself() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);
        local_changeloglib_test_helper::backup($this->file_v2->file);

        // Detect predecessor of file (!) not file_v2.
        $new_files = array(new local_changeloglib_new_file_wrapper($this->file->file, array()));
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // Check predecessor to exist and be correct.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files, array($this->file->file)));

        // Check that the mapping was recognized as an identical file.
        $this->assertFalse($mappings[0]->has_changed());
    }

    /**
     * There are two new files but only one backup.
     * The update_detector must not take the backup for the first file, because the similarity is greater for the second.
     */
    public function test_multiple_files() {
        $this->resetAfterTest(true);
        $this->prepare_coursemodules();

        // Backup old files.
        local_changeloglib_test_helper::backup($this->file->file);

        // Detect predecessor of file_v2.
        $new_files = array(
            new local_changeloglib_new_file_wrapper($this->other->file, array()),
            new local_changeloglib_new_file_wrapper($this->file_v2->file, array())
        );
        $detector = new local_changeloglib_update_detector($new_files, context_system::instance()->id, -1, array());
        $mappings = $detector->map_backups()->mappings;

        // Check predecessor to exist and be correct.
        $this->assertTrue(local_changeloglib_test_helper::correct_predecessor($mappings, $new_files,
            array(null, $this->file->file)));
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
