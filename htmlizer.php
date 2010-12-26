<?php
class Htmlizer {
	public $plain_text, $result_html;
	
	protected $filters = array(
		array('code_blocks', 'before_filter'),
		
		'header',
		'bold',
		
		'code_blocks'
	);
	
	function htmlize($plain_text) {
		$return = $plain_text;
		foreach ($this->filters as $filter) {
			$callback = Htmlizer_Filter::factory($filter);
			$return = call_user_func($callback, $return);
		}
		return $return;
	}
}
abstract class Htmlizer_Filter {
	static $classes = array();
	abstract function process($plain_text);
	
	static function factory($filter) {
		$method = 'process';
		if (is_array($filter)) {
			if(count($filter)==2) {
				list($filter_class, $method) = array_values($filter);
				$filter = $filter_class;
			} else {
				$error = "Cannot parse filter " . print_r($filter, 1);
				throw new Htmlizer_Filter_Exception($error);
			}
		}
		
		if (isset(self::$classes[$filter])) {
			return array(self::$classes[$filter], )
		}
		
		$filter_class = str_replace(' ', '', ucwords(str_replace('_', ' ', $filter)));
		$filter_class = 'Htmlizer_Filter_' . $filter_class;
		
		if (!class_exists($filter_class)) {
			$error = "Unknow filter $filter";
			throw new Htmlizer_Filter_Exception($error);
		}
		$filter_object = new $filter_class();
		
		self::$classes[$filter] = $filter_object;
		
		return array($filter_object, $method);
	}
}
class Htmlizer_Filter_Exception extends Exception {}

class Htmlizer_Filter_CodeBlocks extends Htmlizer_Filter {
	
	function before_filter() {
		
	}
	function process($plain_text) {
		
	}
}

class Htmlizer_Filter_Inline extends Htmlizer_Filter {
	protected $token = '', $replaced_start, $replaced_end;
	# particular elements like headings should be the only text on the line
	protected $whole_line_only = false;
	
	function build_regex() {
		$token = preg_quote($this->token);
		
		$flags = array();
		
		if (empty($token)) {
			$error = "Cannot determinate token for " . get_class($this) . " inline filter. ";
			throw new Htmlizer_Filter_Exception($error);
		}
		
		if (strlen($token)==1) {
			# "x([^x]*)x" is faster than "x(.*?)x" but it's working only for
			# one-char x
			$regex_core = $token . '[^' . $token . ']*' . $token;
		} else {
			$regex_core = $token . '(.*?)' . $token;
		}
		$regex_separator = '~';
		
		if ($this->whole_line_only) {
			# allow whitespace
			$regex_core = "^\s*$regex_core\s*$"; 
			$flags[] = "m"; # multi line so start of line and end of line works correctly
		}
		
		# escape the regex separator in the regex core -- that wat "~" token won't fail
		$this->regex = $regex_separator . 
				 str_replace($regex_separator, '\\' . $regex_separator, $regex_core) . 
				 $regex_separator . 
				 implode('', $flags);
	}
	
	function process($plain_text) {
		$this->build_regex();
		$replaced = preg_replace(
			$this->regex, 
			$this->replaced_start . '$1' . $this->replaced_end, 
			$plain_text
		);
		return $replaced;
	}
}
class Htmlizer_Filter_Bold extends Htmlizer_Filter_Inline {
	protected $token = '*', 
			  $replaced_start = '<strong>', 
			  $replaced_end = '</strong>';
}

class Htmlizer_Filter_Header extends Htmlizer_Filter_Inline {
	protected $token = '=', 
			  $replaced_start = '<h2>', 
			  $replaced_end = '</h2>';
	
    protected $whole_line_only = true;
}

