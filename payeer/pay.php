<?php

require dirname(dirname(dirname(__FILE__))) . "/config.php";
require_once "{$CFG->dirroot}/lib/enrollib.php";

$id = required_param('id', PARAM_INT);  // plugin instance id

if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$id, "status"=>0))) 
{
    print_error('invalidinstance');
}

$plugin = enrol_get_plugin('payeer');

$m_url = $plugin->get_config('payeer_merchant_url');
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

// проверка принадлежности ip списку доверенных ip
$list_ip_str = str_replace(' ', '', $plugin->get_config('payeer_ipfilter'));

if (!empty($list_ip_str)) 
{
	$list_ip = explode(',', $list_ip_str);
	$this_ip = $_SERVER['REMOTE_ADDR'];
	$this_ip_field = explode('.', $this_ip);
	$list_ip_field = array();
	$i = 0;
	$valid_ip = FALSE;
	foreach ($list_ip as $ip)
	{
		$ip_field[$i] = explode('.', $ip);
		if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
			(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
			(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
			(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
			{
				$valid_ip = TRUE;
				break;
			}
		$i++;
	}
}
else
{
	$valid_ip = TRUE;
}

if ($valid_ip)
{
	redirect($m_url . "?
		m_shop={$m_shop}&
		m_orderid={$m_orderid}&
		m_amount={$m_amount}&
		m_curr={$m_curr}&
		m_desc={$m_desc}&
		m_sign={$sign}
	");
}
else
{
	$to = $plugin->get_config('payeer_emailerr');
	$subject = "Payment error";
	$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
	$message.=" - the ip address of the server is not trusted\n";
	$message.="   trusted ip: " . $plugin->get_config('payeer_ipfilter') . "\n";
	$message.="   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
	$message.="\n" . $log_text;
	$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
	mail($to, $subject, $message, $headers);
	
	print_error('IP ADDRESS IS NOT ALLOW');
}