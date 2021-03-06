<?php

class Icepay_Paymentmethod_Ideal extends Icepay_Basicmode
{
	public $_version       = "1.0.0";
	public $_method        = "IDEAL";
	public $_readable_name = "iDEAL";
	public $_issuer        = array('ABNAMRO', 'ASNBANK', 'ING', 'KNAB', 'RABOBANK', 'SNSBANK', 'SNSREGIOBANK', 'TRIODOSBANK', 'VANLANSCHOT');
	public $_country       = array('NL');
	public $_language      = array('NL');
	public $_currency      = array('EUR');
	public $_amount        = array('minimum' => 30, 'maximum' => 1000000);
}
