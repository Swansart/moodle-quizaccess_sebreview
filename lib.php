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
 * Callbacks of the quizaccess_sebreview plugin.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called before any web service function is executed.
 *
 * The Moodle app reads the review of an attempt through a web service, which does not
 * involve the quiz access rules. This stops that web service for quizzes whose review
 * is restricted to Safe Exam Browser.
 *
 * @param stdClass $function description of the web service function that is about to run.
 * @param array $params the validated parameters of the call.
 * @return bool always false, so that the function itself is executed if no exception is thrown.
 */
function quizaccess_sebreview_override_webservice_execution($function, $params) {
    \quizaccess_sebreview\review_guard::check_webservice($function, (array) $params);
    return false;
}
