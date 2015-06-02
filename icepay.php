<?php

/**
 * @package       ICEPAY Payment Module for VirtueMart 3
 * @author        Ricardo Jacobs <ricardo.jacobs@icepay.com>
 * @copyright     (c) 2015 ICEPAY. All rights reserved.
 * @version       1.0.0, May 2015
 * @license       GNU/GPL, see http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Direct access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

require_once(dirname(__FILE__) . '/icepay_api/icepay_api_basic.php');

class plgVmPaymentIcepay extends vmPSPlugin {

	private $vm_icepay;

	public $vm_vendor = 'ICEPAY';
	public $vm_version = '1.0.0';

	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);

		$this->_icepay = new Icepay_Project_Helper();
		$this->_loggable = true;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys($this->getTableSQLFields());

		$varsToPush = array(
			// Do not change any of these array items. If you do, I'll use the Avada Kedavra spell on you.
			'icepaybasic_merchantid'    => array('', 'char'),
			'icepaybasic_secretcode'    => array('', 'char'),
			'icepaybasic_paymentmethod' => array('', 'char'),
			'status_pending'            => array('', 'char'),
			'status_success'            => array('', 'char'),
			'status_canceled'           => array('', 'char'),
			'status_refund'             => array('', 'char'),
			'status_chargeback'         => array('', 'char'),
			'payment_logos'             => array('', 'char'),
			'payment_currency'          => array(0, 'int'),
			'countries'                 => array(0, 'char'),
			'min_amount'                => array(0, 'int'),
			'max_amount'                => array(0, 'int'),
			'cost_per_transaction'      => array(0, 'int'),
			'cost_percent_total'        => array(0, 'int'),
			'tax_id'                    => array(0, 'int')
		);

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

		$notify_url	= isset($_GET['notify']) ? 1 : 0;
		$error_url	= isset($_GET['error'])  ? 1 : 0;

		if ( $notify_url || $error_url ) {
			$this->plgVmOnPaymentNotification();
		}
	}

	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment ' . $vm_vendor . ' Table');
	}

	private function icepay() {
		if (!isset($this->vm_icepay))
			$this->vm_icepay = new Icepay_Project_Helper();

		return $this->vm_icepay;
	}

	private function _getLangISO() {
		$lang = &JFactory::getLanguage();
		$arr = explode("-", $lang->get('tag'));

		return strtoupper($arr[0]);
	}

	function getTableSQLFields() {
		$SQLfields = array(
			'id'                          => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(11) UNSIGNED DEFAULT NULL',
			'order_number'                => 'char(32) DEFAULT NULL',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
			'payment_name'                => 'char(255) NOT NULL DEFAULT \'\' ',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
			'payment_currency'            => 'char(3) ',
			'cost_per_transaction'        => ' decimal(10,2) DEFAULT NULL ',
			'cost_percent_total'          => ' decimal(10,2) DEFAULT NULL ',
			'tax_id'                      => 'smallint(11) DEFAULT NULL',
			'icepay_order_id'             => 'int(11) UNSIGNED DEFAULT NULL',
			'icepay_transaction_id'       => 'char(32) DEFAULT NULL',
			'icepay_status'               => 'char(32) DEFAULT \'NEW\''
		);

		foreach($this->icepay()->postback()->getPostbackResponseFields() as $param => $postback) {
			$field = strtolower($param);
			$SQLfields["icepay_response_{$field}"] = 'varchar(120) DEFAULT NULL';
		}

		return $SQLfields;
	}

	function plgVmConfirmedOrder($cart, $order) {
		if (empty($method->merchantid)) {
			vmInfo(JText::_('Your Merchant ID is missing in the configuration.'));
		}

		if (empty($method->secretcode)) {
			vmInfo(JText::_('Your Secretcode is missing in the configuration.'));
		}

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null;
		}

		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		if (!class_exists('VirtueMartModelCurrency'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

		$usrBT = $order['details']['BT'];
		$usrST = isset($order['details']['ST']) ? $order['details']['ST'] : $order['details']['BT'];

		$this->getPaymentCurrency($method);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = &JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);

		$amount = round($order['details']['BT']->order_total, 2);

		$icepay = $this->vm_icepay->basic();

		try {
			$icepay->setMerchantID($method->merchantid)->setSecretCode($method->secretcode);

			$icepay
				->setAmount($amount)
				->setCountry(ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code'))
				->setLanguage($this->_getLangISO())
				->setCurrency($currency_code_3)
				->setReference($order['details']['BT']->order_number)
				->setDescription($order['details']['BT']->order_number);

			$url = $icepay
				->setOrderID($cart->virtuemart_order_id)
				->getURL();
		} catch (Exception $e) {
			die($e->getMessage());
		}

		$html  = '<form action="' . $url . '" method="post" name="icepay"></form>';
		$html .= '<script type="text/javascript">';
		$html .= 'document.icepay.submit();';
		$html .= '</script>';

		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;

		$this->storePSPluginInternalData($dbValues);

		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $method->status_pending);
	}

	function plgVmOnPaymentNotification() {
		//
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
		//
	}

	function plgVmOnPaymentResponseReceived(&$html) {
		//
	}

	function plgVmOnUserPaymentCancel() {
		return true;
	}

	/**
	 * Required functions by Joomla or VirtueMart. Removed code comments due to 'file length'.
	 * All copyrights are (c) respective year of author or copyright holder, and the author.
	 */
	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}
	protected function checkConditions($cart, $method, $cart_prices) {
		$this->convert_condition_amount($method);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		$amount = $this->getCartAmount($cart_prices);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}
		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}
		return FALSE;
	}
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
		return $this->OnSelectCheck($cart);
	}
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

}

// End of file (v 1.0.0)
// github.com/icepay/virtuemart-3
