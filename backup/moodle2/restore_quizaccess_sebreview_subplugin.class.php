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
 * Restore instructions for the quizaccess_sebreview plugin.
 *
 * @package    quizaccess_sebreview
 * @category   backup
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Restores the setting of the quizaccess_sebreview plugin from the backup of a quiz.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_sebreview_subplugin extends restore_mod_quiz_access_subplugin {
    /**
     * Describe the XML paths that store the setting for a particular quiz.
     *
     * @return restore_path_element[]
     */
    protected function define_quiz_subplugin_structure() {
        $paths = [];

        $elementname = $this->get_namefor('');
        $elementpath = $this->get_pathfor('/quizaccess_sebreview');
        $paths[] = new restore_path_element($elementname, $elementpath);

        return $paths;
    }

    /**
     * Process the restored setting.
     *
     * @param array|stdClass $data data for the quizaccess_sebreview table from the backup.
     */
    public function process_quizaccess_sebreview($data) {
        $data = (object) $data;
        $quizid = $this->get_new_parentid('quiz');
        if (empty($quizid)) {
            return;
        }
        \quizaccess_sebreview\review_guard::set_required((int) $quizid, !empty($data->reviewinseb));
    }
}
