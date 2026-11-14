<?php
// This file is part of STACK - http://stack.maths.ed.ac.uk//
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_stack;

use qtype_stack_testcase;
use stack_inputvalidation_test_data;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');
require_once(__DIR__ . '/../stack/answertest/controller.class.php');
require_once(__DIR__ . '/../stack/options.class.php');
require_once(__DIR__ . '/fixtures/test_base.php');
require_once(__DIR__ . '/fixtures/inputfixtures.class.php');

/**
 * Add in all the tests from studentinputs.php into the unit testing framework.
 * These are exposed to users as documentation and google-ci should also run all the tests.
 *
 * @package    qtype_stack
 * @copyright 2016 The University of Edinburgh.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @group qtype_stack
 * @covers \stack_input
 */
final class studentinput_test extends qtype_stack_testcase {

    /**
     * Check independent student-input fixtures in small concurrent groups.
     *
     * @dataProvider studentinput_fixtures
     * @param array $tests Student-input fixtures.
     */
    public function test_studentinput(array $tests): void {
        $this->run_concurrently($tests, function ($test) {
            $result = stack_inputvalidation_test_data::run_test($test);

            $this->assert_equals_ignore_spaces_and_e($result->display, $result->casdisplay);
            $this->assertEquals($result->ansnotes, $result->casnotes, $test->rawstring);
            $this->assertTrue($result->passed, $test->rawstring);
        });
    }

    /**
     * Group the typeless, units and both decimal-separator fixtures.
     *
     * @return array Fixture groups.
     */
    public static function studentinput_fixtures(): array {
        $tests = stack_inputvalidation_test_data::get_all();
        foreach ([1, 2] as $decimals) {
            foreach (stack_inputvalidation_test_data::get_raw_test_data_decimals() as $data) {
                $tests[] = stack_inputvalidation_test_data::test_decimals_from_raw($data, $decimals);
            }
        }
        return array_map(static fn($tests) => [$tests], array_chunk($tests, 25));
    }
}
