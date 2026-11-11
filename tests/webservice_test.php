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

use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/helper_trait.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/sebreview/lib.php');

/**
 * Tests that the web service used by the Moodle app cannot be used to get around the restriction.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('quizaccess_sebreview_override_webservice_execution')]
#[\PHPUnit\Framework\Attributes\CoversClass(review_guard::class)]
final class webservice_test extends \advanced_testcase {
    use helper_trait;

    /** @var \stdClass the course. */
    private $course;

    /** @var \stdClass a student in the course. */
    private $student;

    /** @var \stdClass a non-editing teacher in the course. */
    private $teacher;

    /**
     * Set up a course with a student and a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
    }

    /**
     * Clean up the request data that fakes Safe Exam Browser.
     */
    protected function tearDown(): void {
        $this->forget_safe_exam_browser();
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * Call the web service that returns the review of an attempt, the way Moodle does it for a real request.
     *
     * @param int $attemptid the attempt id.
     * @return array the response, with the keys 'error' and 'data' or 'exception'.
     */
    private function call_get_attempt_review(int $attemptid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('mod_quiz_get_attempt_review', ['attemptid' => $attemptid]);
    }

    /**
     * A student cannot read the review through the web service.
     */
    public function test_student_is_blocked(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);

        $this->setUser($this->student);
        $response = $this->call_get_attempt_review($attemptobj->get_attemptid());

        $this->assertTrue($response['error']);
        $this->assertSame('reviewrequiresseb', $response['exception']->errorcode);
        $this->assertArrayNotHasKey('data', $response);
    }

    /**
     * Without the restriction the web service works as before, also for quizzes that require Safe Exam Browser.
     */
    public function test_student_is_allowed_if_setting_is_off(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);

        $this->setUser($this->student);
        $response = $this->call_get_attempt_review($attemptobj->get_attemptid());

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']['questions']);
    }

    /**
     * A student in a validated Safe Exam Browser session gets the review.
     */
    public function test_student_in_seb_is_allowed(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);

        $this->setUser($this->student);
        $this->send_valid_config_key($quiz);
        $response = $this->call_get_attempt_review($attemptobj->get_attemptid());

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']['questions']);
    }

    /**
     * Teachers can still read the review of a student's attempt.
     */
    public function test_teacher_is_allowed(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);

        $this->setUser($this->teacher);
        $response = $this->call_get_attempt_review($attemptobj->get_attemptid());

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']['questions']);
    }

    /**
     * If Safe Exam Browser is not set up for the quiz, the review stays closed.
     */
    public function test_student_is_blocked_without_seb_settings(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebno, true);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);

        $this->setUser($this->student);
        $response = $this->call_get_attempt_review($attemptobj->get_attemptid());

        $this->assertTrue($response['error']);
        $this->assertSame('sebnotconfigured', $response['exception']->errorcode);
    }

    /**
     * The callback never replaces a web service function and ignores everything it does not guard.
     */
    public function test_callback_ignores_other_calls(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $attemptobj = $this->create_finished_attempt($quiz, $this->student);
        $attemptid = $attemptobj->get_attemptid();

        $this->setUser($this->student);

        // Other functions, also with an attempt id as first parameter.
        $other = (object) ['name' => 'mod_quiz_get_user_best_grade'];
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution($other, [$attemptid]));
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution((object) [], [$attemptid]));
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution(null, [$attemptid]));

        // The guarded function with unusable parameters: left to the function itself.
        $review = (object) ['name' => 'mod_quiz_get_attempt_review'];
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution($review, []));
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution($review, [0]));
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution($review, [-1]));
        $this->assertFalse(quizaccess_sebreview_override_webservice_execution($review, [99999999]));

        // The guarded function for a protected attempt.
        $this->expectException(\moodle_exception::class);
        quizaccess_sebreview_override_webservice_execution($review, [$attemptid, -1]);
    }
}
