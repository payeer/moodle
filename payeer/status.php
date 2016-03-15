<?php
require("../../config.php");
require_once("$CFG->dirroot/enrol/payeer/lib.php");

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$err = false;
	$message = '';
	$plugin = enrol_get_plugin('payeer');
	
	foreach ($_POST as $key => $value)
	{
		$data->$key = fix_utf8($value);
	}

	// запись логов

	$log_text = 
	"--------------------------------------------------------\n" .
	"operation id		" . $data->m_operation_id . "\n" .
	"operation ps		" . $data->m_operation_ps . "\n" .
	"operation date		" . $data->m_operation_date . "\n" .
	"operation pay date	" . $data->m_operation_pay_date . "\n" .
	"shop				" . $data->m_shop . "\n" .
	"order id			" . $data->m_orderid . "\n" .
	"amount				" . $data->m_amount . "\n" .
	"currency			" . $data->m_curr . "\n" .
	"description		" . base64_decode($data->m_desc) . "\n" .
	"status				" . $data->m_status . "\n" .
	"sign				" . $data->m_sign . "\n\n";
	
	$log_file = $plugin->get_config('payeer_log');
	
	if (!empty($log_file))
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
	}
	
	// проверка цифровой подписи и ip

	$sign_hash = strtoupper(hash('sha256', implode(":", array(
		$data->m_operation_id,
		$data->m_operation_ps,
		$data->m_operation_date,
		$data->m_operation_pay_date,
		$data->m_shop,
		$data->m_orderid,
		$data->m_amount,
		$data->m_curr,
		$data->m_desc,
		$data->m_status,
		$plugin->get_config('payeer_key')
	))));
	
	$valid_ip = true;
	$sIP = str_replace(' ', '', $plugin->get_config('payeer_ipfilter'));
	
	if (!empty($sIP))
	{
		$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
		if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
		'(' . $arrIP[1] . '|\*{1})(\.)' .
		'(' . $arrIP[2] . '|\*{1})(\.)' .
		'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
		{
			$valid_ip = false;
		}
	}
	
	if (!$valid_ip)
	{
		$message .= " - the ip address of the server is not trusted\n" .
		"   trusted ip: " . $sIP . "\n" .
		"   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
		$err = true;
	}

	if ($data->m_sign != $sign_hash)
	{
		$message .= " - do not match the digital signature\n";
		$err = true;
	}
	
	if (!$err)
	{
		// загрузка заказа
		
		$payeertx = $DB->get_record('enrol_payeer_transactions', array(
			'id' => required_param('m_orderid', PARAM_INT)
		));
		
		$plugin_instance = $DB->get_record("enrol", array(
			"id" => $payeertx->instanceid,
			"status" => 0
		));
		
		if (!($plugin_instance && $payeertx)) 
		{
			$message .= " - undefined order id\n";
			$err = true;
		}
		else
		{
			$order_curr = ($plugin_instance->currency == 'RUR') ? 'RUB' : $plugin_instance->currency;
			$order_amount = number_format($plugin_instance->cost, 2, '.', '');
			
			// проверка суммы и валюты
		
			if ($data->m_amount != $order_amount)
			{
				$message .= " - wrong amount\n";
				$err = true;
			}

			if ($data->m_curr != $order_curr)
			{
				$message .= " - wrong currency\n";
				$err = true;
			}
			
			// проверка статуса
			
			if (!$err)
			{
				switch ($data->m_status)
				{
					case 'success':
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
						$payeertx->success = 1;
						unset($USER->mycourses);
						
						if (!$DB->update_record('enrol_payeer_transactions', $payeertx)) 
						{
							$message .= " - cannot change the order status to success\n";
							$err = true;
						}
						break;
						
					default:
						$message .= " - the payment status is not success\n";
						$err = true;
						break;
				}
			}
		}
	}
	
	if ($err)
	{
		$to = $plugin->get_config('payeer_emailerr');

		if (!empty($to))
		{
			$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n" . $message . "\n" . $log_text;
			$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
			"Content-type: text/plain; charset=utf-8 \r\n";
			mail($to, 'Payment error', $message, $headers);
		}
		
		die($data->m_orderid . '|error');
	}
	else
	{
		die($data->m_orderid . '|success');
	}
}
else 
{
	die($_POST['m_orderid'] . '|error');
}