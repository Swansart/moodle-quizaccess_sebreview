# Safe Exam Browser for quiz review (quizaccess_sebreview)

A quiz access rule for Moodle. It adds one option to the quiz settings:

**Require Safe Exam Browser for review**

Moodle's standard Safe Exam Browser (SEB) support protects a quiz attempt, but not the
review of the attempt afterwards. Once the review is released, students can open it in
any browser, take screenshots and copy the questions. With this option switched on,
students can open the review of their attempts only in Safe Exam Browser.

## What it does

For a quiz with the option switched on:

- A student who opens the review outside Safe Exam Browser sees a notice instead of the
  questions, together with the standard buttons to start Safe Exam Browser.
- The same applies to the page that shows a single question of a finished attempt.
- The Moodle app cannot load the review either. The web service that the app uses is
  refused for these quizzes.
- Inside Safe Exam Browser, the review works as usual.
- Teachers are not affected (see "Who is exempt").

The plugin does not check Safe Exam Browser itself. It asks the standard Safe Exam Browser
rule of Moodle (quizaccess_seb), so the review is protected by exactly the same check as the
attempt: the Config Key, the Browser Exam Key or the user agent, depending on how Safe Exam
Browser is set up for the quiz. Both ways in which Safe Exam Browser hands over its keys are
supported, HTTP headers and the JavaScript interface used on macOS and iOS.

## Requirements

- Moodle 4.4 to 5.3 (tested on 4.4, 4.5, 5.0, 5.1, 5.2 and 5.3)
- The standard plugin "Safe Exam Browser access rules" (quizaccess_seb), which is part of Moodle

## Installation

Install the ZIP file under *Site administration > Plugins > Install plugins*, or unpack it
into `mod/quiz/accessrule/sebreview` (`public/mod/quiz/accessrule/sebreview` from Moodle 5.1)
and visit *Site administration > Notifications*.

The plugin creates one small table, `quizaccess_sebreview`, with one row per quiz that uses
the option. It has no site-wide settings and changes nothing for existing quizzes.

## Use

1. In the quiz settings, section *Safe Exam Browser*, require Safe Exam Browser for the quiz.
2. In the same section, tick *Require Safe Exam Browser for review*.

The option can only be saved if Safe Exam Browser is required for the quiz. Unlike the other
Safe Exam Browser settings, it can still be changed after the quiz has been attempted, so it
can be switched on for a quiz that students have already taken.

To review, a student opens the quiz in Safe Exam Browser (for example with the button
*Launch Safe Exam Browser* on the notice page), logs in and chooses *Review* on the quiz page.

Safe Exam Browser starts with the configuration of the quiz. Whether and how students can
quit Safe Exam Browser after the review therefore depends on the quit settings of the quiz
(*Enable quitting of SEB*, *Quit password*).

## Who is exempt

Users with at least one of these capabilities in the quiz are never restricted:

- `quizaccess/seb:bypassseb` (bypass the requirement to use Safe Exam Browser)
- `mod/quiz:viewreports` (view the quiz reports)
- `mod/quiz:preview` (preview the quiz)

With the standard roles these are managers, teachers and non-editing teachers.

## Behaviour in special cases

- **The option is on, but Safe Exam Browser is not required for the quiz.** This cannot be
  saved in the quiz form, but can result from a restore. The review then stays closed for
  students and they see a notice asking them to contact the teacher.
- **Backup, restore, duplication.** The option is included.
- **Deleting a quiz** removes its row from the table.

## Limits

- The review is as well protected as the attempt, not better. A student who can defeat the
  Safe Exam Browser check of the quiz itself can also open the review. With the setting
  "Yes – Use SEB client config" Moodle only checks the user agent, which is easy to forge.
- Images and other files embedded in questions are served by Moodle through a separate
  script that does not involve the quiz access rules, during the attempt as well as during
  the review. A student who knows the exact address of such a file can load it outside Safe
  Exam Browser while the review is released. Safe Exam Browser does not normally show these
  addresses.
- Safe Exam Browser itself decides what a student can do on the device (screenshots, second
  screen, virtual machines). This plugin only makes sure that the review is delivered to
  Safe Exam Browser and to nothing else.

## Privacy

The plugin stores one setting per quiz and no personal data.

## Tests

The plugin comes with PHPUnit tests:

    vendor/bin/phpunit --testsuite quizaccess_sebreview_testsuite

## Licence

GNU GPL v3 or later.
