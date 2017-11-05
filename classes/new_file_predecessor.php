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
