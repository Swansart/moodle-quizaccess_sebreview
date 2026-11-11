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

/**
 * Decides whether the current user may review a quiz attempt, and blocks the review if not.
 *
 * The decision whether the user is working in a correctly configured Safe Exam Browser is
 * delegated to the standard quizaccess_seb rule, so reviewing an attempt is protected by
 * exactly the same check as taking the attempt.
 *
 * @package    quizaccess_sebreview
 * @copyright  2026 Arthur Schwaninger
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_guard {
    /** @var string Name of the table that stores the setting. */
    public const TABLE = 'quizaccess_sebreview';

    /** @var string Name of the form field, and of the property on the loaded quiz settings. */
    public const SETTING = 'sebreview_required';

    /** @var string The review is blocked because the user is not in a valid Safe Exam Browser. */
    public const REASON_NOT_IN_SEB = 'notinseb';

    /** @var string The review is blocked because Safe Exam Browser is not required for the quiz. */
    public const REASON_SEB_NOT_CONFIGURED = 'sebnotconfigured';

    /** @var string[] Web service functions that return the content of a finished attempt. */
    private const GUARDED_WEBSERVICES = ['mod_quiz_get_attempt_review'];

    /** @var string[] Users with any of these capabilities are never restricted. */
    private const EXEMPT_CAPABILITIES = [
        'quizaccess/seb:bypassseb',
        'mod/quiz:viewreports',
        'mod/quiz:preview',
    ];

    /** @var bool True while the "review is blocked" page is being rendered. */
    private static $rendering = false;

    /**
     * Whether the review of the given quiz is restricted to Safe Exam Browser.
     *
     * @param \stdClass $quiz the quiz settings, ideally as loaded by the quiz access manager.
     * @return bool
     */
    public static function is_required_for_quiz(\stdClass $quiz): bool {
        if (property_exists($quiz, self::SETTING)) {
            return !empty($quiz->{self::SETTING});
        }
        // The quiz record was loaded without the access rule settings, so look it up.
        return !empty($quiz->id) && self::is_required_for_quizid((int) $quiz->id);
    }

    /**
     * Whether the review of the quiz with the given id is restricted to Safe Exam Browser.
     *
     * @param int $quizid the quiz id.
     * @return bool
     */
    public static function is_required_for_quizid(int $quizid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['quizid' => $quizid, 'reviewinseb' => 1]);
    }

    /**
     * Store the setting for a quiz.
     *
     * @param int $quizid the quiz id.
     * @param bool $required whether the review is restricted to Safe Exam Browser.
     */
    public static function set_required(int $quizid, bool $required): void {
        global $DB;

        if (!$required) {
            $DB->delete_records(self::TABLE, ['quizid' => $quizid]);
            return;
        }

        $existing = $DB->get_record(self::TABLE, ['quizid' => $quizid]);
        if (!$existing) {
            $DB->insert_record(self::TABLE, (object) ['quizid' => $quizid, 'reviewinseb' => 1]);
        } else if ((int) $existing->reviewinseb !== 1) {
            $DB->set_field(self::TABLE, 'reviewinseb', 1, ['id' => $existing->id]);
        }
    }

    /**
     * Whether Safe Exam Browser is currently required for attempts at the given quiz.
     *
     * @param int $quizid the quiz id.
     * @return bool
     */
    public static function is_seb_required_for_quizid(int $quizid): bool {
        global $DB;
        return $DB->record_exists_select(
            'quizaccess_seb_quizsettings',
            'quizid = :quizid AND requiresafeexambrowser <> 0',
            ['quizid' => $quizid]
        );
    }

    /**
     * Whether the current user is exempt from the restriction (teachers, managers).
     *
     * @param \context $context the quiz context.
     * @return bool
     */
    public static function user_is_exempt(\context $context): bool {
        return has_any_capability(self::EXEMPT_CAPABILITIES, $context);
    }

    /**
     * Get the standard Safe Exam Browser access rule for a quiz.
     *
     * @param quiz_settings $quizobj the quiz.
     * @return \quizaccess_seb|null the rule, or null if Safe Exam Browser is not required for the quiz.
     */
    protected static function get_seb_rule(quiz_settings $quizobj) {
        global $CFG;

        if (!class_exists('quizaccess_seb', false)) {
            $directory = \core_component::get_plugin_directory('quizaccess', 'seb');
            if (empty($directory) || !is_readable($directory . '/rule.php')) {
                return null;
            }
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            require_once($directory . '/rule.php');
        }

        return \quizaccess_seb::make($quizobj, time(), false);
    }

    /**
     * Work out whether the current user must be stopped from reviewing attempts at this quiz right now.
     *
     * This must only be called for quizzes where the restriction is switched on.
     *
     * @param quiz_settings $quizobj the quiz.
     * @return array|null null if the review is allowed. Otherwise an array with the keys
     *      'reason' (one of the REASON_ constants) and 'sebhtml' (message and buttons
     *      from the standard Safe Exam Browser rule, may be empty).
     */
    public static function get_block_info(quiz_settings $quizobj): ?array {
        if (self::user_is_exempt($quizobj->get_context())) {
            return null;
        }

        $sebrule = self::get_seb_rule($quizobj);
        if ($sebrule === null) {
            // The restriction is on, but Safe Exam Browser is not required for the quiz
            // (for example after a restore). Fail closed: do not show the attempt.
            return ['reason' => self::REASON_SEB_NOT_CONFIGURED, 'sebhtml' => ''];
        }

        // Exactly the check that protects the attempt itself: session, headers and keys.
        $result = $sebrule->prevent_access();
        if ($result === false) {
            return null;
        }

        return ['reason' => self::REASON_NOT_IN_SEB, 'sebhtml' => is_string($result) ? $result : ''];
    }

    /**
     * Whether the given page is the given script, for example '/mod/quiz/review.php'.
     *
     * @param \moodle_page $page the page.
     * @param string $script path of the script relative to the site root.
     * @return bool
     */
    public static function is_page(\moodle_page $page, string $script): bool {
        global $PAGE, $SCRIPT;

        if ($page->has_set_url() && $page->url->compare(new \moodle_url($script), URL_MATCH_BASE)) {
            return true;
        }

        // For the page of the current request, also go by the script that is running,
        // so that the check does not depend on when the script sets the page URL.
        return $page === $PAGE && !empty($SCRIPT) && $SCRIPT === $script;
    }

    /**
     * If the current user may not review attempts at this quiz right now, show an explanation
     * instead of the page that was requested, and stop.
     *
     * @param quiz_settings $quizobj the quiz.
     * @param \moodle_page $page the page that is being set up.
     */
    public static function enforce_on_page(quiz_settings $quizobj, \moodle_page $page): void {
        if (self::$rendering) {
            // Rendering the explanation must not trigger the check again.
            return;
        }

        $blockinfo = self::get_block_info($quizobj);
        if ($blockinfo === null) {
            return;
        }

        self::$rendering = true;
        self::render_blocked_page($quizobj, $page, $blockinfo);
        die();
    }

    /**
     * Output the page that explains why the review is not shown.
     *
     * @param quiz_settings $quizobj the quiz.
     * @param \moodle_page $page the page.
     * @param array $blockinfo as returned by {@see get_block_info()}.
     */
    protected static function render_blocked_page(quiz_settings $quizobj, \moodle_page $page, array $blockinfo): void {
        global $OUTPUT;

        $viewurl = new \moodle_url('/mod/quiz/view.php', ['id' => $quizobj->get_cmid()]);
        $quizname = format_string($quizobj->get_quiz_name(), true, ['context' => $quizobj->get_context()]);

        $page->set_title($quizname);
        $page->set_heading(format_string($quizobj->get_course()->fullname, true, ['context' => $quizobj->get_context()]));
        // The quiz name is printed below, like on the review page itself.
        $page->activityheader->disable();

        $content = '';
        if ($blockinfo['reason'] === self::REASON_SEB_NOT_CONFIGURED) {
            $content .= $OUTPUT->notification(
                get_string('sebnotconfigured', 'quizaccess_sebreview'),
                \core\output\notification::NOTIFY_ERROR,
                false
            );
        } else {
            // In Safe Exam Browser versions that hand over their keys by JavaScript, this
            // validates the keys and then reloads the page, exactly as on the quiz page.
            $sebdirectory = \core_component::get_plugin_directory('quizaccess', 'seb');
            if (!empty($sebdirectory) && file_exists($sebdirectory . '/amd/build/validate_quiz_access.min.js')) {
                $page->requires->js_call_amd(
                    'quizaccess_seb/validate_quiz_access',
                    'init',
                    [(int) $quizobj->get_cmid(), (bool) get_config('quizaccess_seb', 'autoreconfigureseb')]
                );
            }

            $content .= $OUTPUT->notification(
                get_string('reviewrequiresseb', 'quizaccess_sebreview'),
                \core\output\notification::NOTIFY_WARNING,
                false
            );
            $content .= \html_writer::tag('p', get_string('reviewinstructions', 'quizaccess_sebreview'));
            if ($blockinfo['sebhtml'] !== '') {
                // Message and buttons of the standard Safe Exam Browser rule.
                $content .= \html_writer::div($blockinfo['sebhtml'], 'quizaccess_sebreview-sebmessage mb-3');
            }
        }
        $content .= \html_writer::div(
            $OUTPUT->single_button($viewurl, get_string('backtoquiz', 'quizaccess_sebreview'), 'get'),
            'quizaccess_sebreview-back mt-3'
        );

        echo $OUTPUT->header();
        echo $OUTPUT->heading($quizname);
        echo $OUTPUT->box($content, 'generalbox quizaccess_sebreview-blocked');
        echo $OUTPUT->footer();
    }

    /**
     * Stop web service functions (used by the Moodle app) from returning the content of
     * an attempt that may only be reviewed in Safe Exam Browser.
     *
     * @param \stdClass $function description of the web service function that is about to run.
     * @param array $params the validated parameters, in the order of the function's signature.
     * @throws \moodle_exception if the review is not allowed.
     */
    public static function check_webservice($function, array $params): void {
        global $CFG, $DB;

        if (
            !is_object($function) || !isset($function->name)
                || !in_array($function->name, self::GUARDED_WEBSERVICES, true)
        ) {
            return;
        }

        // The first parameter of all guarded functions is the attempt id.
        $params = array_values($params);
        $attemptid = isset($params[0]) ? (int) $params[0] : 0;
        if ($attemptid <= 0) {
            return;
        }

        $quizid = $DB->get_field('quiz_attempts', 'quiz', ['id' => $attemptid]);
        if (!$quizid || !self::is_required_for_quizid((int) $quizid)) {
            return;
        }

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quizobj = quiz_settings::create((int) $quizid);

        $blockinfo = self::get_block_info($quizobj);
        if ($blockinfo !== null) {
            $reason = $blockinfo['reason'] === self::REASON_SEB_NOT_CONFIGURED ? 'sebnotconfigured' : 'reviewrequiresseb';
            throw new \moodle_exception($reason, 'quizaccess_sebreview');
        }
    }
}
