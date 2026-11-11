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

namespace quizaccess_sebreview;

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Helpers shared by the tests of the quizaccess_sebreview plugin.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait helper_trait {
    /** @var int Safe Exam Browser is not required (settings_provider::USE_SEB_NO). */
    protected static $sebno = 0;

    /** @var int Safe Exam Browser configured manually (settings_provider::USE_SEB_CONFIG_MANUALLY). */
    protected static $sebmanual = 1;

    /** @var int Safe Exam Browser with the client configuration (settings_provider::USE_SEB_CLIENT_CONFIG). */
    protected static $sebclient = 4;

    /**
     * Create a quiz with one true/false question.
     *
     * @param \stdClass $course the course.
     * @param int $sebtype value of the setting "Require the use of Safe Exam Browser".
     * @param bool $reviewinseb whether the review is restricted to Safe Exam Browser.
     * @return \stdClass the quiz, as returned by the generator.
     */
    protected function create_quiz_with_question(\stdClass $course, int $sebtype, bool $reviewinseb): \stdClass {
        $this->setAdminUser();

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 1,
            'seb_requiresafeexambrowser' => $sebtype,
            review_guard::SETTING => $reviewinseb ? 1 : 0,
        ]);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        return $quiz;
    }

    /**
     * Create a finished attempt at a quiz for a user.
     *
     * @param \stdClass $quiz the quiz.
     * @param \stdClass $user the user.
     * @return quiz_attempt the finished attempt.
     */
    protected function create_finished_attempt(\stdClass $quiz, \stdClass $user): quiz_attempt {
        $timenow = time();
        $quizobj = quiz_settings::create($quiz->id, $user->id);

        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '1']]);
        if (method_exists($attemptobj, 'process_submit')) {
            // Moodle 5.0 and later: submitting and grading are separate steps.
            $attemptobj->process_submit($timenow, false);
            $attemptobj->process_grade_submission($timenow);
        } else {
            $attemptobj->process_finish($timenow, false);
        }

        return quiz_attempt::create($attempt->id);
    }

    /**
     * Delete an activity, with the function that the running Moodle version provides for it.
     *
     * @param \stdClass $course the course.
     * @param int $cmid the course module id.
     */
    protected function delete_activity(\stdClass $course, int $cmid): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $formatactions = '\core_courseformat\formatactions';
        if (class_exists($formatactions) && method_exists($formatactions::cm($course->id), 'delete')) {
            $formatactions::cm($course->id)->delete($cmid);
        } else {
            course_delete_module($cmid);
        }
    }

    /**
     * Duplicate an activity, with the function that the running Moodle version provides for it.
     *
     * @param \stdClass $course the course.
     * @param \stdClass $cm the course module.
     * @return int the instance id of the copy.
     */
    protected function duplicate_activity(\stdClass $course, \stdClass $cm): int {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $formatactions = '\core_courseformat\formatactions';
        if (class_exists($formatactions) && method_exists($formatactions::cm($course->id), 'duplicate')) {
            $newcm = $formatactions::cm($course->id)->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }
        $this->assertNotEmpty($newcm);

        return (int) $newcm->instance;
    }

    /**
     * Pretend that the current request comes from a Safe Exam Browser that sends a valid Config Key.
     *
     * @param \stdClass $quiz the quiz.
     */
    protected function send_valid_config_key(\stdClass $quiz): void {
        global $FULLME;

        $FULLME = 'https://www.example.com/moodle/mod/quiz/review.php?attempt=1';
        $configkey = \quizaccess_seb\seb_quiz_settings::get_config_key_by_quiz_id($quiz->id);
        $this->assertNotEmpty($configkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = hash('sha256', $FULLME . $configkey);
    }

    /**
     * Remove everything a test may have added to pretend to be Safe Exam Browser.
     */
    protected function forget_safe_exam_browser(): void {
        global $SESSION;

        unset($_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH']);
        unset($_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH']);
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($SESSION->quizaccess_seb_access);
    }
}
