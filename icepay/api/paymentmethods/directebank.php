<?php

class Icepay_Paymentmethod_Directebank extends Icepay_Basicmode
{
	public $_version       = "1.0.0";
	public $_method        = "DDEBIT";
	public $_readable_name = "Sofort Banking";
	public $_issuer        = array('INCASSO');
	public $_country       = array('NL');
	public $_language      = array('NL', 'EN');
	public $_currency      = array('EUR');
	public $_amount        = array('minimum' => 1, 'maximum' => 200000);
}
