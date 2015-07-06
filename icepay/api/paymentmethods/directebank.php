<?php

class Icepay_Paymentmethod_Directebank extends Icepay_Basicmode
{
    public $_version       = "1.0.1";
    public $_method        = "DIRECTEBANK";
    public $_readable_name = "Sofort banking";
    public $_issuer        = array('');
    public $_country       = array('AT', 'BE', 'CH', 'DE', 'ES', 'FR', 'IT', 'NL', 'PL');
    public $_language      = array('DE', 'EN', 'NL', 'FR');
    public $_currency      = array('EUR', 'PLN');
    public $_amount        = array('minimum' => 30, 'maximum' => 1000000);
}
