<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backup instructions for the quizaccess_sebreview plugin.
 *
 * @package    quizaccess_sebreview
 * @category   backup
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Stores the setting of the quizaccess_sebreview plugin in the backup of a quiz.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_sebreview_subplugin extends backup_mod_quiz_access_subplugin {
    /**
     * Describe how the setting for a particular quiz is stored in the backup.
     *
     * @return backup_subplugin_element
     */
    protected function define_quiz_subplugin_structure() {
        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginsettings = new backup_nested_element('quizaccess_sebreview', null, ['reviewinseb']);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginsettings);

        // Set source to populate the data.
        $subpluginsettings->set_source_table('quizaccess_sebreview', ['quizid' => backup::VAR_ACTIVITYID]);

        return $subplugin;
    }
}
