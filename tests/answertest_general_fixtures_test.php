<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk//
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
use stack_answertest_test_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Add in all the tests from answertestsfixtures.class into the unit testing framework.
 * These are exposed to users as documentation and google-ci should also run all the tests.
 *
 * @package    qtype_stack
 * @copyright  2016 The University of Edinburgh.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once(__DIR__ . '/../locallib.php');
require_once(__DIR__ . '/../stack/answertest/controller.class.php');
require_once(__DIR__ . '/../stack/options.class.php');
require_once(__DIR__ . '/fixtures/test_base.php');
require_once(__DIR__ . '/fixtures/answertestfixtures.class.php');

/**
 * Add description here.
 * @group qtype_stack
 * @covers \stack_answertest_general_cas
 * @covers \stack_anstest
 */
final class answertest_general_fixtures_test extends qtype_stack_testcase {

    /**
     * Run independent answer-test fixtures with up to four Maxima requests at once.
     *
     * @dataProvider answertest_fixtures
     * @param array $tests Answer-test fixtures.
     */
    public function test_answertest(array $tests): void {
        if (!class_exists(\Fiber::class) || \stack_connection_helper::get_platform() !== 'server') {
            foreach ($tests as [$name, $test]) {
                $this->check_answertest($name, $test);
            }

            return;
        }

        require_once(__DIR__ . '/../stack/cas/connector.server.class.php');
        $multi = curl_multi_init();
        curl_multi_setopt($multi, CURLMOPT_MAX_TOTAL_CONNECTIONS, 4);

        // Disable multiplexing so the connection limit also limits concurrent calculations.
        curl_multi_setopt($multi, CURLMOPT_PIPELINING, CURLPIPE_NOTHING);
        $requests = [];
        $previous = \stack_cas_connection_server::$testcurl;
        \stack_cas_connection_server::$testcurl = static function ($request) use ($multi, &$requests) {
            $status = curl_multi_add_handle($multi, $request);

            if ($status !== CURLM_OK) {
                throw new \RuntimeException(curl_multi_strerror($status));
            }

            $requests[spl_object_id($request)] = [\Fiber::getCurrent(), $request];

            return \Fiber::suspend();
        };

        try {
            foreach ($tests as [$name, $test]) {
                (new \Fiber(fn() => $this->check_answertest($name, $test)))->start();
            }

            while ($requests) {
                $status = curl_multi_exec($multi, $running);

                if ($status !== CURLM_OK) {
                    throw new \RuntimeException(curl_multi_strerror($status));
                }

                while ($info = curl_multi_info_read($multi)) {
                    $request = $info['handle'];
                    [$fiber] = $requests[spl_object_id($request)];
                    unset($requests[spl_object_id($request)]);
                    $response = curl_multi_getcontent($request);
                    curl_multi_remove_handle($multi, $request);

                    if ($info['result'] !== CURLE_OK) {
                        throw new \RuntimeException(curl_error($request));
                    }

                    $fiber->resume($response);
                }

                if ($requests && curl_multi_select($multi, 0.1) === -1) {
                    usleep(1000);
                }
            }
        } finally {
            \stack_cas_connection_server::$testcurl = $previous;

            foreach ($requests as [$fiber, $request]) {
                curl_multi_remove_handle($multi, $request);
            }

            curl_multi_close($multi);
        }
    }

    /**
     * Check one fixture using the normal answer-test implementation.
     *
     * @param string $name Fixture description.
     * @param \stdClass $test Answer-test fixture.
     */
    private function check_answertest(string $name, \stdClass $test): void {
        list($passed, $error, $rawmark, $feedback, $ansnote, $anomalynote) = stack_answertest_test_data::run_test($test);

        $this->assertEquals($test->ansnote, $ansnote, $name);
        $this->assertTrue($passed, $name . ': ' . $anomalynote);
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.MissingTestcaseMethodDescription
    public static function answertest_fixtures(): array {

        $tests = stack_answertest_test_data::get_all();
        $testdata = [];
        foreach ($tests as $test) {
            $testname = 'AT' . $test->name .
                    '( ' . $test->studentanswer . ', ' . $test->teacheranswer. ')';
            if ($test->options != '') {
                $testname .= ' Options: ' . $test->options;
            }
            $testdata[] = [$testname, $test];
        }
        // Share Moodle setup across small groups of independent answer-test fixtures.
        return array_map(static fn($tests) => [$tests], array_chunk($testdata, 25));
    }
}
