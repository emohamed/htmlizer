<?php
include "Htmlizer.php";

error_reporting(E_ALL);
// ini_set('display_errors', 1);
// phpinfo();
// exit;

class htmlizer_tester {
	public $tests_failed = 0;
	public $tests_total = 0;
	
	public $error_log = array();
	
	function normalize_new_lines($in) {
		$return = str_replace("\r", "\n", $in);
		$return = preg_replace('~\n~', $in);
	}
	
	function should_match($test_name, $plain_text, $expected_htmlized) {
		$this->tests_total++;
		
		$htmlizer = new Htmlizer();
		$actual_output = $htmlizer->htmlize($plain_text);
		# hack it so the XML parser doesn't complain about missing root element
		$dom = DomDocument::loadXml("<root>" . $actual_output . "</root>");
		if (!$dom) {
			var_dump($plain_text);
			var_dump($actual_output);
			exit;
			$this->tests_failed++;
			$this->error($test_name, $plain_text, $expected_htmlized, $actual_output, "bad HTML");
			return false;
		}
		
		if ($actual_output==$expected_htmlized) {
			return true;
		}
		$this->tests_failed++;
		$this->error($test_name, $plain_text, $expected_htmlized, $actual_output);
		return false;
	}
	function error($test_name, $plain_text, $expected_htmlized, $actual_output, $error='') {
		$separator = str_repeat('-', 80);
		$this->error_log[] = "Failed $test_name: $error\n$separator\nInput: $plain_text\n$separator\n".
					 "Expected: \"$expected_htmlized\"\n$separator\n" .
					 "Actual result: \"$actual_output\"";
	}
	function get_results() {
		echo "$this->tests_failed from $this->tests_total tests failed\n";
		if (count($this->error_log)) {
			print_r($this->error_log);
		}
	}
}
$tester = new htmlizer_tester();

$tester->should_match(
	"HTML escape functionality", 
	"test <script src=\"http://evil.coom/\"></script> test", 
	"<p>test &lt;script src=\"<a href=\"http://evil.coom/\" target=\"_blank\">http://evil.coom/</a>\"&gt;&lt;/script&gt; test</p>"
);

/* bold ... */
$tester->should_match("Bolder", "test *test 1* test", "<p>test <strong>test 1</strong> test</p>");
$tester->should_match("Double Bolder", "test *test 1* test *test 2*", "<p>test <strong>test 1</strong> test <strong>test 2</strong></p>");
$tester->should_match("Regular Star", "starred item*", "<p>starred item*</p>");
$tester->should_match("Regular Star", "*bold* starred item*", "<p><strong>bold</strong> starred item*</p>");

/* header */
$tester->should_match("Header", "=head=", "<h2>head</h2>");
$tester->should_match("Header Inline", "test =head= test", "<p>test =head= test</p>");
$tester->should_match("Code", "test \n{{{test!}}}", '<p>test ' . "\n" . '<div class="code">test!</div></p>');

/*  auto p */
$tester->should_match("AutoP test 1", "test", "<p>test</p>");
$tester->should_match("AutoP test 2", "test2 \n\n test2", "<p>test2 </p>\n<p> test2</p>");
$tester->should_match("AutoP test 2", "test2 \n test2", "<p>test2 \n test2</p>");

/*  super & sub script */
$tester->should_match("Super Script", "test ^test^ test", "<p>test <sup>test</sup> test</p>");
$tester->should_match("Sub Script", "test ~test~ test", "<p>test <sub>test</sub> test</p>");

$tester->should_match("Code block", "test {{{test!}}}", '<p>test <div class="code">test!</div></p>');
$tester->should_match("Code inline", "test `test!`", '<p>test <code>test!</code></p>');
$tester->should_match("Markup in code block", "test {{{*test!*}}}", '<p>test <div class="code">*test!*</div></p>');
$tester->should_match("Markup in inline code", "test `*test!*`", '<p>test <code>*test!*</code></p>');
$tester->should_match("Actual  markup in block code", 
	"test {{{<script src=\"http://evil.cooom/\"></script>}}}", 
	"<p>test <div class=\"code\">&lt;script src=\"http://evil.cooom/\"&gt;&lt;/script&gt;</div></p>"
);

/* links ...  */
$tester->should_match("Basic link", 
	"test http://google.com", 
	"<p>test <a href=\"http://google.com\" target=\"_blank\">http://google.com</a></p>"
);
$tester->should_match("Basic link", 
	"test http://google.com", 
	"<p>test <a href=\"http://google.com\" target=\"_blank\">http://google.com</a></p>"
);
$tester->should_match("Link with dot at the end", 
	"test http://google.com/wave/.", 
	"<p>test <a href=\"http://google.com/wave/\" target=\"_blank\">http://google.com/wave/</a>.</p>"
);
$tester->should_match("Link with question mark at the end", 
	"test http://google.com/wave/?", 
	"<p>test <a href=\"http://google.com/wave/?\" target=\"_blank\">http://google.com/wave/?</a></p>"
);
$tester->should_match("Link with braces around", 
	"test (http://google.com/wave/)", 
	"<p>test (<a href=\"http://google.com/wave/\" target=\"_blank\">http://google.com/wave/</a>)</p>"
);

$tester->should_match("Link with braces in", 
	"Bison rocks http://en.wikipedia.org/wiki/Bison_(comics)", 
	"<p>Bison rocks <a href=\"http://en.wikipedia.org/wiki/Bison_(comics)\" target=\"_blank\">http://en.wikipedia.org/wiki/Bison_(comics)</a></p>"
);

/*
$tester->should_match("Embed link", 
	"Bison rocks [Bison http://en.wikipedia.org/wiki/Bison_(comics)]", 
	"<p>Bison rocks <a href=\"http://en.wikipedia.org/wiki/Bison_(comics)\" target=\"_blank\">Bison</a></p>"
);
*/
# lists
$tester->should_match("Basic unordered lists", 
	"test test\n * test1\n * test2\n * test3", 
	"<p>test test</p>\n<ul>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ul>"
);
$tester->should_match("Basic ordered lists", 
	"test test\n # test1\n # test2\n # test3", 
	"<p>test test</p>\n<ol>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ol>"
);

$tester->should_match("Both lists ordered lists", 
	"test test\n # test1\n # test2\n # test3\n\n\n* test1\n * test2\n * test3", 
	"<p>test test</p>\n<ol>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ol>\n<ul>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ul>"
);

$tester->should_match("Bad formatted lists", 
	"test test\n # test1\n # test2\n # test3\n* test1\n * test2\n * test3", 
	"<p>test test</p>\n<ol>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ol>\n<ul>\n<li>test1</li>\n<li>test2</li>\n<li>test3</li>\n</ul>"
);
// $tester->should_match("Sub Script", "test ~test~ test", "<p>test <sub>test</sub> test</p>");
// $tester->test("Code", "test {{{test!}}}", '<p>test <div class="code">test!</div></p>');

echo $tester->get_results();
// echo $tester
?>