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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_sebreview\review_guard;

/**
 * Quiz access rule that restricts the review of finished attempts to Safe Exam Browser.
 *
 * The standard Safe Exam Browser rule only protects the attempt itself. With this rule
 * switched on for a quiz, students can open the review of their attempts only in a
 * Safe Exam Browser that passes the same check as for the attempt.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_sebreview extends access_rule_base {
    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from time limits.
     * @return self|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $PAGE;

        if (!review_guard::is_required_for_quiz($quizobj->get_quiz())) {
            return null;
        }

        // The page that shows a single question of a finished attempt creates the access
        // rules but never calls setup_attempt_page(), so that page has to be checked here.
        if (review_guard::is_page($PAGE, '/mod/quiz/reviewquestion.php')) {
            review_guard::enforce_on_page($quizobj, $PAGE);
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        // Attempts in progress are protected by the standard Safe Exam Browser rule.
        if (review_guard::is_page($page, '/mod/quiz/review.php')) {
            review_guard::enforce_on_page($this->quizobj, $page);
        }
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     *
     * @return string a message explaining the restriction.
     */
    public function description() {
        return get_string('reviewinsebinfo', 'quizaccess_sebreview');
    }

    /**
     * Add the setting to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $name = review_guard::SETTING;
        $element = $mform->createElement('advcheckbox', $name, get_string('sebreviewrequired', 'quizaccess_sebreview'));

        if ($mform->elementExists('seb') && $mform->elementExists('security')) {
            // Show the setting at the end of the Safe Exam Browser section.
            $mform->insertElementBefore($element, 'security');
        } else {
            $mform->addElement($element);
        }

        $mform->setType($name, PARAM_BOOL);
        $mform->setDefault($name, 0);
        $mform->addHelpButton($name, 'sebreviewrequired', 'quizaccess_sebreview');
    }

    /**
     * Validate the data from the form field added by {@see add_settings_form_fields()}.
     *
     * The restriction relies on the Safe Exam Browser settings of the quiz, so it can
     * only be switched on if Safe Exam Browser is required for the quiz.
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param mod_quiz_mod_form $quizform the quiz form object.
     * @return array $errors the updated $errors array.
     */
    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if (empty($data[review_guard::SETTING])) {
            return $errors;
        }

        $quizid = empty($data['instance']) ? 0 : (int) $data['instance'];
        // Once a quiz has attempts, its Safe Exam Browser settings can no longer be changed.
        $seblocked = $quizid && quiz_has_attempts($quizid);

        if (!$seblocked && array_key_exists('seb_requiresafeexambrowser', $data)) {
            $sebrequired = !empty($data['seb_requiresafeexambrowser']);
        } else {
            $sebrequired = $quizid && review_guard::is_seb_required_for_quizid($quizid);
        }

        if (!$sebrequired) {
            $errors[review_guard::SETTING] = get_string('error:sebnotrequired', 'quizaccess_sebreview');
        }

        return $errors;
    }

    /**
     * Save the setting when the quiz settings form is submitted.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id.
     */
    public static function save_settings($quiz) {
        if (empty($quiz->id) || !property_exists($quiz, review_guard::SETTING)) {
            // The quiz was saved without this form field, so leave the setting as it is.
            return;
        }
        review_guard::set_required((int) $quiz->id, !empty($quiz->{review_guard::SETTING}));
    }

    /**
     * Delete the setting when the quiz is deleted.
     *
     * @param stdClass $quiz the data from the database, including $quiz->id.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records(review_guard::TABLE, ['quizid' => $quiz->id]);
    }

    /**
     * Return the bits of SQL needed to load the setting together with the quiz.
     *
     * @param int $quizid the id of the quiz we are loading settings for.
     * @return array with three elements: fields, joins and parameters.
     */
    public static function get_settings_sql($quizid) {
        return [
            'sebreview.reviewinseb AS ' . review_guard::SETTING,
            'LEFT JOIN {' . review_guard::TABLE . '} sebreview ON sebreview.quizid = quiz.id',
            [],
        ];
    }
}
