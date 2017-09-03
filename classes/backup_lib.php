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
 * Backup Lib.
 *
 * A library to manage backups of moodle files.
 *
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_backup_lib {

    /**
     * The name of the database table where all backups are managed.
     */
    const BACKUP_TABLE = 'local_changeloglib_backup';

    /**
     * The used filearea for backups
     */
    const BACKUP_FILEAREA = 'local_changeloglib_backup';

    /**
     * The component which creates the backups. This is the plugin name.
     */
    const BACKUP_COMPONENT = LOCAL_CHANGELOGLIB_NAME;

    /**
     * Creates a new backup based on the passed parameters.
     * @param array $data Additional data which is used to identify a definite predecessor.
     * @param int $context_id_from The context ID of the file which should be backuped
     * @param string $component_from The component of the file which should be backuped
     * @param string $filearea_from The filearea of the file which should be backuped
     * @param int $itemid_from The itemid of the file which should be backuped
     * @param int $context_id_to The new context under which the backup should be stored
     * @param int $scope_id The scope within the context. (For resources this is the section within the course)
     */
    public static function backup(array $data,
                           $context_id_from, $component_from, $filearea_from, $itemid_from = 0,
                           $context_id_to, $scope_id) {
        global $DB;
        $fs = get_file_storage();

        // Get the file which will be deleted right now.
        $area_files = $fs->get_area_files(
            $context_id_from,
            $component_from,
            $filearea_from,
            $itemid_from,
            'sortorder DESC, id ASC',
            false);

        foreach ($area_files as $file) { // Iterate all files found.

            // Store a reference for this file in the plugin table.
            $id = $DB->insert_record(self::BACKUP_TABLE, (object)array(
                'context' => $context_id_to,
                'scope' => $scope_id,
                'data' => json_encode($data),
                'timestamp' => time()
            ), true);

            // Create a copy of the file and store is under the given ID.
            $file_info = array(
                'contextid' => $context_id_to,
                'component' => self::BACKUP_COMPONENT,
                'filearea' => self::BACKUP_FILEAREA,
                'itemid' => $id);
            try {
                $fs->create_file_from_storedfile($file_info, $file);
            } catch (Exception $exception) { // Unknown error --> rollback.
                $DB->delete_records(self::BACKUP_TABLE, array('id' => $id));
            }

        }
    }

    /**
     * Deletes all backup files which are older than one hour.
     * This method will be called by the task `clean_backup`.
     */
    public static function clean_up_old_files() {
        self::clean_up_files('timestamp < ' . (time() - 60 * 60));
    }

    /**
     * Deletes all backup files.
     * This method will be called when the plugin becomes uninstalled.
     */
    public static function clean_up_all_files() {
        self::clean_up_files('true');
    }

    /**
     * Deletes all backup files with the passed context and scope.
     * This method will be called to delete old submissions when new files are saved.
     * @param int $context The context whose backups should be deleted
     * @param int $scope The scope inside the context whose backups should be deleted.
     */
    public static function clean_up_selected($context, $scope) {
        self::clean_up_files('context = ? AND scope = ?', array($context, $scope));
    }

    /**
     * Deletes all backup files which fulfill the passed select query.
     * @param string $select The DB query to select the files which should be deleted
     * @param null $params The params to the select statement.
     */
    private static function clean_up_files($select, $params = null) {
        global $DB;

        // Get the references to the files.
        $records = $DB->get_records_select(self::BACKUP_TABLE, $select, $params);

        // Get the file instances for the records.
        foreach ($records as $record) {
            // Delete the file (The loop should only be iterated once).
            foreach (self::get_backup_files($record) as $file) {
                try {
                    $file->delete();
                } catch (Exception $exception) { // This file is not reachable for any reason.
                    continue;
                }
            }
        }

        // Delete the reference in the database.
        $DB->delete_records_select(self::BACKUP_TABLE, $select, $params);
    }

    /**
     * Get the previously saved files.
     * @param object $backup The database backup record.
     * @return stored_file[] The file instances of the backup. Normally this should only be one.
     */
    private static function get_backup_files($backup) {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $backup->context,
            self::BACKUP_COMPONENT,
            self::BACKUP_FILEAREA,
            $backup->id,
            'sortorder DESC, id ASC',
            false);
        return $files;
    }

    /**
     * Get a previously saved file.
     * @param object $backup The database backup record.
     * @return stored_file|null The file instances of the backup or null if no file exists.
     */
    public static function get_backup_file($backup) {
        $files = self::get_backup_files($backup);
        return array_shift($files); // Get only the first file.
    }
}