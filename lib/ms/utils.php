<?php


/**
 * Mediasoft Utilities.
 *
 *
 */
class MsUtils {
	
	//-- STRING UTILITIES --//
	
	/**
	 * CamelCase to Array.
	 *
	 * @param string $camel_string
	 *
	 * @return string[] An array with each camelcase segment as an element.
	 *
	 */
	static function camelcaseToArray($camel_string) {
		return preg_split('/([[:upper:]][[:lower:]]+)/', $camel_string, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);	
	}
	
	/**
	 * Auth Hash.
	 * 
	 * Given a string, returns a hash of the string to be used for auth validation.
	 *
	 * @param string $value
	 * @param string $hashsalt (Optional).
	 *
	 * @return string Hashed value.
	 */
	static function authHash($value, $hashsalt = '') {
		return md5($hashsalt . $value);
	}
	
	
	
	//-- COLOR/DISPLAY UTILITIES --//
	
	
	/**
	 * Adjust Color Brightness.
	 * RGB Usage: adjustColorBrightness(array("r"=>204,"g"=>136,"b"=>0),-5);
	 * Hex Usage: adjustColorBrightness("#C80",-5);
	 * Inspired by: http://jaspreetchahal.org/how-to-lighten-or-darken-hex-or-rgb-color-in-php-and-javascript/.
	 *
	 * @param string|int[] $color_code The hex color code preceded by '#' or an array of RGB data.
	 * @param int $percentage_adjuster Percent to brighten/darken as a percent. Negative values lighten. Example: -20 to brighten 20%, or 20 to darken 20%.
	 *
	 * @return string The adjusted hex value preceded by '#'.
	 *
	 */
	static function adjustColorBrightness($color_code,$percentage_adjuster = 0) {
		$percentage_adjuster = round($percentage_adjuster/100,2);
		if(is_array($color_code)) {
			$r = $color_code["r"] - (round($color_code["r"])*$percentage_adjuster);
			$g = $color_code["g"] - (round($color_code["g"])*$percentage_adjuster);
			$b = $color_code["b"] - (round($color_code["b"])*$percentage_adjuster);
	 
			return array("r"=> round(max(0,min(255,$r))),
				"g"=> round(max(0,min(255,$g))),
				"b"=> round(max(0,min(255,$b))));
		}
		else if(preg_match("/#/",$color_code)) {
			$hex = str_replace("#","",$color_code);
			$r = (strlen($hex) == 3)? hexdec(substr($hex,0,1).substr($hex,0,1)):hexdec(substr($hex,0,2));
			$g = (strlen($hex) == 3)? hexdec(substr($hex,1,1).substr($hex,1,1)):hexdec(substr($hex,2,2));
			$b = (strlen($hex) == 3)? hexdec(substr($hex,2,1).substr($hex,2,1)):hexdec(substr($hex,4,2));
			$r = round($r - ($r*$percentage_adjuster));
			$g = round($g - ($g*$percentage_adjuster));
			$b = round($b - ($b*$percentage_adjuster));
	 
			return "#".str_pad(dechex( max(0,min(255,$r)) ),2,"0",STR_PAD_LEFT)
				.str_pad(dechex( max(0,min(255,$g)) ),2,"0",STR_PAD_LEFT)
				.str_pad(dechex( max(0,min(255,$b)) ),2,"0",STR_PAD_LEFT);
	 
		}
	}
	
	/**
	 * Compress CSS.
	 *
	 * @param string $css_in
	 *
	 * @return string Compressed CSS.
	 */
	static function compressCss($css_in) {
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css_in);
		$css = str_replace(': ', ':', $css);
		$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
		
		return $css;
	}
	
	
	
	
}