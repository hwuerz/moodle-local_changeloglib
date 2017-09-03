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
require_once(dirname(__FILE__) . '/pdftotext.php');

/**
 * Checks for diffs in two strings.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_diff_detector {

    /**
     * @var array The file names of the two text documents.
     */
    private $file = array();

    /**
     * @var array The last line in the text files for each document for each page
     *      First dimension: The document index: Zero for the first file, one for the second
     *      Second dimension: The pages of the document
     *      Value: The last line of this document
     */
    private $page_end_at_line = array();

    /**
     * @var array The changes on each page
     *      First dimension: The document index: Zero for the original file, one for the updated
     *      Second dimension: The pages of the document
     *      Value: How many changes are in the defined document on the defined line
     */
    private $page_changes_counter;

    /**
     * @var array The number of available lines in the text documents
     */
    private $number_of_lines = array();

    /**
     * local_changeloglib_diff_detector constructor.
     * @param string $first_file The filename for the first text document.
     * @param string $second_file The filename for the second text document.
     */
    public function __construct($first_file, $second_file) {
        $this->file[0] = $first_file;
        $this->file[1] = $second_file;
        $this->generate_page_index(0);
        $this->generate_page_index(1);
        $this->calculate_changes();
    }

    /**
     * Get a string with all pages containing changes, separated by comma.
     * @return string A string which can be printed to the user.
     */
    public function get_info() {
        $diff_output = '';
        $add_comma = false;
        foreach ($this->page_changes_counter[1] as $page => $amount) {
            if ($amount > 0) { // On this page there are changes.
                if ($add_comma) {
                    $diff_output .= ', ';
                }
                $add_comma = true;
                $diff_output .= ($page + 1); // Add +1 to map the index (starting with zero) to a page number (starting with one).
            }
        }
        return $diff_output;
    }

    /**
     * Checks whether there are more changes than allowed for a predecessor.
     * @return bool Whether the amount of changes allows this file as a predecessor.
     */
    public function has_acceptable_amount_of_changes() {
        $changes = 0; // How many changes were detected in the document (line based).
        $changed_pages = 0; // How many pages were changed.
        $total_pages = 0; // How many pages do exist in the document.
        foreach ($this->page_changes_counter[1] as $page => $amount) {
            $changes += $amount;
            $total_pages++;
            if ($amount > 0) {
                $changed_pages++;
            }
        }

        // Get the ratio of changed LINES compared to the total amount of lines.
        $ratio = $changes / ($this->number_of_lines[1] + 1); // +1 to avoid division through zero.
        if ($ratio > 0.5) { // Too many lines have been changed --> This was not a real predecessor.
            return false;
        }

        // Get the ratio of changed PAGES compared to the total amount of pages.
        $ratio = $changed_pages / ($total_pages + 1); // +1 to avoid division through zero.
        if ($ratio > 0.5) { // Too many lines have been changed --> This was not a real predecessor.
            return false;
        }

        return true;
    }

    /**
     * Get an overview over the amount of changes on each page.
     * Fills the page_changes_counter array
     */
    private function calculate_changes() {
        $diff = $this->run_command_line_diff();

        // Init page change counter.
        $this->page_changes_counter = array(array(), array());
        for ($document = 0; $document < 2; $document++) { // Loop the two files.
            // Loop pages
            // <= because last page has not a page break and therefor no entry in the page_end_at_line array.
            for ($page = 0; $page <= count($this->page_end_at_line[$document]); $page++) {
                $this->page_changes_counter[$document][$page] = 0;
            }
        }

        // Count changes on each page
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $diff) as $line) { // Iterate lines in diff output.
            $line_data = $this->analyse_line($line); // Extract information from this diff line.
            if ($line_data === false) { // This is an invalid line (empty, detail information or something like this).
                continue;
            }

            $affected_lines = $line_data['affected_lines']; // The lines from the two documents which are part of the changes.
            for ($document = 0; $document < 2; $document++) {
                foreach ($affected_lines[$document] as $line_number) { // Loop each changed line number in the documents.
                    // Increase change counter for the page of the document where this line came from.
                    $this->page_changes_counter[$document][$this->get_page_of_line($document, $line_number)]++;
                }
            }
        }
    }

    /**
     * Get the affected lines in the used files.
     * @param string $line The line to be analysed.
     *        Example '1,3c1' means that lines 1-3 in the first file have to be changed to get line 3 in the second file.
     * @return array|bool An array with status information if a valid line was passed, false otherwise.
     */
    private function analyse_line($line) {
        // Initialize array because optional groups ('?' in regex) would not generate an array entry if they are not present.
        $hits = array();
        $match = preg_match('/^(\d+)(,(\d+))?([acd])(\d+)(,(\d+))?$/m', $line, $hits);
        if (!$match) {
            return false;
        }
        return array(
            'operation' => $hits[4], // Contains 'a' for add, 'c' for change or 'd' for delete.
            'affected_lines' => array(
                $this->build_range(self::get($hits[1]), self::get($hits[3])),
                $this->build_range(self::get($hits[5]), self::get($hits[7]))
            )
        );
    }

    /**
     * Gets the passed value or the default if not defined.
     * Taken from https://stackoverflow.com/a/25205195
     * @param mixed $var The value which should be extracted.
     * @param mixed $default The value which should be returned if the requested element is not available.
     * @return mixed The requested value or the default if it is not available.
     */
    private static function get(&$var, $default = null) {
        return isset($var) ? $var : $default;
    }

    /**
     * Generates an array, containing all numbers between start (inclusive) and end (inclusive).
     * Handles empty strings as start and end.
     * @param int $start The start of the range (inclusive).
     * @param int $end The end of the range (inclusive).
     * @return array All numbers between start and end.
     */
    private function build_range($start, $end) {
        $range = array();
        if ($start != '' && $start != null) { // A start of the range is known. This should always be given.
            $range[] = $start;
            if ($end != '') { // Also an end is given. This might be happen.
                for ($i = $start + 1; $i <= $end; $i++) { // Add elements until end.
                    $range[] = $i;
                }
            }
        }
        return $range;
    }

    /**
     * Generates a new_page_at_line entry for the passed document index
     * @param int $document The index of the file in the file array
     */
    private function generate_page_index($document) {
        $this->page_end_at_line[$document] = array(); // Each element contains the line where a new page starts.
        $this->number_of_lines[$document] = 0;
        $handle = fopen($this->file[$document], "r");
        if ($handle) { // File could be opened.
            $current_line_number = 0;
            while (($line = fgets($handle)) !== false) { // Iterate lines.
                // Process the line read.
                if (strpos($line, "\f") !== false) { // A new page starts.
                    $this->page_end_at_line[$document][] = $current_line_number;
                }
                $current_line_number++;
            }
            fclose($handle);
            $this->number_of_lines[$document] = $current_line_number;
        }
    }

    /**
     * Get the page of the passed line in the passed document.
     * @param int $document The document index in the file array
     * @param int $line The line from the converted PDF which should be mapped to the page number
     * @return int The page number from the PDF where the passed line was extracted
     */
    private function get_page_of_line($document, $line) {
        // Iterate the pages.
        for ($page = 0; $page < count($this->page_end_at_line[$document]); $page++) {
            if ($this->page_end_at_line[$document][$page] > $line) { // Does this page ends after the passed line?.
                return $page;
            }
        }

        // The line must be a part of the last page because no fitting ending was found until now.
        return count($this->page_end_at_line[$document]);
    }

    /**
     * Get the file diff.
     * @return string The diff of the two files in the file array
     */
    private function run_command_line_diff() {
        // Parameter -w to ignore whitespaces; -B to ignore blank lines.
        $cmd = "diff -w -B " . $this->file[0] . " " . $this->file[1] . " 2>&1";
        return shell_exec($cmd);
    }
}
