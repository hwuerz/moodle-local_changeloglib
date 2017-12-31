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
require_once(dirname(__FILE__) . '/../classes/pdftotext.php');

/**
 * Class local_changeloglib_test_helper.
 *
 * Provides helper functions which are required by multiple tests.
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_test_helper {

    /**
     * Creates a new stored file, based on the documents in the tests/res/ subdirectory.
     * @param int $contextid The ID of the context of the new file.
     * @param string $filename The name of the file. Must ne equal to the filename in tests/res.
     * @param string $component The component under which the file should be created.
     * @param string $filearea The file area under which the file should be created.
     * @param int $itemid The item ID under which the file should be created.
     * @param string $filepath The filepath of the file.
     * @return stored_file The file instance of moodle.
     */
    public static function create_file($contextid, $filename = 'file.pdf', $component = 'mod_resource', $filearea = 'content',
                                       $itemid = 0, $filepath = '/') {

        $fs = get_file_storage();
        $file_info = array(
            'contextid' => $contextid,
            'filename' => $filename,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => $filepath,
            'timecreated' => time(),
            'timemodified' => time());
        // Delete auto generated file.
        $fs->delete_area_files($file_info['contextid'], $file_info['component'], $file_info['filearea']);
        // Create own files.
        $file = $fs->create_file_from_pathname($file_info, dirname(__FILE__) . '/res/' . $filename);

        return $file;
    }

    /**
     * Backups the passed file.
     * @param stored_file $file The file to be stored.
     * @param array $data The data of the backup.
     * @param int $context_id_to The destination context.
     * @param int $scope_id The scope.
     */
    public static function backup($file, $data = array(), $context_id_to = -1, $scope_id = -1) {
        if ($context_id_to < 0) {
            $context_id_to = context_system::instance()->id;
        }
        local_changeloglib_backup_lib::backup($data,
            $file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(),
            $context_id_to, $scope_id);
    }

    /**
     * Checks whether the passed mappings contain a predecessor for a new_file.
     * @param local_changeloglib_update_detector_mapping[] $mappings The mappings from the update_detector.
     * @param local_changeloglib_new_file_wrapper[] $new_files The new files which are analysed in the mappings.
     * @param stored_file[] $predecessors The expected predecessors or null as an entry if none is expected.
     * @return bool Whether the passed new_files have a predecessor (false) or not (true).
     */
    public static function correct_predecessor($mappings, $new_files, $predecessors) {
        if (count($mappings) != count($new_files)) {
            return 'Dimension of $mappings and $new_files do not match';
        }
        if (count($predecessors) != count($new_files)) {
            return 'Dimension of $predecessors and $new_files do not match';
        }
        foreach ($mappings as $mapping) {

            // Find the correct original file and expected predecessor.
            $file = null;
            $predecessor = null;
            $hash = $mapping->file_wrapper->get_file()->get_contenthash();
            foreach ($new_files as $file_idx => $current_file) {
                if ($hash == $current_file->get_file()->get_contenthash()) {
                    $file = $current_file;
                    $predecessor = $predecessors[$file_idx];
                    break;
                }
            }

            if ($file == null) {
                return 'The mapping file ' . $mapping->file_wrapper->get_file()->get_filename()
                    . ' is not included in the $new_files array.';
            }
            if (is_null($mapping->predecessor) && is_null($predecessor)) {
                continue; // No predecessor expected an no found --> this mapping is ok.
            }
            if (is_null($mapping->predecessor)) {
                return 'Mapping was null and a predecessor was expected. Expected: ' . $predecessor->get_filename();
            }
            if (is_null($predecessor)) {
                return 'The expected predecessor was null and a mapping was found. Mapping: '
                    . $mapping->predecessor->get_backup()->get_file()->get_filename();
            }
            if ($mapping->predecessor->get_backup()->get_file()->get_contenthash() !== $predecessor->get_contenthash()) {
                return 'The mapping does not handle the expected predecessor. Found '
                    . $mapping->predecessor->get_backup()->get_file()->get_filename() . ' Expected '
                    . $predecessors[$idx]->get_filename();
            }
        }
        return true; // No errors found in any mapping.
    }

    /**
     * Checks whether the passed mappings contain a predecessor for a new_file.
     * @param local_changeloglib_update_detector_mapping[] $mappings The mappings from the update_detector.
     * @param local_changeloglib_new_file_wrapper[] $new_files The new files which are analysed in the mappings.
     * @return bool Whether the passed new_files have a predecessor (false) or not (true).
     */
    public static function no_predecessor($mappings, $new_files) {
        $predecessors = array(); // All predecessors have to be null --> Build array with correct size.
        foreach ($new_files as $new_file) {
            $predecessors[] = null;
        }
        return self::correct_predecessor($mappings, $new_files, $predecessors);
    }
}
