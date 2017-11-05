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
require_once(dirname(__FILE__) . '/backup_wrapper.php');
require_once(dirname(__FILE__) . '/new_file_predecessor.php');

/**
 * A wrapper around a new file for which a predecessor should be found.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_new_file_wrapper {

    /**
     * @var stored_file $file The new file whose predecessor should be found.
     */
    private $file;

    /**
     * @var array $data The data array of the new file with constraints for a definite predecessor.
     */
    private $data;

    /**
     * @var local_changeloglib_new_file_predecessor[] The candidates for a predecessor for this file.
     */
    private $predecessor_candidates;

    /**
     * local_changeloglib_new_file_wrapper constructor.
     * @param stored_file $file The new file whose predecessor should be found.
     * @param array $data The data array of the new file with constraints for a definite predecessor.
     */
    public function __construct(stored_file $file, array $data) {
        $this->file = $file;
        $this->data = $data;
        $this->predecessor_candidates = array();
    }

    /**
     * @param local_changeloglib_backup_wrapper[] $backups All available backups which could be a predecessor for this file.
     * @param boolean $ensure_mime_type Whether MIME Types should be ensured (true) or just be a similarity factor (false).
     * @param float $min_similarity The minimum similarity a predecessor must have in the range [0, 1].
     */
    public function check_candidates($backups, $ensure_mime_type, $min_similarity) {

        foreach ($backups as $candidate) {

            // The types of the files must match.
            $fitting_mime_type = $this->file->get_mimetype() == $candidate->get_file()->get_mimetype();

            // The MIME types do not match and this detector should ensure, that they do.
            if ($ensure_mime_type && !$fitting_mime_type) {
                continue;
            }

            // The similarity in the range [0, 1].
            $similarity = $this->calculate_meta_similarity($candidate->get_file());

            // Check for soft handling of MIME-Types
            if (!$ensure_mime_type) { // This detector should not ensure the MIME-Types...
                if ($fitting_mime_type) { // ... but they fit --> Increase the similarity.
                    $similarity += 1;
                }
                // The similarity should be in the range [0, 1] again. If the detector does not ensure MIME Types
                // and the types do not match, the similarity will decrease.
                $similarity /= 2;
            }

            // Check min similarity.
            if ($similarity < $min_similarity) {
                continue;
            }

            // Add the predecessor candidate to this file.
            $predecessor_candidate = new local_changeloglib_new_file_predecessor($candidate, $similarity);
            $this->predecessor_candidates[] = $predecessor_candidate;
        }
    }

    /**
     * @return stored_file The new file whose predecessor should be found.
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * @return array The data array of the new file with constraints for a definite predecessor.
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @return local_changeloglib_new_file_predecessor[] The candidates for a predecessor for this file.
     */
    public function get_predecessor_candidates() {
        return $this->predecessor_candidates;
    }

    /**
     * Calculates the similarity to the passed file based on meta information.
     * This means the content will not become analysed.
     * @param stored_file $candidate An candidate which might be a predecessor of this file.
     * @return float The similarity of the two files based on meta information. Value in range [0,1]
     */
    private function calculate_meta_similarity(stored_file $candidate) {

        $key_weight = 0;
        $key_similarity = 1;
        $factors = array();

        // How similar are the file names.
        $filename = self::levenshtein_realtive($this->file->get_filename(), $candidate->get_filename());
        $factors[] = array($key_weight => 1, $key_similarity => $filename);

        // How similar is the file size.
        $filesize = self::number_similarity_realtive($this->file->get_filesize(), $candidate->get_filesize());
        $factors[] = array($key_weight => 1, $key_similarity => $filesize);

        // How many minutes ago the candidate was deleted
        // Until one minute (= 60 sec) the similarity will not decrease.
        $deletion_time = 1 / (1 + 0.01 * ( time() - $candidate->get_timemodified()));
        $factors[] = array($key_weight => 0.5, $key_similarity => $deletion_time);

        // Sum up all factors with their weights.
        $weight_sum = 0;
        $similarity_sum = 0;
        foreach ($factors as $factor) {
            $weight_sum += $factor[$key_weight];
            $similarity_sum += $factor[$key_weight] * $factor[$key_similarity];
        }
        return $similarity_sum / $weight_sum;
    }

    /**
     * Wrapper around levenshtein which calculates the operations relative to the string length
     * @see levenshtein
     * @param string $str1 The first string
     * @param string $str2 The second string
     * @return float The operations relative to the string length.
     */
    private static function levenshtein_realtive($str1, $str2) {
        return 1 - levenshtein($str1, $str2) / max(strlen($str1), strlen($str2));
    }

    /**
     * Calculates the similarits between the two numbers based on the relative difference between them
     * @param int $val1 The first number
     * @param int $val2 The second number
     * @return float The similarity of the passed numbers
     */
    private static function number_similarity_realtive($val1, $val2) {
        return 1 - abs($val1 - $val2) / max($val1, $val2);
    }

}
