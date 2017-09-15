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
 * Provides helper functions which are required by multiple tests.
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

}
