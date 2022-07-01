<?php

namespace Uptaxi\Controllers\Payment;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Otp extends MainController
{
	public function main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = $_POST['order_id'] ?? null;
		$otp = $_POST['otp'] ?? null;

		if (empty($order_id) || empty($otp))
			return parent::errCli('Post params order_id or otp is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$order = $dbfirm->GetRow('SELECT id, selected_payment_method_type, split_part(selected_payment_card_token, \'$$\', 1) as payment_token, split_part(selected_payment_card_token, \'$$\', 2) as auth_token, selected_payment_method_res, split_part(get_opt_s(\'terminal_key\'), \'::\', 1) AS merchant, (SELECT pozivnoi FROM bort WHERE id = o.id_bort) AS requisite, get_opt_s(\'service_id\') AS service_id, get_opt_s(\'payment_url\') AS beeline_server FROM orders o WHERE id = ?', $order_id);

		if ($order['selected_payment_method_type'] !== 'beeline.kg')
			return parent::errCli('Payment method type is not valid', -7);

		if ($order['payment_token'] == '')
			return parent::errCli(Language::data('payment')['failed_to_pay_check_reg'], -7);

		$beeline_server = $order['beeline_server'];

		$curl = curl_init();

		$params = [
			'payment_token' => $order['payment_token'],
			'one_time_password' => $otp
		];

		curl_setopt_array($curl, array(
			CURLOPT_URL => "$beeline_server/site-api/acquiring/payment/verify",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => urldecode(http_build_query($params)),
			CURLOPT_HTTPHEADER => array(
				'Cache-Control: no-cache',
				'Content-Type: application/x-www-form-urlencoded'
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$json = @json_decode($response);
		$_POST['json1'] = $json;

		if ($json === null && json_last_error() !== JSON_ERROR_NONE)
			return parent::errSer(Language::data('global')['unknown_error'].' #PO1URJE');

		if ($httpcode != 200)
		{
			switch ($json->code)
			{
				case 'FAIL':
					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Пользователь заблокирован', false]);
					return parent::errCli(Language::data('payment')['user_blocked_pay_via_cash'], -7);
					break;
				case 'ERROR':
					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Недостаточно денежных средств', false]);
					return parent::errCli(Language::data('payment')['no_money_pay_via_cash'], -7);
					break;
				case 'ORDER_OTP_MISMATCH':
					return parent::errCli(Language::data('payment')['otp_mismatch']);
					break;
				case 'ORDER_VERIFY_EXPIRED':
					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Время для подтверждения платежного поручения истекло', false]);
					return parent::errSer(Language::data('payment')['verify_expired'], -7);
					break;
				default:
					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Неизвестная ошибка #PO1HCN2', false]);
					return parent::errSer(Language::data('global')['unknown_error'].' #PO1HCN2');
					break;
			}
		} else {
			switch ($json->status) {
				case 'SUCCESS':
					if ($order['selected_payment_method_res'] === null)
						$res = (false !== $dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата прошла успешно', true]));
					return parent::success($res);
					break;
				case 'IN_PROGRESS':
					//Go to check_status
					break;
				case 'FAIL':
					if ($json->details->field == 'amount')
					{
						if ($order['selected_payment_method_res'] === null)
							$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Недостаточно денежных средств', false]);
						return parent::errCli(Language::data('payment')['no_money_pay_via_cash'], -7);
					}

					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Неизвестная ошибка #PO1HCN3', false]);
					return parent::errSer(Language::data('global')['unknown_error'].' #PO1HCN3', -7);
					break;
				default:
					if ($order['selected_payment_method_res'] === null)
						$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Неизвестная ошибка #PO1HCN4', false]);
					return parent::errSer(Language::data('global')['unknown_error'].' #PO1HCN4', -7);
					break;
			}
		}

		if ($err) {
			return parent::errSer(Language::data('global')['unknown_error'].' #PO1СE');
		} else {
			$counter = 0;

			while ($counter < 5)
			{
				$status = self::check_status($dbfirm, $beeline_server, $order);

				if ($status)
				{
					if ($order['selected_payment_method_res'] === null)
						$res = (false !== $dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата прошла успешно', true]));
					return parent::success($res);
				}

				$counter++;
				usleep(2900*1000);
			}

			if ($order['selected_payment_method_res'] === null)
				$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Не удалось провести оплату со счета', false]);
			return parent::errSer(Language::data('payment')['failed_to_pay'], -7);
		}
	}

	public function check_status($dbfirm, $beeline_server, $order)
	{
		$curl = curl_init();

		$params = [
			'auth_token' => $order['auth_token'],
			'merchant' => $order['merchant'],
			'service_id' => $order['service_id'],
			'transaction_id' => $order['id'],
			'requisite' => $order['requisite']
		];

		curl_setopt_array($curl, array(
			CURLOPT_URL => "$beeline_server/site-api/acquiring/payment-state",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($params),
			CURLOPT_HTTPHEADER => array(
				'Cache-Control: no-cache',
				'Content-Type: application/x-www-form-urlencoded'
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		$json = @json_decode($response);
		$_POST['json2'] = $json;

		if ($json === null && json_last_error() !== JSON_ERROR_NONE)
			return false;

		if ($httpcode != 200)
		{
			return false;
		}

		if ($err) {
			return false;
		} else {
			if ($json->status == 'SUCCESS')
			{
				return true;
			} else {
				return false;
			}
		}
	}
}