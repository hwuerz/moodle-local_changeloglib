Changeloglib Plugin for Moodle
==============================

This plugin provides helper methods to handle the backup process of files which are required
for a changelog generation. 
This plugin will analyse the files and detect changes. 
Currently all file types are supported for backup and PDF documents can be used for difference
detection. 

Without any other plugins this will not do anything.

License
-------

    Copyright (c) 2017 Hendrik Wuerz

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

Requirements
------------
* Tested with Moodle 3.3+

Installation
--------

1. Clone this repository
2. Copy plugin to `MOODLE_HOME/local/changeloglib`
3. Browse to Site Administration -> Notifications and allow the database upgrades to execute
