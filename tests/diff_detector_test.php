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
require_once(dirname(__FILE__) . '/../classes/diff_detector.php');

/**
 * Class local_changeloglib_diff_detector_test.
 * vendor/bin/phpunit local_changeloglib_diff_detector_test local/changeloglib/tests/diff_detector_test.php
 * Checks the detection of changed pages and decision whether there are too many changes for a valid predecessor.
 *
 * @group local_changeloglib
 */
class local_changeloglib_diff_detector_test extends advanced_testcase {

    /**
     * Checks for correct detected pages.
     */
    public function test_diff_detection() {
        $this->resetAfterTest(true);

        // Setup detector.
        $contextid = context_system::instance()->id;
        $first_file = local_changeloglib_test_helper::create_file($contextid, 'file.txt')->copy_content_to_temp();
        $second_file = local_changeloglib_test_helper::create_file($contextid, 'file_v2.txt')->copy_content_to_temp();
        $detector = new local_changeloglib_diff_detector($first_file, $second_file);

        // Page two was changed, page four was added.
        $this->assertEquals('2, 4', $detector->get_info());
        $this->assertTrue($detector->has_acceptable_amount_of_changes());

        // Clean up.
        unlink($first_file);
        unlink($second_file);
    }

    /**
     * Checks has_acceptable_amount_of_changes with two complete different documents.
     */
    public function test_acceptable_amount_of_changes() {
        $this->resetAfterTest(true);

        // Setup detector.
        $contextid = context_system::instance()->id;
        $first_file = local_changeloglib_test_helper::create_file($contextid, 'file.txt')->copy_content_to_temp();
        $second_file = local_changeloglib_test_helper::create_file($contextid, 'other.txt')->copy_content_to_temp();
        $detector = new local_changeloglib_diff_detector($first_file, $second_file);

        // Page two was changed, page four was added.
        $this->assertEquals('1, 2, 3, 4, 5, 6, 7', $detector->get_info());
        $this->assertFalse($detector->has_acceptable_amount_of_changes());

        // Clean up.
        unlink($first_file);
        unlink($second_file);
    }
}
