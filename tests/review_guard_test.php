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

use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/helper_trait.php');

#[\PHPUnit\Framework\Attributes\CoversClass(review_guard::class)]
/**
 * Tests for the decision whether a user may review an attempt.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_sebreview\review_guard
 */
final class review_guard_test extends \advanced_testcase {
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
        parent::tearDown();
    }

    /**
     * The setting is read from the loaded quiz settings, or from the database if it is missing there.
     */
    public function test_is_required_for_quiz(): void {
        global $DB;

        $quizon = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $quizoff = $this->create_quiz_with_question($this->course, self::$sebmanual, false);

        // Loaded together with the access rule settings.
        $this->assertTrue(review_guard::is_required_for_quiz(quiz_settings::create($quizon->id)->get_quiz()));
        $this->assertFalse(review_guard::is_required_for_quiz(quiz_settings::create($quizoff->id)->get_quiz()));

        // Plain quiz records, without the access rule settings.
        $this->assertTrue(review_guard::is_required_for_quiz($DB->get_record('quiz', ['id' => $quizon->id])));
        $this->assertFalse(review_guard::is_required_for_quiz($DB->get_record('quiz', ['id' => $quizoff->id])));

        $this->assertTrue(review_guard::is_required_for_quizid($quizon->id));
        $this->assertFalse(review_guard::is_required_for_quizid($quizoff->id));
        $this->assertFalse(review_guard::is_required_for_quizid(0));
    }

    /**
     * Switching the setting on and off never leaves more than one row per quiz.
     */
    public function test_set_required(): void {
        global $DB;

        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $this->assertEquals(0, $DB->count_records(review_guard::TABLE, ['quizid' => $quiz->id]));

        review_guard::set_required($quiz->id, true);
        review_guard::set_required($quiz->id, true);
        $this->assertEquals(1, $DB->count_records(review_guard::TABLE, ['quizid' => $quiz->id]));
        $this->assertTrue(review_guard::is_required_for_quizid($quiz->id));

        review_guard::set_required($quiz->id, false);
        review_guard::set_required($quiz->id, false);
        $this->assertEquals(0, $DB->count_records(review_guard::TABLE, ['quizid' => $quiz->id]));
        $this->assertFalse(review_guard::is_required_for_quizid($quiz->id));
    }

    /**
     * A row that says "not required" is repaired when the setting is switched on.
     */
    public function test_set_required_updates_existing_row(): void {
        global $DB;

        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $DB->insert_record(review_guard::TABLE, (object) ['quizid' => $quiz->id, 'reviewinseb' => 0]);
        $this->assertFalse(review_guard::is_required_for_quizid($quiz->id));

        review_guard::set_required($quiz->id, true);
        $this->assertEquals(1, $DB->count_records(review_guard::TABLE, ['quizid' => $quiz->id]));
        $this->assertTrue(review_guard::is_required_for_quizid($quiz->id));
    }

    /**
     * A student outside Safe Exam Browser is blocked.
     */
    public function test_student_without_seb_is_blocked(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $sink = $this->redirectEvents();
        $blockinfo = review_guard::get_block_info(quiz_settings::create($quiz->id, $this->student->id));
        $events = $sink->get_events();
        $sink->close();

        $this->assertIsArray($blockinfo);
        $this->assertSame(review_guard::REASON_NOT_IN_SEB, $blockinfo['reason']);
        $this->assertIsString($blockinfo['sebhtml']);

        // The standard Safe Exam Browser rule logs the prevented access.
        $prevented = array_filter($events, function ($event) {
            return $event instanceof \quizaccess_seb\event\access_prevented;
        });
        $this->assertCount(1, $prevented);
    }

    /**
     * The review is blocked even if the standard rule has no message or button to show.
     */
    public function test_student_without_seb_is_blocked_without_buttons(): void {
        global $DB;

        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        // Neither the download button nor the links to start Safe Exam Browser are shown.
        $DB->set_field('quizaccess_seb_quizsettings', 'showsebdownloadlink', 0, ['quizid' => $quiz->id]);
        set_config('showseblinks', '', 'quizaccess_seb');
        \cache::make('quizaccess_seb', 'quizsettings')->purge();

        $this->setUser($this->student);
        $blockinfo = review_guard::get_block_info(quiz_settings::create($quiz->id, $this->student->id));

        $this->assertIsArray($blockinfo);
        $this->assertSame(review_guard::REASON_NOT_IN_SEB, $blockinfo['reason']);
        $this->assertSame('', $blockinfo['sebhtml']);
    }

    /**
     * A student whose browser claims to be Safe Exam Browser but sends no key is blocked.
     */
    public function test_student_with_fake_user_agent_is_blocked(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 SEB/3.0';
        $blockinfo = review_guard::get_block_info(quiz_settings::create($quiz->id, $this->student->id));

        $this->assertIsArray($blockinfo);
        $this->assertSame(review_guard::REASON_NOT_IN_SEB, $blockinfo['reason']);
    }

    /**
     * A student who sends a wrong Config Key is blocked.
     */
    public function test_student_with_wrong_config_key_is_blocked(): void {
        global $FULLME;

        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $FULLME = 'https://www.example.com/moodle/mod/quiz/review.php?attempt=1';
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = hash('sha256', $FULLME . 'not the config key');
        $blockinfo = review_guard::get_block_info(quiz_settings::create($quiz->id, $this->student->id));

        $this->assertIsArray($blockinfo);
        $this->assertSame(review_guard::REASON_NOT_IN_SEB, $blockinfo['reason']);
    }

    /**
     * A student in Safe Exam Browser with the valid Config Key may review, and stays
     * validated for the rest of the session.
     */
    public function test_student_with_valid_config_key_is_allowed(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $quizobj = quiz_settings::create($quiz->id, $this->student->id);

        $this->send_valid_config_key($quiz);
        $this->assertNull(review_guard::get_block_info($quizobj));

        // The validation is remembered for this session and quiz, as for attempts.
        unset($_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH']);
        $this->assertNull(review_guard::get_block_info($quizobj));

        // A new session has to be validated again.
        $this->forget_safe_exam_browser();
        $this->assertIsArray(review_guard::get_block_info($quizobj));
    }

    /**
     * The validation of one quiz does not open the review of another quiz.
     */
    public function test_validation_is_per_quiz(): void {
        $quiz1 = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $quiz2 = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $this->send_valid_config_key($quiz1);
        $this->assertNull(review_guard::get_block_info(quiz_settings::create($quiz1->id, $this->student->id)));

        unset($_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH']);
        $this->assertIsArray(review_guard::get_block_info(quiz_settings::create($quiz2->id, $this->student->id)));
    }

    /**
     * With the client configuration, the standard rule accepts any Safe Exam Browser.
     */
    public function test_client_configuration(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebclient, true);

        $this->setUser($this->student);
        $quizobj = quiz_settings::create($quiz->id, $this->student->id);

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605.1.15';
        $this->assertIsArray(review_guard::get_block_info($quizobj));

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) SEB/3.4';
        $this->assertNull(review_guard::get_block_info($quizobj));
    }

    /**
     * Teachers are not restricted.
     */
    public function test_teacher_is_exempt(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->teacher);
        $quizobj = quiz_settings::create($quiz->id, $this->teacher->id);

        $this->assertTrue(review_guard::user_is_exempt($quizobj->get_context()));
        $this->assertNull(review_guard::get_block_info($quizobj));

        $this->setUser($this->student);
        $this->assertFalse(review_guard::user_is_exempt($quizobj->get_context()));

        $this->setAdminUser();
        $this->assertTrue(review_guard::user_is_exempt($quizobj->get_context()));
    }

    /**
     * If the restriction is on but Safe Exam Browser is not required for the quiz,
     * the review stays closed for students.
     */
    public function test_fails_closed_without_seb_settings(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebno, true);

        $this->setUser($this->student);
        $blockinfo = review_guard::get_block_info(quiz_settings::create($quiz->id, $this->student->id));
        $this->assertIsArray($blockinfo);
        $this->assertSame(review_guard::REASON_SEB_NOT_CONFIGURED, $blockinfo['reason']);

        $this->setUser($this->teacher);
        $this->assertNull(review_guard::get_block_info(quiz_settings::create($quiz->id, $this->teacher->id)));
    }

    /**
     * The check whether Safe Exam Browser is required for a quiz.
     */
    public function test_is_seb_required_for_quizid(): void {
        $quizseb = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $quiznoseb = $this->create_quiz_with_question($this->course, self::$sebno, false);

        $this->assertTrue(review_guard::is_seb_required_for_quizid($quizseb->id));
        $this->assertFalse(review_guard::is_seb_required_for_quizid($quiznoseb->id));
        $this->assertFalse(review_guard::is_seb_required_for_quizid(0));
    }

    /**
     * Only the review pages are recognised.
     */
    public function test_is_page(): void {
        $page = new \moodle_page();
        $this->assertFalse(review_guard::is_page($page, '/mod/quiz/review.php'));

        $page->set_url(new \moodle_url('/mod/quiz/review.php', ['attempt' => 5, 'page' => 2]));
        $this->assertTrue(review_guard::is_page($page, '/mod/quiz/review.php'));
        $this->assertFalse(review_guard::is_page($page, '/mod/quiz/reviewquestion.php'));

        $page = new \moodle_page();
        $page->set_url(new \moodle_url('/mod/quiz/attempt.php', ['attempt' => 5]));
        $this->assertFalse(review_guard::is_page($page, '/mod/quiz/review.php'));
    }

    /**
     * The page of the current request is also recognised by the running script.
     */
    public function test_is_page_uses_running_script(): void {
        global $PAGE, $SCRIPT;

        $originalscript = $SCRIPT;
        $SCRIPT = '/mod/quiz/reviewquestion.php';
        try {
            $this->assertFalse($PAGE->has_set_url());
            $this->assertTrue(review_guard::is_page($PAGE, '/mod/quiz/reviewquestion.php'));
            $this->assertFalse(review_guard::is_page($PAGE, '/mod/quiz/review.php'));
            // Any other page object is only judged by its own URL.
            $this->assertFalse(review_guard::is_page(new \moodle_page(), '/mod/quiz/reviewquestion.php'));
        } finally {
            $SCRIPT = $originalscript;
        }
    }

    /**
     * The page check does nothing for users who are allowed to review.
     */
    public function test_enforce_on_page_returns_if_allowed(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);

        $this->setUser($this->student);
        $this->send_valid_config_key($quiz);

        $page = new \moodle_page();
        $page->set_url(new \moodle_url('/mod/quiz/review.php', ['attempt' => 1]));
        review_guard::enforce_on_page(quiz_settings::create($quiz->id, $this->student->id), $page);

        // Reaching this line means that the script was not stopped.
        $this->assertTrue(true);
    }
}
