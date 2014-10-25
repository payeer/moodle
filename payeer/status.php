<?php

require("../../config.php");
require_once("$CFG->dirroot/enrol/payeer/lib.php");

$id = required_param('m_orderid', PARAM_INT);

$payeertx = $DB->get_record('enrol_payeer_transactions', array('id' =>required_param('m_orderid', PARAM_INT)));

$plugin_instance = $DB->get_record("enrol", array("id"=>$payeertx->instanceid, "status"=>0));

$plugin = enrol_get_plugin('payeer');

if ($plugin_instance->enrolperiod) 
{
	$timestart = time();
	$timeend   = $timestart + $plugin_instance->enrolperiod;
} 
else 
{
	$timestart = 0;
	$timeend   = 0;
}

$plugin->enrol_user($plugin_instance, $payeertx->userid, $plugin_instance->roleid, $timestart, $timeend);
unset($USER->mycourses);

$payeertx->success = 1;
$DB->update_record('enrol_payeer_transactions', $payeertx);

if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$payeertx->instanceid, "status"=>0))) 
{
    die($_POST['m_orderid'] . '|error');
}

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$m_key = $plugin->get_config('payeer_key');
	
	$arHash = array(
		$_POST['m_operation_id'],
		$_POST['m_operation_ps'],
		$_POST['m_operation_date'],
		$_POST['m_operation_pay_date'],
		$_POST['m_shop'],
		$_POST['m_orderid'],
		$_POST['m_amount'],
		$_POST['m_curr'],
		$_POST['m_desc'],
		$_POST['m_status'],
		$m_key
	);
	
	$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
	
	$log_text = 
		"--------------------------------------------------------\n".
		"operation id		".$_POST["m_operation_id"]."\n".
		"operation ps		".$_POST["m_operation_ps"]."\n".
		"operation date		".$_POST["m_operation_date"]."\n".
		"operation pay date	".$_POST["m_operation_pay_date"]."\n".
		"shop				".$_POST["m_shop"]."\n".
		"order id			".$_POST["m_orderid"]."\n".
		"amount				".$_POST["m_amount"]."\n".
		"currency			".$_POST["m_curr"]."\n".
		"description		".base64_decode($_POST["m_desc"])."\n".
		"status				".$_POST["m_status"]."\n".
		"sign				".$_POST["m_sign"]."\n\n";
	
	if ($plugin->get_config('payeer_log'))
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'].'/payeer.log', $log_text, FILE_APPEND);
	}

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

	if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success" && $valid_ip)
	{
		if (!$payeertx = $DB->get_record('enrol_payeer_transactions', array('id' =>required_param('m_orderid', PARAM_INT)))) 
		{
            die($_POST['m_orderid'] . '|error');
        }
		
		if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$payeertx->instanceid, "status"=>0))) 
		{
			die($_POST['m_orderid'] . '|error');
		}
		
		if ($plugin_instance->enrolperiod) 
		{
			$timestart = time();
			$timeend   = $timestart + $plugin_instance->enrolperiod;
		} 
		else 
		{
			$timestart = 0;
			$timeend   = 0;
		}

		$plugin->enrol_user($plugin_instance, $payeertx->userid, $plugin_instance->roleid, $timestart, $timeend);

		unset($USER->mycourses);
		
		$payeertx->success = 1;
        if (!$DB->update_record('enrol_payeer_transactions', $payeertx)) 
		{
            die('FAIL');
        } 
		else 
		{
			die($_POST['m_orderid'] . '|success');
		}
	}
	else 
	{
		$to = $plugin->get_config('payeer_emailerr');
		$subject = "Payment error";
		$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
		if ($_POST["m_sign"] != $sign_hash)
		{
			$message .= " - Do not match the digital signature\n";
		}
		
		if ($_POST['m_status'] != "success")
		{
			$message .= " - The payment status is not success\n";
		}
		
		if (!$valid_ip)
		{
			$message .= " - the ip address of the server is not trusted\n";
			$message .= "   trusted ip: " . $plugin->get_config('payeer_ipfilter') . "\n";
			$message .= "   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
		}
		
		$message .= "\n" . $log_text;
		$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
		mail($to, $subject, $message, $headers);
				
		die($_POST['m_orderid'] . '|error');
	}
}
else 
{
	die($_POST['m_orderid'] . '|error');
}