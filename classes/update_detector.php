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
require_once(dirname(__FILE__) . '/backup_lib.php');

/**
 * Checks whether one file is an update of another.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_update_detector {

    /**
     * The best distribution of the new files and the backups.
     * Only set if map_backups() was called.
     * @var local_changeloglib_update_detector_distribution
     */
    private $distribution;

    /**
     * The new files whose predecessors should be found.
     * @var local_changeloglib_new_file_wrapper[]
     */
    private $new_files;

    /**
     * The moodle context in which the new file and the predecessor are stored.
     * @var int
     */
    private $context;

    /**
     * The scope within the context which must match between the new file and the predecessor.
     * This might be the course section for resources.
     * @var int
     */
    private $scope;

    /**
     * @var array All records in the backup table which are relevant as a predecessor.
     */
    private $backups;

    /**
     * Whether this detector should ensure that the MIME type of the found predecessor matches
     * the MIME type of the new file.
     * If this value is true, all candidates with not matching type will be skipped.
     * If this value is false, the MIME Type will be handled as a similarity property.
     * @var bool
     */
    private $ensure_mime_type = true;

    /**
     * The minimum similarity a predecessor must have.
     * If the similarity is below, the file will be rejected as a predecessor.
     * Adjust this value to define a level of similarity you want to ensure.
     * @var float
     */
    private $min_similarity = 0.5;

    /**
     * local_changeloglib_update_detector constructor.
     * @param local_changeloglib_new_file_wrapper[] $new_files The new files whose predecessors should be found.
     * @param int $context The context of the file and its predecessor. (Resources: The course context)
     * @param int $scope The scope of the file and its predecessor within the context. (In courses: the section)
     * @param stored_file[] $further_candidates All other files which should be checked as predecessor.
     *                                          Use this for deletion in progress.
     */
    public function __construct(array $new_files, $context, $scope, array $further_candidates) {
        $this->new_files = $new_files;
        $this->context = $context;
        $this->scope = $scope;

        $this->backups = $this->get_backups($further_candidates);
    }

    /**
     * Maps the backups to a new file.
     * @return local_changeloglib_update_detector_distribution The best distribution of backups for the new files.
     */
    public function map_backups() {
        $this->calculate_similarity(); // All $this->new_files have predecessor_candidates.
        return $this->map_backups_rec();
    }


    /**
     * Maps the backups to a new file.
     * @return local_changeloglib_update_detector_distribution The best distribution of backups for the new files.
     */
    private function map_backups_rec() {

        // Get the first element in the new files array.
        $current_new_file = null;
        $current_index = -1;
        foreach ($this->new_files as $key => $new_file) { // Get first element via foreach to get the key as well.
            $current_new_file = $new_file;
            $current_index = $key;
            break;
        }

        // Terminate as soon as no files are left.
        if ($current_new_file == null) {
            return new local_changeloglib_update_detector_distribution(array(), 0);
        }

        // Remove this file from the array to avoid access in recursion.
        unset($this->new_files[$current_index]);

        // First option: This file is not an update and has therefore no backup.
        // --> Directly make recursive call.
        $best_distribution = $this->map_backups_rec();
        $empty_mapping = new local_changeloglib_update_detector_mapping($current_new_file, null);
        $best_distribution->add_mapping($empty_mapping, 0);

        // Second option: This file is an update.
        // --> Test all combinations recursively.
        foreach ($current_new_file->get_predecessor_candidates() as $predecessor_candidate) {

            // Check whether this backup was already used by another new file.
            if ($predecessor_candidate->get_backup()->is_used()) {
                continue;
            }

            // Block the usage of this backup for other files.
            $predecessor_candidate->get_backup()->set_is_used(true);

            // Call function recursively to get the best distribution of the files left.
            $recursive_distribution = $this->map_backups_rec();

            // Calculate the mapping and similarity for 'This file' <-> 'This Backup'.
            $current_mapping = new local_changeloglib_update_detector_mapping($current_new_file, $predecessor_candidate);
            $current_similarity = $predecessor_candidate->get_similarity();
            // Add this mapping to the recursive distribution --> Allows comparision with best_distribution.
            $recursive_distribution->add_mapping($current_mapping, $current_similarity);

            // Check whether this combination leads to a better similarity.
            if ($recursive_distribution->is_better_than($best_distribution)) {
                $best_distribution = $recursive_distribution;
            }

            // The backup can be used again in another recursive path.
            $predecessor_candidate->get_backup()->set_is_used(false);
        }

        // Add this file again to the array so it can be accessed later.
        $this->new_files[$current_index] = $current_new_file;

        // Return the best distribution.
        // This can contain a mapping for the current file or not respectively what is better.
        $this->distribution = $best_distribution;
        return $best_distribution;
    }

    /**
     * Whether this detector should ensure that the MIME type of the found predecessor matches
     * the MIME type of the new file.
     * If this value is true, all candidates with not matching type will be skipped.
     * If this value is false, the MIME Type will be handled as a similarity property.
     * Default value is true.
     * @param bool $ensure_mime_type Whether the MIME type of the candidates must match or not.
     */
    public function set_ensure_mime_type($ensure_mime_type) {
        $this->ensure_mime_type = $ensure_mime_type;
    }

    /**
     * If the similarity is below this value, a file will be rejected as a predecessor.
     * Adjust this value to define a level of similarity you want to ensure.
     * Default value is 0.5
     * @param float $min_similarity The minimum similarity a predecessor must have in the range [0, 1].
     */
    public function set_min_similarity($min_similarity) {
        $this->min_similarity = $min_similarity;
    }

    /**
     * Get all backups stored with the same context and scope.
     * @param stored_file[] $further_candidates All other files which are available and not in a backup.
     * @return local_changeloglib_backup_wrapper[] All available backups.
     */
    private function get_backups($further_candidates) {
        global $DB;

        $records = $DB->get_records(local_changeloglib_backup_lib::BACKUP_TABLE, array(
            'context' => $this->context,
            'scope' => $this->scope
        ), 'timestamp DESC');

        // Get the stored_file objects for all records.
        $backups = array();
        foreach ($records as $record) {
            $file = local_changeloglib_backup_lib::get_backup_file($record);
            $backups[] = new local_changeloglib_backup_wrapper($record, $file);
        }

        // Add the further candidates.
        foreach ($further_candidates as $further_candidate) {
            $backups[] = new local_changeloglib_backup_wrapper(null, $further_candidate);
        }

        return $backups;
    }

    /**
     * Calculate the similarity for all new files to all available backups.
     */
    private function calculate_similarity() {
        foreach ($this->new_files as $new_file) { // Iterate all new files and ...
            // ... check the similarity to all backups.
            $new_file->check_candidates($this->backups, $this->ensure_mime_type, $this->min_similarity);
        }
    }
}


