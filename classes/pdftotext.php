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

/**
 * Wrapper to access command line tool pdftotext.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_pdftotext {

    /**
     * Checks whether the command line tool pdftotext is installed. This tool is required to convert a pdf file to a
     * text document for diff detection.
     * @return bool Whether the required tool is installed or not.
     */
    public static function is_installed() {
        $output = shell_exec('dpkg -s poppler-utils 2>&1');
        if (strpos($output, 'pdf') !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Tries to convert the passed file in a text document.
     * Convertion is only possible if pdftotext is available and a PDF document was passed.
     * @param stored_file $file The file which should be converted.
     * @return bool|string Returns false if file could not be converted or a string with the path of the generated text file.
     */
    public static function convert_to_txt(stored_file $file) {

        // The linux tool poppler-utils must be installed.
        if (!self::is_installed()) {
            return false;
        }

        // The file must be a PDF file.
        if ($file->get_mimetype() != 'application/pdf') {
            return false;
        }

        $file_tmp = $file->copy_content_to_temp();
        $file_tmp_txt = $file_tmp . '_txt';

        shell_exec("pdftotext " . $file_tmp . " " . $file_tmp_txt . " 2>&1");

        unlink($file_tmp); // Remove PDF file copy.
        return $file_tmp_txt;
    }

}
