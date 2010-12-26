<?php
include "Htmlizer.php";
class htmlizer_tester {
	public $tests_failed = 0;
	public $tests_total = 0;
	
	public $error_log = array();
	
	function normalize_new_lines($in) {
		$return = str_replace("\r", "\n", $in);
		$return = preg_replace('~\n~', $in);
	}
	
	function test($test_name, $plain_text, $expected_htmlized) {
		$this->tests_total++;
		
		$htmlizer = new Htmlizer();
		$actual_output = $htmlizer->htmlize($plain_text);
		$actual_output = ;
		
		if ($actual_output==$expected_htmlized) {
			return true;
		}
		$this->tests_failed++;
		$this->error($test_name, $plain_text, $expected_htmlized, $actual_output);
		return false;
	}
	function error($test_name, $plain_text, $expected_htmlized, $actual_output) {
		$separator = str_repeat('-', 80);
		$this->error_log[] = "Failed $test_name: \n$separator\nInput: $plain_text\n$separator\n".
					 "Expected: $expected_htmlized\n$separator\n" .
					 "Actual result: $actual_output";
	}
	function get_results() {
		echo "$this->tests_failed from $this->tests_total tests failed\n";
		if (count($this->error_log)) {
			print_r($this->error_log);
		}
	}
}
$tester = new htmlizer_tester();
/* bold ... */
$tester->test("Bolder", "test *test 1* test", "test <strong>test 1</strong> test");
$tester->test("Double Bolder", "test *test 1* test *test 2*", "test <strong>test 1</strong> test <strong>test 2</strong>");
$tester->test("Regular Star", "starred item*", "starred item*");
$tester->test("Regular Star", "*bold* starred item*", "<strong>bold</strong> starred item*");

/* header */
$tester->test("Header", "=head=", "<h2>head</h2>");
$tester->test("Header Inline", "test =head= test", "test =head= test");
$tester->test("Code", "test {{{test!}}}", 'test <div class="code">test!</div>');

$tester->test("Code", "test
{{{test!}}}
	
", 'test
<div class="code">test!</div>
');

echo $tester->get_results();
// echo $tester
?>