/**
 * Class local_changeloglib_update_detector_distribution.
 *
 * This is a data class to manage all mappings of new files to their predecessors and the resulting similarity.
 * Use this if you have a list of mappings.
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_update_detector_distribution {

    /**
     * @var local_changeloglib_update_detector_mapping[] $mappings All given mappings.
     */
    public $mappings;

    /**
     * @var float $similarity The total similarity if all mappings would be done as stored.
     */
    public $similarity;

    /**
     * This is a data class to manage all mappings of new files to their predecessors and the resulting similarity.
     * Use this if you have a list of mappings.
     * @param local_changeloglib_update_detector_mapping[] $mappings All given mappings.
     * @param float $similarity The total similarity if all mappings would be done as stored.
     */
    public function __construct(array $mappings, $similarity) {
        $this->mappings = $mappings;
        $this->similarity = $similarity;
    }

    /**
     * Adds a new mapping to the distribution.
     * @param local_changeloglib_update_detector_mapping $mapping The new mapping.
     * @param float $similarity The similarity of the new mapping.
     */
    public function add_mapping(local_changeloglib_update_detector_mapping $mapping, $similarity) {
        array_push($this->mappings, $mapping);
        $this->similarity += $similarity;
    }

    /**
     * Compares this distribution with another one. Checks whether this one is better than the passed one.
     * @param local_changeloglib_update_detector_distribution $other The distribution which should be compared.
     * @return bool Whether this distribution is better than the passed one.
     */
    public function is_better_than($other) {
        return $this->similarity > $other->similarity;
    }
}


