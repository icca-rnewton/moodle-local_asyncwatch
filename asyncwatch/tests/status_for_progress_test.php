<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_asyncwatch;

/**
 * Regression tests for helper::status_for_progress().
 *
 * Added after external review found parts-mode warnings targeting the
 * wrong learners (nearly-finished learners flagged before the deadline,
 * instead of unfinished learners graded at the deadline).
 *
 * @package    local_asyncwatch
 * @copyright  2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_asyncwatch\helper::status_for_progress
 */
final class status_for_progress_test extends \basic_testcase {

    /** @var int Fixed deadline used by every case. */
    private const DEADLINE = 1800000000;

    /**
     * Build a minimal rule object.
     *
     * @param string $mode 'time' or 'parts'
     * @param int $gap warn_parts_gap
     * @return \stdClass
     */
    private function rule(string $mode, int $gap = 0): \stdClass {
        return (object)['parts_required' => 10, 'warn_mode' => $mode, 'warn_parts_gap' => $gap];
    }

    /**
     * Parts mode, 10 required, minimum 7 (gap 3) — the reviewer's example.
     *
     * @return array
     */
    public static function parts_mode_provider(): array {
        return [
            'before deadline, 0 done'  => [0, -100, 'ok'],
            'before deadline, 9 done'  => [9, -100, 'ok'],
            'after deadline, 0 done'   => [0, 100, 'breach'],
            'after deadline, 6 done'   => [6, 100, 'breach'],
            'after deadline, 7 done'   => [7, 100, 'warning'],
            'after deadline, 9 done'   => [9, 100, 'warning'],
            'exactly at deadline, 7'   => [7, 0, 'warning'],
            'exactly at deadline, 6'   => [6, 0, 'breach'],
            'completed before'         => [10, -100, 'completed'],
            'completed after'          => [10, 100, 'completed'],
        ];
    }

    /**
     * @dataProvider parts_mode_provider
     * @param int $done Parts completed
     * @param int $offset Seconds relative to the deadline
     * @param string $expected Expected status
     */
    public function test_parts_mode(int $done, int $offset, string $expected): void {
        $status = helper::status_for_progress($this->rule('parts', 3), $done, self::DEADLINE + $offset, self::DEADLINE, 0);
        $this->assertSame($expected, $status);
    }

    /**
     * Parts mode with the warning disabled (gap 0): plain On track / Behind.
     */
    public function test_parts_mode_no_gap(): void {
        $rule = $this->rule('parts', 0);
        $this->assertSame('ok', helper::status_for_progress($rule, 9, self::DEADLINE - 100, self::DEADLINE, 0));
        $this->assertSame('breach', helper::status_for_progress($rule, 9, self::DEADLINE + 100, self::DEADLINE, 0));
    }

    /**
     * An override's own band replaces the rule's; null inherits it.
     */
    public function test_parts_mode_override_band(): void {
        $rule  = $this->rule('parts', 3); // Rule: At risk from 7.
        $after = self::DEADLINE + 100;
        // Rule band: 8 done is At risk.
        $this->assertSame('warning', helper::status_for_progress($rule, 8, $after, self::DEADLINE, 0, null));
        // Override band of 1 (At risk from 9): 8 done is Behind, 9 is At risk.
        $this->assertSame('breach', helper::status_for_progress($rule, 8, $after, self::DEADLINE, 0, 1));
        $this->assertSame('warning', helper::status_for_progress($rule, 9, $after, self::DEADLINE, 0, 1));
        // Override band of 5 (At risk from 5): 5 done is At risk.
        $this->assertSame('warning', helper::status_for_progress($rule, 5, $after, self::DEADLINE, 0, 5));
    }

    /**
     * Time mode must be unchanged by the parts-mode fix.
     */
    public function test_time_mode_unchanged(): void {
        $rule = $this->rule('time');
        // 60-minute warning window.
        $this->assertSame('ok', helper::status_for_progress($rule, 5, self::DEADLINE - 7200, self::DEADLINE, 60));
        $this->assertSame('warning', helper::status_for_progress($rule, 5, self::DEADLINE - 1800, self::DEADLINE, 60));
        $this->assertSame('breach', helper::status_for_progress($rule, 5, self::DEADLINE + 100, self::DEADLINE, 60));
        $this->assertSame('completed', helper::status_for_progress($rule, 10, self::DEADLINE + 100, self::DEADLINE, 60));
    }
}