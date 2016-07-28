<?php
//
// Modified from includes/unicode.php in Drupal v4.7.3 (http://www.drupal.org)
//
define('UNICODE_ERROR', -1);
define('UNICODE_SINGLEBYTE', 0);
define('UNICODE_MULTIBYTE', 1);

# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
* Wrapper around _unicode_check().
*/
function unicode_check() {
	$GLOBALS['multibyte'] = _unicode_check();
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
* Perform checks about Unicode support in PHP, and set the right settings if
* needed.
*
* Because Drupal needs to be able to handle text in various encodings, we do
* not support mbstring function overloading. HTTP input/output conversion must
* be disabled for similar reasons.
*
* @param $errors
*   Whether to report any fatal errors with form_set_error().
*/
function _unicode_check($errors = false) {
// Set the standard C locale to ensure consistent, ASCII-only string handling.
	setlocale(LC_CTYPE, 'C');
	
	// Check for outdated PCRE library
	// Note: we check if U+E2 is in the range U+E0 - U+E1. This test returns TRUE on old PCRE versions.
	if (preg_match('/[à-á]/u', 'â')) {
		if ($errors) {
			$GLOBALS["UNICODE_ERROR"] = 'The PCRE library in your PHP installation is outdated. This will cause problems when handling Unicode text. If you are running PHP 4.3.3 or higher, make sure you are using the PCRE library supplied by PHP. Please refer to the <a href="http://www.php.net/pcre">PHP PCRE documentation</a> for more information.';
		}
		return UNICODE_ERROR;
	}
	
	// Check for mbstring extension
	if (!function_exists('mb_strlen')) {
		return UNICODE_SINGLEBYTE;
	}
	
	// Check mbstring configuration
	if (ini_get('mbstring.func_overload') != 0) {
		if ($errors) {
			$GLOBALS["UNICODE_ERROR"] = 'Multibyte string function overloading in PHP is active and must be disabled. Check the php.ini <em>mbstring.func_overload</em> setting. Please refer to the <a href="http://www.php.net/mbstring">PHP mbstring documentation</a> for more information.';
		}
		return UNICODE_ERROR;
	}
	if (ini_get('mbstring.encoding_translation') != 0) {
		if ($errors) {
			$GLOBALS["UNICODE_ERROR"] = 'Multibyte string input conversion in PHP is active and must be disabled. Check the php.ini <em>mbstring.encoding_translation</em> setting. Please refer to the <a href="http://www.php.net/mbstring">PHP mbstring documentation</a> for more information.';
		}
		return UNICODE_ERROR;
	}
	if (ini_get('mbstring.http_input') != 'pass') {
		if ($errors) {
			$GLOBALS["UNICODE_ERROR"] = 'Multibyte string input conversion in PHP is active and must be disabled. Check the php.ini <em>mbstring.http_input</em> setting. Please refer to the <a href="http://www.php.net/mbstring">PHP mbstring documentation</a> for more information.';
		}
		return UNICODE_ERROR;
	}
	if (ini_get('mbstring.http_output') != 'pass') {
		if ($errors) {
			$GLOBALS["UNICODE_ERROR"] = 'Multibyte string output conversion in PHP is active and must be disabled. Check the php.ini <em>mbstring.http_output</em> setting. Please refer to the <a href="http://www.php.net/mbstring">PHP mbstring documentation</a> for more information.';
		}
		return UNICODE_ERROR;
	}
	
	// Set appropriate configuration
	mb_internal_encoding('utf-8');
	mb_language('uni');
	return UNICODE_MULTIBYTE;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
* Return the required Unicode status and errors for admin/settings.
*/
function unicode_settings() {
	$status = _unicode_check(true);
	$options = array(
		UNICODE_SINGLEBYTE => 'Standard PHP: operations on Unicode strings are emulated on a best-effort basis. Install the <a href="http://www.php.net/mbstring">PHP mbstring extension</a> for improved Unicode support.',
		UNICODE_MULTIBYTE => 'Multi-byte: operations on Unicode strings are supported through the <a href="http://www.php.net/mbstring">PHP mbstring extension</a>.',
		UNICODE_ERROR => 'Invalid: the current configuration is incompatible with Drupal.'
	);
	$form['settings'] = array('#type' => 'item', '#title' => t('String handling method'), '#value' => $options[$status]);
	return $form;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Prepare a new XML parser.
 *
 * This is a wrapper around xml_parser_create() which extracts the encoding from
 * the XML data first and sets the output encoding to UTF-8. This function should
 * be used instead of xml_parser_create(), because PHP 4's XML parser doesn't
 * check the input encoding itself. "Starting from PHP 5, the input encoding is
 * automatically detected, so that the encoding parameter specifies only the
 * output encoding."
 *
 * This is also where unsupported encodings will be converted. Callers should
 * take this into account: $data might have been changed after the call.
 *
 * @param &$data
 *   The XML data which will be parsed later.
 * @return
 *   An XML parser object.
 */
function unicode_xml_parser_create(&$data) {
  // Default XML encoding is UTF-8
  $encoding = 'utf-8';
  $bom = false;

  // Check for UTF-8 byte order mark (PHP5's XML parser doesn't handle it).
  if (!strncmp($data, "\xEF\xBB\xBF", 3)) {
    $bom = true;
    $data = substr($data, 3);
  }

  // Check for an encoding declaration in the XML prolog if no BOM was found.
  if (!$bom && ereg('^<\?xml[^>]+encoding="([^"]+)"', $data, $match)) {
    $encoding = $match[1];
  }

  // Unsupported encodings are converted here into UTF-8.
  $php_supported = array('utf-8', 'iso-8859-1', 'us-ascii');
  if (!in_array(strtolower($encoding), $php_supported)) {
    $out = unicode_convert_to_utf8($data, $encoding);
    if ($out !== false) {
      $encoding = 'utf-8';
      $data = ereg_replace('^(<\?xml[^>]+encoding)="([^"]+)"', '\\1="utf-8"', $out);
    }
    else {
     // watchdog('php', t("Could not convert XML encoding '%s' to UTF-8.", array('%s' => $encoding)), WATCHDOG_WARNING);
      return 0;
    }
  }

  $xml_parser = xml_parser_create($encoding);
  xml_parser_set_option($xml_parser, XML_OPTION_TARGET_ENCODING, 'utf-8');
  return $xml_parser;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Convert data to UTF-8
 *
 * Requires the iconv, GNU recode or mbstring PHP extension.
 *
 * @param $data
 *   The data to be converted.
 * @param $encoding
 *   The encoding that the data is in
 * @return
 *   Converted data or FALSE.
 */
function unicode_convert_to_utf8($data, $encoding) {
  if (function_exists('iconv')) {
    $out = @iconv($encoding, 'utf-8', $data);
  }
  else if (function_exists('mb_convert_encoding')) {
    $out = @mb_convert_encoding($data, 'utf-8', $encoding);
  }
  else if (function_exists('recode_string')) {
    $out = @recode_string($encoding .'..utf-8', $data);
  }
  else {
   // watchdog('php', t("Unsupported encoding '%s'. Please install iconv, GNU recode or mbstring for PHP.", array('%s' => $encoding)), WATCHDOG_ERROR);
    return FALSE;
  }

  return $out;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Truncate a UTF-8-encoded string safely to a number of bytes.
 *
 * If the end position is in the middle of a UTF-8 sequence, it scans backwards
 * until the beginning of the byte sequence.
 *
 * Use this function whenever you want to chop off a string at an unsure
 * location. On the other hand, if you're sure that you're splitting on a
 * character boundary (e.g. after using strpos() or similar), you can safely use
 * substr() instead.
 *
 * @param $string
 *   The string to truncate.
 * @param $len
 *   An upper limit on the returned string length.
 * @param $wordsafe
 *   Flag to truncate at nearest space. Defaults to FALSE.
 * @return
 *   The truncated string.
 */
function truncate_utf8($string, $len, $wordsafe = FALSE, $dots = FALSE) {
  $slen = strlen($string);
  if ($slen <= $len) {
    return $string;
  }
  if ($wordsafe) {
    $end = $len;
    while (($string[--$len] != ' ') && ($len > 0)) {};
    if ($len == 0) {
      $len = $end;
    }
  }
  if ((ord($string[$len]) < 0x80) || (ord($string[$len]) >= 0xC0)) {
    return substr($string, 0, $len) . ($dots ? ' ...' : '');
  }
  while (--$len >= 0 && ord($string[$len]) >= 0x80 && ord($string[$len]) < 0xC0) {};
  return substr($string, 0, $len) . ($dots ? ' ...' : '');
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Encodes MIME/HTTP header values that contain non-ASCII, UTF-8 encoded
 * characters.
 *
 * For example, mime_header_encode('tést.txt') returns "=?UTF-8?B?dMOpc3QudHh0?=".
 *
 * See http://www.rfc-editor.org/rfc/rfc2047.txt for more information.
 *
 * Notes:
 * - Only encode strings that contain non-ASCII characters.
 * - We progressively cut-off a chunk with truncate_utf8(). This is to ensure
 *   each chunk starts and ends on a character boundary.
 * - Using \n as the chunk separator may cause problems on some systems and may
 *   have to be changed to \r\n or \r.
 */
function mime_header_encode($string) {
  if (preg_match('/[^\x20-\x7E]/', $string)) {
    $chunk_size = 47; // floor((75 - strlen("=?UTF-8?B??=")) * 0.75);
    $len = strlen($string);
    $output = '';
    while ($len > 0) {
      $chunk = truncate_utf8($string, $chunk_size);
      $output .= ' =?UTF-8?B?'. base64_encode($chunk) ."?=\n";
      $c = strlen($chunk);
      $string = substr($string, $c);
      $len -= $c;
    }
    return trim($output);
  }
  return $string;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Complement to mime_header_encode
 */
function mime_header_decode($header) {
  // First step: encoded chunks followed by other encoded chunks (need to collapse whitespace)
  $header = preg_replace_callback('/=\?([^?]+)\?(Q|B)\?([^?]+|\?(?!=))\?=\s+(?==\?)/', '_mime_header_decode', $header);
  // Second step: remaining chunks (do not collapse whitespace)
  return preg_replace_callback('/=\?([^?]+)\?(Q|B)\?([^?]+|\?(?!=))\?=/', '_mime_header_decode', $header);
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Helper function to mime_header_decode
 */
function _mime_header_decode($matches) {
  // Regexp groups:
  // 1: Character set name
  // 2: Escaping method (Q or B)
  // 3: Encoded data
  $data = ($matches[2] == 'B') ? base64_decode($matches[3]) : str_replace('_', ' ', quoted_printable_decode($matches[3]));
  if (strtolower($matches[1]) != 'utf-8') {
    $data = unicode_convert_to_utf8($data, $matches[1]);
  }
  return $data;
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Decode all HTML entities (including numerical ones) to regular UTF-8 bytes.
 * Double-escaped entities will only be decoded once ("&amp;lt;" becomes "&lt;", not "<").
 *
 * @param $text
 *   The text to decode entities in.
 * @param $exclude
 *   An array of characters which should not be decoded. For example,
 *   array('<', '&', '"'). This affects both named and numerical entities.
 */
function decode_entities($text, $exclude = array()) {
  static $table;
  // We store named entities in a table for quick processing.
  if (!isset($table)) {
    // Get all named HTML entities.
    $table = array_flip(get_html_translation_table(HTML_ENTITIES));
    // PHP gives us ISO-8859-1 data, we need UTF-8.
    $table = array_map('utf8_encode', $table);
    // Add apostrophe (XML)
    $table['&apos;'] = "'";
  }
  $newtable = array_diff($table, $exclude);

  // Use a regexp to select all entities in one pass, to avoid decoding double-escaped entities twice.
  return preg_replace('/&(#x?)?([A-Za-z0-9]+);/e', '_decode_entities("$1", "$2", "$0", $newtable, $exclude)', $text);
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Helper function for decode_entities
 */
function _decode_entities($prefix, $codepoint, $original, &$table, &$exclude) {
  // Named entity
  if (!$prefix) {
    if (isset($table[$original])) {
      return $table[$original];
    }
    else {
      return $original;
    }
  }
  // Hexadecimal numerical entity
  if ($prefix == '#x') {
    $codepoint = base_convert($codepoint, 16, 10);
  }
  // Decimal numerical entity (strip leading zeros to avoid PHP octal notation)
  else {
    $codepoint = preg_replace('/^0+/', '', $codepoint);
  }
  // Encode codepoint as UTF-8 bytes
  if ($codepoint < 0x80) {
    $str = chr($codepoint);
  }
  else if ($codepoint < 0x800) {
    $str = chr(0xC0 | ($codepoint >> 6))
         . chr(0x80 | ($codepoint & 0x3F));
  }
  else if ($codepoint < 0x10000) {
    $str = chr(0xE0 | ( $codepoint >> 12))
         . chr(0x80 | (($codepoint >> 6) & 0x3F))
         . chr(0x80 | ( $codepoint       & 0x3F));
  }
  else if ($codepoint < 0x200000) {
    $str = chr(0xF0 | ( $codepoint >> 18))
         . chr(0x80 | (($codepoint >> 12) & 0x3F))
         . chr(0x80 | (($codepoint >> 6)  & 0x3F))
         . chr(0x80 | ( $codepoint        & 0x3F));
  }
  // Check for excluded characters
  if (in_array($str, $exclude)) {
    return $original;
  }
  else {
    return $str;
  }
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Count the amount of characters in a UTF-8 string. This is less than or
 * equal to the byte count.
 */
function unicode_strlen($text) {
  global $multibyte;
  if ($multibyte == UNICODE_MULTIBYTE) {
    return mb_strlen($text);
  }
  else {
    // Do not count UTF-8 continuation bytes.
    return strlen(preg_replace("/[\x80-\xBF]/", '', $text));
  }
}

/**
 * Uppercase a UTF-8 string.
 */
function unicode_strtoupper($text) {
  global $multibyte;
  if ($multibyte == UNICODE_MULTIBYTE) {
    return mb_strtoupper($text);
  }
  else {
    // Use C-locale for ASCII-only uppercase
    $text = strtoupper($text);
    // Case flip Latin-1 accented letters
    $text = preg_replace_callback('/\xC3[\xA0-\xB6\xB8-\xBE]/', '_unicode_caseflip', $text);
    return $text;
  }
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Lowercase a UTF-8 string.
 */
function unicode_strtolower($text) {
  global $multibyte;
  if ($multibyte == UNICODE_MULTIBYTE) {
    return mb_strtolower($text);
  }
  else {
    // Use C-locale for ASCII-only lowercase
    $text = strtolower($text);
    // Case flip Latin-1 accented letters
    $text = preg_replace_callback('/\xC3[\x80-\x96\x98-\x9E]/', '_unicode_caseflip', $text);
    return $text;
  }
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Helper function for case conversion of Latin-1.
 * Used for flipping U+C0-U+DE to U+E0-U+FD and back.
 */
function _unicode_caseflip($matches) {
  return $matches[0][0] . chr(ord($matches[0][1]) ^ 32);
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Capitalize the first letter of a UTF-8 string.
 */
function unicode_ucfirst($text) {
  // Note: no mbstring equivalent!
  return unicode_strtoupper(unicode_substr($text, 0, 1)) . unicode_substr($text, 1);
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Cut off a piece of a string based on character indices and counts. Follows
 * the same behaviour as PHP's own substr() function.
 *
 * Note that for cutting off a string at a known character/substring
 * location, the usage of PHP's normal strpos/substr is safe and
 * much faster.
 */
function unicode_substr($text, $start, $length = NULL) {
  global $multibyte;
  $bytes = 0;
  if ($multibyte == UNICODE_MULTIBYTE) {
    return $length === NULL ? mb_substr($text, $start) : mb_substr($text, $start, $length);
  }
  else {
    $strlen = strlen($text);
    // Find the starting byte offset
    if ($start > 0) {
      // Count all the continuation bytes from the start until we have found
      // $start characters
      $bytes = -1; $chars = -1;
      while ($bytes < $strlen && $chars < $start) {
        $bytes++;
        $c = ord($text[$bytes]);
        if ($c < 0x80 || $c >= 0xC0) {
          $chars++;
        }
      }
    }
    else if ($start < 0) {
      // Count all the continuation bytes from the end until we have found
      // abs($start) characters
      $start = abs($start);
      $bytes = $strlen; $chars = 0;
      while ($bytes > 0 && $chars < $start) {
        $bytes--;
        $c = ord($text[$bytes]);
        if ($c < 0x80 || $c >= 0xC0) {
          $chars++;
        }
      }
    }
    $istart = $bytes;

    // Find the ending byte offset
    if ($length === NULL) {
      $bytes = $strlen - 1;
    }
    else if ($length > 0) {
      // Count all the continuation bytes from the starting index until we have
      // found $length + 1 characters. Then backtrack one byte.
      $bytes = $istart; $chars = 0;
      while ($bytes < $strlen && $chars < $length) {
        $bytes++;
        $c = ord($text[$bytes]);
        if ($c < 0x80 || $c >= 0xC0) {
          $chars++;
        }
      }
      $bytes--;
    }
    else if ($length < 0) {
      // Count all the continuation bytes from the end until we have found
      // abs($length) characters
      $length = abs($length);
      $bytes = $strlen - 1; $chars = 0;
      while ($bytes >= 0 && $chars < $length) {
        $c = ord($text[$bytes]);
        if ($c < 0x80 || $c >= 0xC0) {
          $chars++;
        }
        $bytes--;
      }
    }
    $iend = $bytes;

    return substr($text, $istart, max(0, $iend - $istart + 1));
  }
}
# ----------------------------------------------------------------------------------------------------------------------------------------------------
?>