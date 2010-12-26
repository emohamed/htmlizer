<?phpENT_NOQUOTES;
class Htmlizer {
	public $plain_text, $result_html;
	
	protected $filters = array(
		'html_special_chars',
		
		array('code_blocks', 'before_filter'),
		array('inline_code', 'before_filter'),
		
		'header',
		'bold',
		'super_script',
		'sub_script',
		'link',
		'auto_p',
		'list',
		
		'code_blocks',
		'inline_code',
	);
	
	function htmlize($plain_text) {
		$return = $plain_text;
		foreach ($this->filters as $filter) {
			$callback = Htmlizer_Filter::factory($filter);
			if (!is_callable($callback)) {
				throw new Exception("Invalid callback");
			}
			$return = call_user_func($callback, $return);
		}
		return $return;
	}
}
abstract class Htmlizer_Filter {
	static $classes = array();
	
	protected $start_token, 
			  $end_token, 
			  $replaced_start, 
			  $replaced_end;
	
	# particular elements like headings should be the only text on the line
	protected $whole_line_only = false;
	protected $block_element = false;
	
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
			return array(self::$classes[$filter], $method);
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
	
	function build_regex() {
		$start_token = preg_quote($this->start_token);
		$end_token = preg_quote($this->end_token);
		
		$is_greedy = true;
		if ($this->whole_line_only) {
			$is_greedy = false;
		}
		$flags = array();
		
		if (empty($start_token)) {
			$error = "Cannot determinate token for " . get_class($this) . " inline filter. ";
			throw new Htmlizer_Filter_Exception($error);
		}
		
		$regex_core = $start_token . '(.+' . ($is_greedy ? '?' : '') . ')' . $end_token;
		
		$regex_separator = '~';
		
		if ($this->whole_line_only) {
			# allow whitespace
			$regex_core = "^\s*$regex_core\s*$"; 
			$flags[] = "m"; # multi line so start of line and end of line works correctly
		} elseif ($this->block_element) {
			$flags[] = "s"; # dot all 
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
class Htmlizer_Filter_Exception extends Exception {}
class Htmlizer_Filter_HtmlSpecialChars extends Htmlizer_Filter {
	function process($filter) {
		return htmlspecialchars($filter, ENT_NOQUOTES);
	}
}
class Htmlizer_Filter_AutoP extends Htmlizer_Filter {
	/**
	 * Accepts matches array from preg_replace_callback in wpautop() or a string.
	 *
	 * Ensures that the contents of a <<pre>>...<</pre>> HTML block are not
	 * converted into paragraphs or line-breaks.
	 *
	 * @param array|string $matches The array or string
	 * @return string The pre block without paragraph/line-break conversion.
	 */
	function clean_pre($matches) {
		if ( is_array($matches) )
			$text = $matches[1] . $matches[2] . "</pre>";
		else
			$text = $matches;
	
		$text = str_replace('<br />', '', $text);
		$text = str_replace('<p>', "\n", $text);
		$text = str_replace('</p>', '', $text);
	
		return $text;
	}
	// Borrowed from WordPress
	function process($pee) {
		if ( trim($pee) === '' )
			return '';
		$pee = $pee . "\n"; // just to make things a little easier, pad the end
		$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
		// Space things out a little
		$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
		$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
		$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
		$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
		if ( strpos($pee, '<object') !== false ) {
			$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
			$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
		}
		$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
		// make paragraphs, including one at the end
		$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
		$pee = '';
		foreach ( $pees as $tinkle )
			$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
		$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
		$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
		$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
		$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
		$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
		$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
		if (strpos($pee, '<pre') !== false)
			$pee = preg_replace_callback('!(<pre[^>]*>)(.*?)</pre>!is', 'clean_pre', $pee );
		$pee = preg_replace( "|\n</p>$|", '</p>', $pee );
		$pee = preg_replace( "|\s+$|", '', $pee );
	
		return $pee;
	}
}

class Htmlizer_Filter_CodeBlocks extends Htmlizer_Filter {
	public $start_token = '{{{', $end_token = '}}}',
		   $replaced_start = '<div class="code">', $replaced_end = '</div>';
	
	protected $block_element=true;
	
	public $code_blocks_references = array();
	function initial_replace_callback($matches) {
		$code = $matches[1];
		$code_block_id = md5(mt_rand() . $code);
		
		$this->code_blocks_references[$code_block_id] = $code;
		return $code_block_id;
	}
	function before_filter($plain_text) {
		$this->build_regex();
		return preg_replace_callback($this->regex, array($this, 'initial_replace_callback'), $plain_text);
	}
	function process($plain_text) {
		foreach ($this->code_blocks_references as $code_block_id=>$code) {
			$plain_text = str_replace(
				$code_block_id, 
				$this->replaced_start . $code . $this->replaced_end, 
				$plain_text
			);
		} 
		return $plain_text;
	}
}

class Htmlizer_Filter_InlineCode extends Htmlizer_Filter_CodeBlocks {
	public $start_token = '`', $end_token = '`',
		   $replaced_start = '<code>', $replaced_end = '</code>';
	
	protected $block_element=true;
}

class Htmlizer_Filter_Inline extends Htmlizer_Filter {
	
}
class Htmlizer_Filter_List extends Htmlizer_Filter {
	function replace_callback($matches) {
		print_r($matches);
		exit;
	}
	function process($plain_text) {
		return preg_replace_callback(
			'~(^\s*\*(.*))+~m', 
			array($this, 'replace_callback'),
			$plain_text
		);
	}
}
class Htmlizer_Filter_Link extends Htmlizer_Filter {
	function replace_callback($matches) {
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
		// $link_repr = preg_replace('~([%/\?=:])~', '$1<wbr></wbr>', $link);
		$link_repr = $link;
		$link_repr = wordwrap($link_repr, 120, '<wbr></wbr>', 1);
		return '<a href="' . $link_location . '" target="_blank">' . $link_repr . '</a>' . $rest;
	}
	
	function process($plain_text) {
		return preg_replace_callback(
			'~((file:|mailto\:|(news|(ht|f)tp(s?))\://){1}[^\*\s"\'\[\]]+)~',
			array($this, 'replace_callback'),
			$plain_text
		);
	}
}

class Htmlizer_Filter_Bold extends Htmlizer_Filter_Inline {
	protected $start_token = '*', $end_token = '*', 
			  $replaced_start = '<strong>', 
			  $replaced_end = '</strong>';
}

class Htmlizer_Filter_Header extends Htmlizer_Filter_Inline {
	protected $start_token = '=', $end_token = '=', 
			  $replaced_start = '<h2>', 
			  $replaced_end = '</h2>';
	
    protected $whole_line_only = true;
}

class Htmlizer_Filter_SuperScript extends Htmlizer_Filter_Inline{
	protected $start_token = '^', $end_token = '^', 
			  $replaced_start = '<sup>', 
			  $replaced_end = '</sup>';
}
class Htmlizer_Filter_SubScript extends Htmlizer_Filter_Inline{
	protected $start_token = '~', $end_token = '~', 
			  $replaced_start = '<sub>', 
			  $replaced_end = '</sub>';
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

function do_quotes($match) {
    return "<em style='display: block; margin-top: 3px;'>$match[1]</em>";
}
function do_horizontal_lines($match) {
    return "\n" . '<div class="hr">&nbsp;</div>' . "\n";
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
*/
?>