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
 * Strings for the quizaccess_sebreview plugin.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['backtoquiz'] = 'Back to the quiz';
$string['error:sebnotrequired'] = 'This option can only be used if Safe Exam Browser is required for this quiz.';
$string['pluginname'] = 'Safe Exam Browser for quiz review';
$string['privacy:metadata'] = 'The plugin stores one setting per quiz and no personal data.';
$string['reviewinsebinfo'] = 'Finished attempts at this quiz can only be reviewed in Safe Exam Browser.';
$string['reviewinstructions'] = 'Open this quiz in Safe Exam Browser and go to the review from there.';
$string['reviewrequiresseb'] = 'The review of this quiz is only available in Safe Exam Browser.';
$string['sebnotconfigured'] = 'The review of this quiz is restricted to Safe Exam Browser, but Safe Exam Browser is not set up for this quiz. Please contact your teacher.';
$string['sebreviewrequired'] = 'Require Safe Exam Browser for review';
$string['sebreviewrequired_help'] = 'If enabled, students can review their finished attempts only in Safe Exam Browser. The same check applies as for the attempt itself. Without Safe Exam Browser, and in the Moodle app, they see a notice instead of the questions.

Teachers and other users who are allowed to view the quiz reports are not affected.

This option can only be used if Safe Exam Browser is required for the quiz. Unlike the other Safe Exam Browser settings, it can still be changed after the quiz has been attempted.';
