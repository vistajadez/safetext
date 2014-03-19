<?php

/**
 * Content Filter for Mobile View Helper.
 * Example of how to create a view helper. This could be called from within a view script like this:
 *
 *	echo $this->contentFilter($someHtmlContentString);
 *
 */
class ContentFilterHelper extends MsHelper {
	
	/**
	 * An example helper that filters content to be viewed in some controlled context. Removes all but a handful of tags.
	 *
	 * @param mixed[] $params - First parameter is content, the unfiltered HTML content being passed in.
	 *
	 * @return string The filtered content to display.
	 *
	 */
	public function contentFilter($params) {
		return stripslashes(strip_tags($params[0], '<p><a><br><b><u><i><em><strong>'));
	}
	
	
	
	
}