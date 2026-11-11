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

use mod_quiz\access_manager;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/helper_trait.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/sebreview/rule.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for the access rule class: settings, quiz form, deletion and duplication.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_sebreview
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\quizaccess_sebreview::class)]
final class rule_test extends \advanced_testcase {
    use helper_trait;

    /** @var \stdClass the course. */
    private $course;

    /**
     * Set up a course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Clean up the request data that fakes Safe Exam Browser.
     */
    protected function tearDown(): void {
        $this->forget_safe_exam_browser();
        parent::tearDown();
    }

    /**
     * The rule is only active for quizzes that have the setting switched on.
     */
    public function test_make(): void {
        $quizon = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $quizoff = $this->create_quiz_with_question($this->course, self::$sebmanual, false);

        $rule = \quizaccess_sebreview::make(quiz_settings::create($quizon->id), time(), false);
        $this->assertInstanceOf(\quizaccess_sebreview::class, $rule);
        $this->assertNull(\quizaccess_sebreview::make(quiz_settings::create($quizoff->id), time(), false));

        // The rule never interferes with attempts: that is the job of the standard rule.
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->prevent_new_attempt(0, false));
        $this->assertFalse($rule->is_preflight_check_required(null));
        $this->assertNotEmpty($rule->description());
    }

    /**
     * The quiz access manager picks the rule up and loads its setting with the quiz.
     */
    public function test_access_manager_integration(): void {
        $quizon = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $quizoff = $this->create_quiz_with_question($this->course, self::$sebmanual, false);

        $settings = access_manager::load_settings($quizon->id);
        $this->assertArrayHasKey(review_guard::SETTING, $settings);
        $this->assertEquals(1, $settings[review_guard::SETTING]);
        $this->assertEmpty(access_manager::load_settings($quizoff->id)[review_guard::SETTING]);

        $this->assertEquals(1, access_manager::load_quiz_and_settings($quizon->id)->{review_guard::SETTING});

        $manager = new access_manager(quiz_settings::create($quizon->id), time(), false);
        $this->assertContains('quizaccess_sebreview', $manager->get_active_rule_names());
        $this->assertContains(get_string('reviewinsebinfo', 'quizaccess_sebreview'), $manager->describe_rules());

        $manager = new access_manager(quiz_settings::create($quizoff->id), time(), false);
        $this->assertNotContains('quizaccess_sebreview', $manager->get_active_rule_names());
    }

    /**
     * Setting up the page of an attempt in progress does not trigger the review check.
     */
    public function test_setup_attempt_page_ignores_other_pages(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $rule = \quizaccess_sebreview::make(quiz_settings::create($quiz->id, $student->id), time(), false);

        $page = new \moodle_page();
        $page->set_url(new \moodle_url('/mod/quiz/attempt.php', ['attempt' => 1]));
        $rule->setup_attempt_page($page);

        $page = new \moodle_page();
        $page->set_url(new \moodle_url('/mod/quiz/summary.php', ['attempt' => 1]));
        $rule->setup_attempt_page($page);

        // Reaching this line means that the script was not stopped.
        $this->assertTrue(true);
    }

    /**
     * Saving the quiz form stores, changes and removes the setting.
     */
    public function test_save_settings(): void {
        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $this->assertFalse(review_guard::is_required_for_quizid($quiz->id));

        \quizaccess_sebreview::save_settings((object) ['id' => $quiz->id, review_guard::SETTING => 1]);
        $this->assertTrue(review_guard::is_required_for_quizid($quiz->id));

        // Saving a quiz without the form field leaves the setting alone.
        \quizaccess_sebreview::save_settings((object) ['id' => $quiz->id]);
        $this->assertTrue(review_guard::is_required_for_quizid($quiz->id));

        \quizaccess_sebreview::save_settings((object) ['id' => $quiz->id, review_guard::SETTING => 0]);
        $this->assertFalse(review_guard::is_required_for_quizid($quiz->id));
    }

    /**
     * Data for the test of the form validation.
     *
     * @return array[]
     */
    public static function validation_provider(): array {
        return [
            'option off, seb off' => [0, 0, false],
            'option off, seb on' => [0, 1, false],
            'option on, seb on' => [1, 1, false],
            'option on, seb client config' => [1, 4, false],
            'option on, seb off' => [1, 0, true],
        ];
    }

    /**
     * The option can only be switched on together with Safe Exam Browser (new quiz).
     *
     * @dataProvider validation_provider
     * @param int $option submitted value of the option.
     * @param int $seb submitted value of the setting "Require the use of Safe Exam Browser".
     * @param bool $expecterror whether a validation error is expected.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validation_provider')]
    public function test_validation_for_new_quiz(int $option, int $seb, bool $expecterror): void {
        $form = $this->createStub(\mod_quiz_mod_form::class);
        $data = ['instance' => '', review_guard::SETTING => $option, 'seb_requiresafeexambrowser' => $seb];

        $errors = \quizaccess_sebreview::validate_settings_form_fields([], $data, [], $form);
        $this->assertSame($expecterror, array_key_exists(review_guard::SETTING, $errors));
    }

    /**
     * Without the Safe Exam Browser field in the form, the stored settings of the quiz decide.
     */
    public function test_validation_uses_stored_seb_settings(): void {
        $form = $this->createStub(\mod_quiz_mod_form::class);
        $quizseb = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $quiznoseb = $this->create_quiz_with_question($this->course, self::$sebno, false);

        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            [],
            ['instance' => $quizseb->id, review_guard::SETTING => 1],
            [],
            $form
        );
        $this->assertArrayNotHasKey(review_guard::SETTING, $errors);

        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            [],
            ['instance' => $quiznoseb->id, review_guard::SETTING => 1],
            [],
            $form
        );
        $this->assertArrayHasKey(review_guard::SETTING, $errors);

        // A new quiz without the Safe Exam Browser field cannot require Safe Exam Browser.
        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            [],
            ['instance' => '', review_guard::SETTING => 1],
            [],
            $form
        );
        $this->assertArrayHasKey(review_guard::SETTING, $errors);

        // Errors found by other rules are kept.
        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            ['other' => 'x'],
            ['instance' => $quiznoseb->id, review_guard::SETTING => 1],
            [],
            $form
        );
        $this->assertArrayHasKey('other', $errors);
        $this->assertArrayHasKey(review_guard::SETTING, $errors);
    }

    /**
     * Once a quiz has attempts its Safe Exam Browser settings are frozen, so the stored
     * settings decide and a submitted value is ignored. The option itself stays changeable.
     */
    public function test_validation_for_quiz_with_attempts(): void {
        $form = $this->createStub(\mod_quiz_mod_form::class);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $quizseb = $this->create_quiz_with_question($this->course, self::$sebmanual, false);
        $this->create_finished_attempt($quizseb, $student);
        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            [],
            ['instance' => $quizseb->id, review_guard::SETTING => 1, 'seb_requiresafeexambrowser' => 0],
            [],
            $form
        );
        $this->assertArrayNotHasKey(review_guard::SETTING, $errors);

        $quiznoseb = $this->create_quiz_with_question($this->course, self::$sebno, false);
        $this->create_finished_attempt($quiznoseb, $student);
        $errors = \quizaccess_sebreview::validate_settings_form_fields(
            [],
            ['instance' => $quiznoseb->id, review_guard::SETTING => 1, 'seb_requiresafeexambrowser' => 1],
            [],
            $form
        );
        $this->assertArrayHasKey(review_guard::SETTING, $errors);
    }

    /**
     * Deleting a quiz removes its setting.
     */
    public function test_delete_settings(): void {
        global $DB;

        $quiz = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $other = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $this->assertEquals(2, $DB->count_records(review_guard::TABLE));

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        $this->delete_activity($this->course, $cm->id);

        $this->assertEquals(0, $DB->count_records(review_guard::TABLE, ['quizid' => $quiz->id]));
        $this->assertEquals(1, $DB->count_records(review_guard::TABLE, ['quizid' => $other->id]));
    }

    /**
     * Duplicating a quiz (backup and restore) keeps the setting.
     */
    public function test_duplicate_keeps_setting(): void {
        $this->setAdminUser();

        $quizon = $this->create_quiz_with_question($this->course, self::$sebmanual, true);
        $quizoff = $this->create_quiz_with_question($this->course, self::$sebmanual, false);

        $cm = get_coursemodule_from_instance('quiz', $quizon->id, $this->course->id, false, MUST_EXIST);
        $newquizid = $this->duplicate_activity($this->course, $cm);
        $this->assertNotEquals($quizon->id, $newquizid);
        $this->assertTrue(review_guard::is_required_for_quizid($newquizid));
        $this->assertTrue(review_guard::is_seb_required_for_quizid($newquizid));
        $this->assertTrue(review_guard::is_required_for_quizid($quizon->id));

        $cm = get_coursemodule_from_instance('quiz', $quizoff->id, $this->course->id, false, MUST_EXIST);
        $newquizid = $this->duplicate_activity($this->course, $cm);
        $this->assertNotEquals($quizoff->id, $newquizid);
        $this->assertFalse(review_guard::is_required_for_quizid($newquizid));
    }

    /**
     * The privacy provider declares that no personal data is stored.
     */
    public function test_privacy_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\null_provider::class,
            new \quizaccess_sebreview\privacy\provider()
        );
        $this->assertTrue(get_string_manager()->string_exists(
            \quizaccess_sebreview\privacy\provider::get_reason(),
            'quizaccess_sebreview'
        ));
    }

    /**
     * The German language file has exactly the same strings as the English one.
     */
    public function test_language_files_match(): void {
        $load = function (string $language): array {
            global $CFG;
            $string = [];
            include($CFG->dirroot . "/mod/quiz/accessrule/sebreview/lang/{$language}/quizaccess_sebreview.php");
            return $string;
        };

        $english = $load('en');
        $german = $load('de');
        $this->assertNotEmpty($english);
        $this->assertSame(array_keys($english), array_keys($german));
        foreach ($german as $text) {
            $this->assertNotEmpty($text);
        }
    }
}
