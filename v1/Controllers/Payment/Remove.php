<?php

namespace Uptaxi\Controllers\Payment;

use Uptaxi\Controllers\MainController;

class Remove extends MainController
{
	public function main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$card_token = $_POST['card_token'] ?? null;

		if (empty($card_token))
			return parent::errCli('Post params card_token is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$payment_system = $dbfirm->GetRow('
			SELECT get_opt_s(\'payment_system_in_client_app\') AS val
			')['val'];

		if ($payment_system == 'ckassa')
		{
			$url = 'http://kvs.uptaxi.ru/pay/ckassa/actions.php?action=remove_cards';
		} else if ($payment_system == 'tinkoff') {
			$url = 'http://kvs.uptaxi.ru/pay/tinkoff/actions.php?action=remove_cards';
		} else if ($payment_system == 'liqpay') {
			$res = (false !== $dbfirm->Execute('
				DELETE FROM client_payment_method cpm USING client c
				WHERE cpm.client_id = c.id AND c.phone_number = ? AND cpm.rebill_id = ?
			', [$user_auth->login, $card_token]));
			return parent::success($res);
		} else if ($payment_system == 'wayforpay') {
			$res = (false !== $dbfirm->Execute('
				DELETE FROM client_payment_method cpm USING client c
				WHERE cpm.client_id = c.id AND c.phone_number = ? AND cpm.rebill_id = ?
			', [$user_auth->login, $card_token]));
			return parent::success($res);
		} else if ($payment_system == 'payme') {
			$payment_data = $dbfirm->GetRow('
				SELECT
				get_opt_s(\'payment_url\') AS pay_url,
				get_opt_s(\'terminal_key\') AS public_key
			');
			$data["id"] = 123;
			$data["method"] = "cards.remove";
			$data["params"]["token"] = $card_token;

			$resPay = getPayMe($payment_data["pay_url"], $data, $payment_data["public_key"]);
			if(!empty($resPay["result"])&&$resPay["result"]["success"] == true){
				echo 'OK : card delete success';
				$res = (false !== $dbfirm->Execute('
				DELETE FROM client_payment_method cpm USING client c
				WHERE cpm.client_id = c.id AND c.phone_number = ? AND cpm.rebill_id = ?
			', [$user_auth->login, $card_token]));
			return parent::success($res);
			}else{
				echo 'ERROR : card delete for payMe: '.json_encode($resPay);
				return parent::success($res);
			};
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
				'Card_IDP' => $card_token,
				'Customer_IDP' => trim(base64_encode(openssl_encrypt($user_auth->id_firm.' '.$user_auth->login, 'AES-128-ECB', 'yabteb9vzlomal')), '='),
				'Action' => 2
			];

			$remove_cards = [];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
			$remove_cards = curl_exec($ch);
			curl_close ($ch);

			$ans = Available::csv_to_array($remove_cards, ';');

			if (count($ans) != 0 && isset($ans[0]['ErrorCode']) && $ans[0]['ErrorCode'] == 0)
				return parent::success(true);

			return parent::errSer($ans[0]['ErrorMessage']);
		} else {
			return parent::errSer('Can\'t determine payment system', -5);
		}

		$firm_id = (int)$dbfirm->GetRow('SELECT get_opt_s(\'id_firm\') AS val')['val'];

		if ($payment_system == 'tinkoff')
			$card_token = self::get_tinkoff_card_id($dbfirm, $user_auth, $card_token);

		$params = [
			'firm' => $firm_id,
			'phone' => urlencode($user_auth->login),
			'password' => md5($dbfirm->GetRow('SELECT pass FROM client WHERE phone_number = ? LIMIT 1', $user_auth->login)['pass']),
			'card_token' => $card_token
		];

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

		$response = curl_exec ($ch);

		curl_close ($ch);

		if ($payment_system == 'ckassa')
		{
			if (json_decode($response)->error != 0)
				return parent::errCli('Something wrong');
		} else if ($payment_system == 'tinkoff') {
			if (json_decode($response)->ErrorCode != 0)
				return parent::errCli('Something wrong');
		}

		return parent::success(true);
	}

	private function get_tinkoff_card_id($dbfirm, $user_auth, $card_token)
	{
		$url = 'http://kvs.uptaxi.ru/pay/tinkoff/actions.php?action=get_cards';

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

		foreach ($ans as $val) {
			if ($val->RebillId == $card_token)
				return $val->id;
		}
	}
}

function getPayMe($url,$dataSend, $Auth){
	$headers[] = 'X-Auth: '.$Auth;
	$data_string = json_encode ($dataSend, JSON_UNESCAPED_UNICODE);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);                                //устанавливаем адрес
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                        //Вывод в строку вместо прямого вывода в браузер
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                        //Игнорируем верификацию сертификата
	curl_setopt($ch, CURLOPT_POST, 1);                                  //Указываем, что у нас POST запрос
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                 //Добавляем переменные
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);                     //Добавляем массив заголовков
	$output = curl_exec($ch);                                           //Присваиваем ответ переменной
	curl_close($ch);
	if ($output === FALSE) {                                            //Проверяем что не ошибка
		// Если произошла ошибка! 
		return 'Error: ' . curl_error($ch);
	}else{
		return json_decode($output, true);                              //строку JSON в ассоциативный масиив
	};
}