/**
 * Class local_changeloglib_update_detector_mapping.
 *
 * This is a data class to manage the mapping of a new file to a selected predecessor.
 * Use this if you have found the correct predecessor for a new file.
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_update_detector_mapping {

    /**
     * @var local_changeloglib_new_file_wrapper $file_wrapper
     */
    public $file_wrapper;

    /**
     * @var local_changeloglib_new_file_predecessor|null $predecessor
     */
    public $predecessor;

    /**
     * This is a data class to manage the mapping of a new file to a selected predecessor.
     * Use this if you have found the correct predecessor for a new file.
     * @param local_changeloglib_new_file_wrapper $file_wrapper The new file for which a predecessor search was performed.
     * @param local_changeloglib_new_file_predecessor|null $predecessor The selected predecessor for the file.
     *                                                                  Null if the file has none.
     */
    public function __construct($file_wrapper, $predecessor) {
        $this->file_wrapper = $file_wrapper;
        $this->predecessor = $predecessor;
    }

    /**
     * Deletes the predecessor from the backup table and file system.
     * This avoids a reuse of the backup.
     */
    public function delete_found_predecessor() {
        if ($this->predecessor) {
            local_changeloglib_backup_lib::clean_up_id($this->predecessor->get_backup()->get_file()->get_itemid());
            return true;
        }
        return false;
    }

    /**
     * Checks whether the predecessor is another file than the new one.
     * @return bool Whether this mapping indicates a changed file.
     */
    public function has_changed() {
        if ($this->predecessor == null) {
            return true; // This file does not have a predecessor. So it is definitely a change.
        }
        // This is a real change if the predecessor is not the same file as the new one.
        return
            $this->file_wrapper->get_file()->get_contenthash() !== $this->predecessor->get_backup()->get_file()->get_contenthash();
    }
}


/**
 * A wrapper around a backup. Has access to the database record and the stored_file.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_backup_wrapper {

    /**
     * @var stdClass|null $record The database record of the backup. Null if no record exists (further candidate).
     */
    private $record;

    /**
     * @var stored_file $file The stored file for this backup.
     */
    private $file;

    /**
     * @var boolean $is_used Whether this backup is already used as a predecessor by a file.
     */
    private $is_used;

    /**
     * local_changeloglib_backup_wrapper constructor.
     * @param stdClass|null $record The database record of the backup. Null if none exists.
     * @param stored_file $file The stored file for this backup.
     */
    public function __construct($record, stored_file $file) {
        $this->record = $record;
        $this->file = $file;
    }

    /**
     * @return null|stdClass The database record of the backup. Null if no record exists (further candidate).
     */
    public function get_record() {
        return $this->record;
    }

    /**
     * @return stored_file The stored file for this backup.
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * @return boolean Whether this backup is used as a predecessor by a file or not.
     */
    public function is_used() {
        return $this->is_used;
    }

    /**
     * @param boolean $is_used Whether this backup is used as a predecessor by a file or not.
     */
    public function set_is_used($is_used) {
        $this->is_used = $is_used;
    }
}


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

        $require_definite_predecessor = $this->has_definite_predecessors($backups);

        foreach ($backups as $candidate) {

            // Only allow definite predecessors if they are required.
            if ($require_definite_predecessor) {
                if (!self::is_data_equal($candidate->get_record()->data, $this->data)) {
                    continue; // This is not a definite predecessor --> skip the candidate.
                }
            }

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
     * Checks whether the backups contain at least one definite predecessor.
     * @param local_changeloglib_backup_wrapper[] $backups All available backups which could be a predecessor for this file.
     * @return bool True if there is at least one definite predecessor, false otherwise.
     */
    private function has_definite_predecessors($backups) {
        foreach ($backups as $backup) {
            // Check whether this backup is a definite predecessor.
            if ($backup->get_record() != null &&
                self::is_data_equal($backup->get_record()->data, $this->data)) {
                return true;
            }
        }
        // No definite predecessors found in all candidates.
        return false;
    }

    /**
     * Checks whether two data objects are equal.
     * @param string $data1 The first data object as a JSON String.
     * @param array $data2 The second data object as an array.
     * @return bool Whether the two data objects are equal.
     */
    private static function is_data_equal($data1, $data2) {
        // Extract the data object from the string.
        $data = json_decode($data1, true);

        $is_equal = true;
        foreach ($data2 as $key => $value) { // Check each data attribute of the current file.
            if ($data[$key] != $value) { // If only one is not equal...
                $is_equal = false; // ... the two objects are not equal.
                break;
            }
        }

        return $is_equal;
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


/**
 * A wrapper around the calculation results for the search of a predecessor.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_new_file_predecessor {

    /**
     * @var local_changeloglib_backup_wrapper $backup The backup which is analysed in this object.
     */
    private $backup;

    /**
     * @var float $similarity The calculated similarity.
     */
    private $similarity;

    /**
     * local_changeloglib_new_file_wrapper constructor.
     * @param local_changeloglib_backup_wrapper $backup The backup which is analysed in this object.
     * @param float $similarity The calculated similarity.
     */
    public function __construct($backup, $similarity) {
        $this->backup = $backup;
        $this->similarity = $similarity;
    }

    /**
     * @return local_changeloglib_backup_wrapper The backup which is analysed in this object.
     */
    public function get_backup() {
        return $this->backup;
    }

    /**
     * @return float The calculated similarity.
     */
    public function get_similarity() {
        return $this->similarity;
    }
}
