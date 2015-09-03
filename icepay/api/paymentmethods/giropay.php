<?php

class Icepay_Paymentmethod_Giropay extends Icepay_Basicmode
{
	public $_version       = "1.0.0";
	public $_method        = "GIROPAY";
	public $_readable_name = "Giropay";
	public $_issuer        = array('DEFAULT');
	public $_country       = array('DE');
	public $_language      = array('DE');
	public $_currency      = array('EUR');
	public $_amount        = array('minimum' => 30, 'maximum' => 1000000);
}