/*
function linker($matches) {
	$link = html_entity_decode(str_replace('\\', '/', $matches[0]));
	# handle links with parenthese properly
	$rest = '';
	if (strpos($link, ')')!==false && strpos($link, '(')===false) {
		$rest = substr($link, strpos($link, ')'));
		$link = substr($link, 0, strpos($link, ')'));
	} else if (preg_match('~([\.",])$~', $link, $m)) {
		# dots at the end of the string are really not part of the url(in most cases)
		$link = substr($link, 0, -1);
		$rest = $m[1];
	}
	$link_location = $link;
	$link_repr = preg_replace('~([%/\?=:])~', '$1<wbr></wbr>', $link);
	$link_repr = wordwrap($link_repr, 120, '<wbr></wbr>', 1);
	return '<a href="' . $link_location . '" target="_blank">' . $link_repr . '</a>' . $rest;
}
function bolder($matches) {
	return '<strong>' . $matches[1] . '</strong>';
}


function italicer($matches) {
	if (isset($matches[1])) {
		return '<em>' . $matches[1] . '</em>';
	}
}
function do_quotes($match) {
    return "<em style='display: block; margin-top: 3px;'>$match[1]</em>";
}
function do_horizontal_lines($match) {
    return "\n" . '<div class="hr">&nbsp;</div>' . "\n";
}
function do_smilies($htmlized) {
	$smilies_icons = array(
		':-(' => 'sad',
		':-(' => 'sad',
		':-)' => 'smile',
		':-?' => 'confused',
		':-D' => 'biggrin',
		':-P' => 'razz',
		':-o' => 'surprised',
		':-x' => 'mad',
		':-|' => 'neutral',
		';-)' => 'wink',
		':('  => 'sad',
		':)'  => 'smile',
		':?'  => 'confused',
		':D'  => 'biggrin',
		':P'  => 'razz',
		':o'  => 'surprised',
		':x'  => 'mad',
		':|'  => 'neutral',
	);
	foreach ($smilies_icons as $emoticon => $image) {
		$htmlized = str_replace($emoticon, '<img src="/css/img/smilies/icon_'. $image . '.gif" alt="' . $emoticon . '" />', $htmlized);
	}
	return $htmlized;
}

class HTMLIze_code_blocks_fixer {
	public $tokens = array(
		'inline'=>array(),
		'block'=>array(),
	);
	
	// Step 1 replaces all block codes with tokens that won't be processed by other formatting functions
	function step1($plain_text) {
		$htmlized = preg_replace_callback('~`(.+?)`~', array($this, 'step1_inline_code'), $plain_text);
	    return preg_replace_callback('~\{\{\{(.+?)\}\}\}~s', array($this, 'step1_blocks_code'), $htmlized);
	}
	function memorize_code_fragment($code, $type) {
	    $token = md5(mt_rand() . $code . microtime(true));
		$this->tokens[$type][$token] = $code;
		return $token;
	}
	
	function step1_inline_code($matches) {
		return $this->memorize_code_fragment($matches[1], 'inline');
	}
	
	function step1_blocks_code($matches) {
		return $this->memorize_code_fragment($matches[1], 'block');
	}
	
	// adds the code block to it's place
	function step2($htmlized) {
		foreach ($this->tokens['block'] as $token=>$body) {
			$body = '<div class="code"><pre class="prettyprint">' . trim($body) . '</pre></div>';
			$htmlized = str_replace($token, $body, $htmlized);
		}
		foreach ($this->tokens['inline'] as $token=>$body) {
			$body = '<code>' . trim($body) . '</code>';
			$htmlized = str_replace($token, $body, $htmlized);
		}
		return $htmlized;
	}
}
function do_list_elements($matches) {
    return '<li>' . $matches[1] . '</li>';
}
function do_ol_list_elements($matches) {
    return '<oli>' . $matches[1] . '</oli>';
}
function do_unordered_lists($matches) {
    return '<ul>' . trim($matches[0]) . '</ul>' . "\n";
}
function do_ordered_lists($matches) {
    return '<ol>' . trim(str_replace(
    	array('<oli>', '</oli>'), 
    	array('<li>', '</li>'), 
    	$matches[0]
    )) . '</ol>' . "\n";
}

function do_headings($matches) {
    return "<h3>" . trim($matches[1]) . "</h3>";
}
// alternative to nl2br -- taken from WordPress
function auto_p($html, $br = 1) {
	if ( trim($html) === '' )
		return '';
	$html = $html . "\n"; // just to make things a little easier, pad the end
	$html = preg_replace('|<br />\s*<br />|', "\n\n", $html);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
	$html = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $html);
	$html = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $html);
	$html = str_replace(array("\r\n", "\r"), "\n", $html); // cross-platform newlines
	if ( strpos($html, '<object') !== false ) {
		$html = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $html); // no pee inside object/embed
		$html = preg_replace('|\s*</embed>\s*|', '</embed>', $html);
	}
	$html = preg_replace("/\n\n+/", "\n\n", $html); // take care of duplicates
	// make paragraphs, including one at the end
	$htmls = preg_split('/\n\s*\n/', $html, -1, PREG_SPLIT_NO_EMPTY);
	$html = '';
	foreach ( $htmls as $tinkle )
		$html .= '<p>' . trim($tinkle, "\n") . "</p>\n";
	$html = preg_replace('|<p>\s*</p>|', '', $html); // under certain strange conditions it could create a P of entirely whitespace
	$html = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $html);
	$html = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $html); // don't pee all over a tag
	$html = preg_replace("|<p>(<li.+?)</p>|", "$1", $html); // problem with nested lists
	$html = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $html);
	$html = str_replace('</blockquote></p>', '</p></blockquote>', $html);
	$html = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $html);
	$html = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $html);
	if ($br) {
		$html = preg_replace_callback('/<(script|style).*?<\/\\1>/s', create_function('$matches', 'return str_replace("\n", "<WPPreserveNewline />", $matches[0]);'), $html);
		$html = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $html); // optionally make line breaks
		$html = str_replace('<WPPreserveNewline />', "\n", $html);
	}
	$html = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $html);
	$html = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $html);
	if (strpos($html, '<pre') !== false)
		$html = preg_replace_callback('!(<pre[^>]*>)(.*?)</pre>!is', 'clean_pre', $html );
	$html = preg_replace( "|\n</p>$|", '</p>', $html );

	return $html;
}
function htmlize($plain_text) {
	
	// return "<pre>$plain_text</pre>";
	$html_checklist_html = '<p class="checklist"><strong>HTML Checklist:</strong><br /> https://trac.2c-studio.com/wiki/html-checklist </p>';
	$ssi_html_checklist_html = '<p class="checklist"><strong>WordPress</strong> HTML Checklist<br /> https://trac.2c-studio.com/wiki/WordPress/HTMLGuide </p>';
	$wp_checklist_html = '<p class="checklist"><strong>WP Checklist:</strong><br /> https://trac.2c-studio.com/wiki/wordpress-pre-commit-checks </p>';
	
	$code_blocks_fixer = new HTMLIze_code_blocks_fixer();
	
	$plain_text = $code_blocks_fixer->step1($plain_text);
	
	$htmlized = str_replace('[html-checklist]', $html_checklist_html, $plain_text);
	$htmlized = str_replace('[ssi-html-checklist]', $ssi_html_checklist_html, $htmlized);
	$htmlized = str_replace('[wp-checklist]', $wp_checklist_html, $htmlized);
	
	$htmlized = preg_replace_callback('~((file:|mailto\:|(news|(ht|f)tp(s?))\://){1}[^\*\s"\'\[\]]+)~', 'linker', $htmlized);
	
	// $htmlized = preg_replace_callback('~^\s*([A-Z0-9]+)\s*$~m', 'do_headings', $htmlized);

	

	$htmlized = preg_replace_callback('~\*+(.+?)\*+~', 'bolder', $htmlized);
	$htmlized = preg_replace_callback('~([A-Z]+:)~', 'bolder', $htmlized);

	$htmlized = preg_replace_callback('~^\s*=(.+)=\s*$~m', 'do_headings', $htmlized);
	
	$htmlized = preg_replace_callback('~^((&gt;|>){2}.+$\s*)~m', 'do_quotes', $htmlized);
	
	$htmlized = preg_replace_callback('~\s*^-{3,}\s*~m', 'do_horizontal_lines', $htmlized);
	
	$htmlized = preg_replace_callback('~\s*^\s*[\-\*â€¢Â·â€¢] ?(.*?)$~um', 'do_list_elements', $htmlized);
	$htmlized = preg_replace_callback('~(<li>.*?</li>\s*)+~', 'do_unordered_lists', $htmlized);
	
	$htmlized = preg_replace_callback('~^\s*#\s*?(.*?)$~um', 'do_ol_list_elements', $htmlized);
	$htmlized = preg_replace_callback('~(<oli>.*?</oli>\s*)+~', 'do_ordered_lists', $htmlized);
	

	$htmlized = do_smilies($htmlized);
	
	$htmlized = auto_p($htmlized);
	
	$htmlized = $code_blocks_fixer->step2($htmlized);
	
	return $htmlized;
}
*/
?>