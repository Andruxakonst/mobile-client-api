<?php

namespace Uptaxi\Controllers\Payment;

use Uptaxi\Controllers\MainController;

class Available extends MainController
{
	public function main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = [
			'methods' => ['cash'],
			'card' => ['add' => false]
		];

		$cashless = $dbfirm->GetRow('SELECT
			CASE WHEN car_options_list.id = 46 THEN true ELSE false END AS korp,
			coalesce(korp.self_select_payment_method, false) AS can_select,
			coalesce(korp.auto_korp_online, false) AS auto_set_cashless
		FROM
			car_options_list,
			korp,
			client
		WHERE
			client.id_korp=korp.id
			AND client.phone_number=?
			AND car_options_list.id=46
		ORDER BY car_options_list.id', $user_auth->login);

		$bonuses = (int)$dbfirm->GetRow('SELECT * FROM balans_client_6(?, ?)', [$user_auth->service, $user_auth->login])['bonus_step'];

		$get_opt = $dbfirm->GetRow('SELECT
			get_opt_s(\'payment_system_in_client_app\') AS payment_system_in_client_app,
			get_opt_s(\'show_payment_method_pin_code\')::integer AS show_payment_method_pin_code
		');

		$payment_system = $get_opt['payment_system_in_client_app'];
		$pin_code = (int)$get_opt['show_payment_method_pin_code'];

		if ($cashless['korp'] && (!$cashless['can_select'] || $cashless['auto_set_cashless']))
		{
			$res['methods'] = ['cashless'];
			return parent::success($res);
		}

		if ($cashless['korp'] && $cashless['can_select'])
			array_push($res['methods'], 'cashless');

		if ($bonuses > 0)
			array_push($res['methods'], 'bonuses');

		if ($pin_code == 1)
			array_push($res['methods'], 'pin_code');

		if ($payment_system)
		{
			if ($payment_system == 'ckassa')
			{
				$url = 'http://kvs.uptaxi.ru/pay/ckassa/actions.php?action=get_cards_only';
			} else if ($payment_system == 'tinkoff') {
				$url = 'http://kvs.uptaxi.ru/pay/tinkoff/actions.php?action=get_cards';
			} else if ($payment_system == 'liqpay') {
				$get_cards = $dbfirm->GetAll('
					SELECT
						card_masked AS "cardMask",
						rebill_id AS "cardToken",
						CASE
							WHEN substring(card_masked, 1, 1) = \'4\' THEN \'Visa\'
							WHEN substring(card_masked, 1, 1) = \'5\' THEN \'MasterCard\'
						ELSE
							\'\'
						END AS "cardType",
						\'active\' AS state
					FROM
						client_payment_method cpm,
						client c
					WHERE c.phone_number = ? AND c.id = cpm.client_id AND card_masked IS NOT NULL
				', $user_auth->login);
				$res['card']['add'] = true;
				$res['card']['available'] = $get_cards;
				return parent::success($res);
			} else if ($payment_system == 'beeline.kg') {
				// $phone_prefixes = $dbfirm->GetAll('SELECT kod FROM def WHERE id_provider = 6');

				// foreach ($phone_prefixes as $prefix) {
				// 	if (strpos($user_auth->login, $prefix['kod']) !== FALSE && !in_array('beeline.kg', $res['methods']))
				// 		array_push($res['methods'], 'beeline.kg');
				// }

				array_push($res['methods'], 'beeline.kg');

				return parent::success($res);
			} else if ($payment_system == 'payme') {
				$get_cards = $dbfirm->GetAll('
					SELECT
						card_masked AS "cardMask",
						rebill_id AS "cardToken",
						CASE
							WHEN substring(card_masked, 1, 1) = \'4\' THEN \'Visa\'
							WHEN substring(card_masked, 1, 1) = \'5\' THEN \'MasterCard\'
						ELSE
							\'\'
						END AS "cardType",
						\'active\' AS state
					FROM
						client_payment_method cpm,
						client c
					WHERE c.phone_number = ? AND c.id = cpm.client_id AND card_masked IS NOT NULL
				', $user_auth->login);
				$res['card']['add'] = true;
				$res['card']['available'] = $get_cards;
				array_push($res['methods'], 'payme');
				return parent::success($res);
			} else if ($payment_system == 'wayforpay') {
				$get_cards = $dbfirm->GetAll('
					SELECT
						card_masked AS "cardMask",
						rebill_id AS "cardToken",
						CASE
							WHEN substring(card_masked, 1, 1) = \'4\' THEN \'Visa\'
							WHEN substring(card_masked, 1, 1) = \'5\' THEN \'MasterCard\'
						ELSE
							\'\'
						END AS "cardType",
						\'active\' AS state
					FROM
						client_payment_method cpm,
						client c
					WHERE c.phone_number = ? AND c.id = cpm.client_id AND card_masked IS NOT NULL
				', $user_auth->login);
				$res['card']['add'] = true;
				$res['card']['available'] = $get_cards;
				return parent::success($res);
			} else if ($payment_system == 'uniteller') {
				$payment_data = $dbfirm->GetRow('
					SELECT
						get_opt_s(\'terminal_key\') AS public_key,
						get_opt_s(\'secret_key\') AS private_key,
						get_opt_s(\'service_id\') AS login
				');

				$url = 'https://wpay.uniteller.ru/cardv3/';
				$params = [
					'Shop_IDP' => $payment_data['public_key'],
					'Login' => $payment_data['login'],
					'Password' => $payment_data['private_key'],
					'Customer_IDP' => trim(base64_encode(openssl_encrypt($user_auth->id_firm.' '.$user_auth->login, 'AES-128-ECB', 'yabteb9vzlomal')), '=')
				];

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
				$get_cards = curl_exec($ch);
				curl_close ($ch);

				$ans = self::csv_to_array($get_cards, ';');

				$get_cards = [];

				if (count($ans) != 0 && !isset($ans[0]['ErrorCode']))
				{
					foreach ($ans as $key => $val) {
						$get_cards[] = [
							'cardMask' => $val['CardNumber'],
							'cardToken' => $val['Card_IDP'],
							'cardType' => $val['CardType'],
							// 'state' => (($val['CardStatus'] == 1)?'active':'passive')
							'state' => (($val['CardStatus'] == 0)?'active':'passive')
						];
					}
				}

				$res['card']['add'] = true;
				$res['card']['available'] = $get_cards;
				return parent::success($res);
			} else if ($payment_system == 'rncb') {
				/*$get_cards = $dbfirm->GetAll('
					SELECT
						card_masked AS "cardMask",
						rebill_id AS "cardToken",
						CASE
							WHEN substring(card_masked, 1, 1) = \'4\' THEN \'Visa\'
							WHEN substring(card_masked, 1, 1) = \'5\' THEN \'MasterCard\'
						ELSE
							\'\'
						END AS "cardType",
						\'active\' AS state
					FROM
						client_payment_method cpm,
						client c
					WHERE c.phone_number = ? AND c.id = cpm.client_id AND card_masked IS NOT NULL
				', $user_auth->login);
				$res['card']['add'] = true;
				$res['card']['available'] = $get_cards;*/

				return parent::success($res);
			} else {
				return parent::success($res);
			}

			$res['card']['add'] = true;
			$res['card']['available'] = [];

			$firm_id = (int)$dbfirm->GetRow('SELECT get_opt_s(\'id_firm\') AS val')['val'];

			$params = [
				'firm' => $firm_id,
				'phone' => urlencode($user_auth->login),
				'password' => md5($dbfirm->GetRow('SELECT pass FROM client WHERE phone_number = ? LIMIT 1', $user_auth->login)['pass'])
			];

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

			$get_cards = curl_exec($ch);

			curl_close ($ch);

			$ans = json_decode($get_cards);

			if ($payment_system == 'ckassa')
			{
				if ($ans->error == 0)
					$res['card']['available'] = $ans->cards;
			} else if ($payment_system == 'tinkoff') {
				$arr_cards = [];

				foreach ($ans as $val) {
					$arr_cards[] = (object)[
						'cardMask' => $val->card_masked,
						'cardToken' => $val->RebillId,
						'cardType' => (substr($val->card_masked, 0, 1) == '4')?'Visa':'MasterCard',
						'state' => ($val->Status == 'A')?'active':'expired'
					];
				}

				$res['card']['available'] = $arr_cards;
			}
		}

		return parent::success($res);
	}

	public function csv_to_array($filename_or_string = '', $delimiter = ',')
	{
		$header = NULL;
		$data = [];

		if(!file_exists($filename_or_string) || !is_readable($filename_or_string))
		{
			//String
			foreach (explode("\n", $filename_or_string) as $str)
			{
				if (empty($str)) continue;

				$row = explode($delimiter, $str);

				if (!$header)
					$header = $row;
				else
					$data[] = array_combine($header, $row);
			}
		} else {
			//File
			if (($handle = fopen($filename_or_string, 'r')) !== FALSE)
			{
				while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
				{
					if (!$header)
						$header = $row;
					else
						$data[] = array_combine($header, $row);
				}
				fclose($handle);
			}
		}

		return $data;
	}
}