<?php
$test_string = "test test
 * item 1
 * item 1 and a half
 * item 2
 ** sub item 2.1
 ** sub item 2.2
 *** sub sub item 2.2.1
 *** sub sub item 2.2.2
 
 ** list subitem3
 * list item2
 
 te
 sadasad
 asdas
";
function replace_call($m) {
	print_r($m);
}
function replace_lists($plain_text) {
	$regex = '~([\n\r] \*(.*))+~';
	if (preg_match($regex, $plain_text, $matches)) {
		$inner_text = trim($matches[0]);
		
		$inner_plain_text = preg_replace('~^(\s*)\*~m', '$1', $inner_text);
		if(preg_match($regex, $inner_plain_text)) {
			$inner_text = preg_replace('~^(\s*)\*~m', replace_lists($inner_plain_text), $inner_text);
		}
	} else {
		return false;
	}
	
	return '<ul>' . str_replace(array("\n", "\r"), "", preg_replace('~^(.*)$~m', '<li>$1</li>', $inner_text)) . '</ul>';
	// preg_replace_callback(, 'replace_call', $test_string);
}
echo replace_lists($test_string);

?>