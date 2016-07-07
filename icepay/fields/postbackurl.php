<?php

/**
 * @package       ICEPAY Payment Module for VirtueMart 3
 * @author        Ricardo Jacobs <ricardo.jacobs@icepay.com>
 * @copyright     (c) 2016 ICEPAY. All rights reserved.
 * @version       1.0.2, July 2015
 * @license       GNU/GPL, see http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('JPATH_BASE') or die('Something is not defined and it is not our fault.');

jimport('joomla.form.formfield');

class JFormFieldPostbackURL extends JFormField {

	var $type = 'postbackurl';

	function getInput() {

		$cid = vRequest::getvar('cid', NULL, 'array');

		if (is_Array($cid)) {
			$virtuemart_paymentmethod_id = $cid[0];
		} else {
			$virtuemart_paymentmethod_id = $cid;
		}

		$url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $virtuemart_paymentmethod_id);

		return $url;

	}

}

// End of file (v 1.0.2)
// github.com/icepay/virtuemart-3
