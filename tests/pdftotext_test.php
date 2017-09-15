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
require_once(dirname(__FILE__) . '/../classes/pdftotext.php');

/**
 * Class local_changeloglib_pdftotext_test.
 * vendor/bin/phpunit local_changeloglib_pdftotext_test local/changeloglib/tests/pdftotext_test.php
 *
 * Tries to convert a pdf file to a text file. If possible -> check for correct result
 *
 * @group local_changeloglib
 */
class local_changeloglib_pdftotext_test extends advanced_testcase {

    /**
     * Checks that the converted file is correct (only if pfttotext is installed on the system).
     */
    public function test_conversion_to_text() {
        $this->resetAfterTest(true);

        $file = local_changeloglib_test_helper::create_file(context_system::instance()->id);
        $text_file = local_changeloglib_pdftotext::convert_to_txt($file);

        // If pdftotext is not installed, the response must be false.
        if (!local_changeloglib_pdftotext::is_installed()) {
            $this->assertFalse($text_file);
            return; // No more tests possible.
        }

        // The content must be correct.
        $expected_text = file_get_contents(dirname(__FILE__) . '/res/file.txt');
        $converted_text = file_get_contents($text_file);
        $this->assertEquals($expected_text, $converted_text);

        // Clean up.
        unlink($text_file);
    }

    /**
     * Checks that the converted file is correct (only if pfttotext is installed on the system).
     */
    public function test_no_pdf_convert() {
        $this->resetAfterTest(true);

        $file = local_changeloglib_test_helper::create_file(context_system::instance()->id, 'file.txt');
        $text_file = local_changeloglib_pdftotext::convert_to_txt($file);

        // This is not a pdf file --> The response must be false.
        $this->assertFalse($text_file);
    }
}
