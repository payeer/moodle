<?php

require dirname(dirname(dirname(__FILE__))) . "/config.php";
require_once "{$CFG->dirroot}/lib/enrollib.php";

$id = required_param('id', PARAM_INT);

if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$id, "status"=>0))) 
{
    print_error('invalidinstance');
}

$plugin = enrol_get_plugin('payeer');

$m_url = 'http:' . $plugin->get_config('payeer_merchant_url');
$m_shop = $plugin->get_config('payeer_shop');
$m_orderid = $plugin->begin_transaction($plugin_instance, $USER);
$m_amount = number_format($plugin_instance->cost, 2, '.', '');
$m_curr = $plugin_instance->currency;
$m_desc = base64_encode('Payment order No. ' . $m_orderid);
$m_key = $plugin->get_config('payeer_key');

$paymentsystem = explode('_', $plugin_instance->customchar1);

$arHash = array(
	$m_shop,
	$m_orderid,
	$m_amount,
	$m_curr,
	$m_desc,
	$m_key
);
$sign = strtoupper(hash('sha256', implode(":", $arHash)));

redirect($m_url . "?
	m_shop={$m_shop}&
	m_orderid={$m_orderid}&
	m_amount={$m_amount}&
	m_curr={$m_curr}&
	m_desc={$m_desc}&
	m_sign={$sign}
");