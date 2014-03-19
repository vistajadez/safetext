<?php

/**
 * State Dropdown View Helper.
 *
 *
 */
class StateDropdownHelper extends MsHelper {
	
	
	/**
	 * State Dropdown
	 *
	 *	@param string[] $params
	 *		id		 - HTML ID for this dropdown.
	 *		class.
	 *		style.
	 *		selected - Selected value (optional).
	 *		onchange - JS to execute upon value change (optional).
	 */
	public function stateDropdown($params) {
		$htmlOut = '<select';
		if (isset($params['id'])) $htmlOut .= ' id="' . $params['id'] . '"';
		if (isset($params['class'])) $htmlOut .= ' class="' . $params['class'] . '"';
		if (isset($params['style'])) $htmlOut .= ' style="' . $params['style'] . '"';
		if (isset($params['onchange'])) $htmlOut .= ' onchange="' . $params['onchange'] . '"';
		$htmlOut .= '>';
		
		$stateArray = array(
			'AL'        => 'Alabama',
			'AK'        => 'Alaska',
			'AZ'        => 'Arizona',
			'AR'        => 'Arkansas',
			'CA'        => 'California',
			'CO'        => 'Colorado',
			'CT'        => 'Connecticut',
			'DE'        => 'Delaware',
			'DC'        => 'District of Columbia',
			'FL'        => 'Florida',
			'GA'        => 'Georgia',
			'HI'        => 'Hawaii',
			'ID'        => 'Idaho',
			'IL'        => 'Illinois',
			'IN'        => 'Indiana',
			'IA'        => 'Iowa',
			'KS'        => 'Kansas',
			'KY'        => 'Kentucky',
			'LA'        => 'Louisiana',
			'ME'        => 'Maine',
			'MD'        => 'Maryland',
			'MA'        => 'Massachusetts',
			'MI'        => 'Michigan',
			'MN'        => 'Minnesota',
			'MS'        => 'Mississippi',
			'MO'        => 'Missouri',
			'MT'        => 'Montana',
			'NE'        => 'Nebraska',
			'NV'        => 'Nevada',
			'NH'        => 'New Hampshire',
			'NJ'        => 'New Jersey',
			'NM'        => 'New Mexico',
			'NY'        => 'New York',
			'NC'        => 'North Carolina',
			'ND'        => 'North Dakota',
			'OH'        => 'Ohio',
			'OK'        => 'Oklahoma',
			'OR'        => 'Oregon',
			'PA'        => 'Pennsylvania',
			'RI'        => 'Rhode Island',
			'SC'        => 'South Carolina',
			'SD'        => 'South Dakota',
			'TN'        => 'Tennessee',
			'TX'        => 'Texas',
			'UT'        => 'Utah',
			'VT'        => 'Vermont',
			'VA'        => 'Virginia',
			'WA'        => 'Washington',
			'WV'        => 'West Virginia',
			'WI'        => 'Wisconsin',
			'WY'        => 'Wyoming'
		);
		
		foreach ($stateArray as $code => $name) {
			$htmlOut .= '<option value="' . $code . '"';
			if ((isset($params['selected'])) && ($params['selected'] == $code)) $htmlOut .= ' selected';
			$htmlOut .= '>' . $name . '</option>';
		}
		
		$htmlOut .= '</select>';
		return $htmlOut;
	}
	
}