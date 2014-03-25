<?php

/**
 * Log Manager
 *
 * Object to write consistently formatted entries to an external log file for tracking/debug/troubleshooting purposes
 *
 */
class SafetextLog
{
	
	/**
	 * write.
	 * 
	 * @param string $message
	 * @param string $title (Optional).
	 *
	 * @return void
	 */
	public function write($message, $title='') {
		$entry = '';
		
		if ($title != '') {
			$entry = "\n\n" .
			date('D M jS, Y \a\t g:ia') . ' - ' . $title;
		}
		
		$entry .= "\n" . $message;
		
		file_put_contents(MS_PATH_BASE . DS . 'assets' . DS . 'logs' . DS . 'safetext.log', $entry, FILE_APPEND | LOCK_EX);
	}
	
	
}
	