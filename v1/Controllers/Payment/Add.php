<?php

namespace Uptaxi\Controllers\Payment;

use Uptaxi\Controllers\MainController;

class Add extends MainController
{
	public function main()
	{
		//Получаем расшифрованные данные Auth заголовка запроса
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;
		//получение данных БД по id фирмы. Id берется из объекта Auth заголовка запроса
		$dbfirm = new \DBfirm($user_auth->id_firm);
		//проверяем соединение с БД
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);
		//получаем из БД систему оплаты, привязаную к фирме
		$payment_system = $dbfirm->GetRow('
			SELECT get_opt_s(\'payment_system_in_client_app\') AS val
			')['val'];
		//Присвоение URL адреса для получения ссылки на добавление карты
		switch($payment_system){
			case 'ckassa':
				$url = 'http://kvs.uptaxi.ru/pay/ckassa/actions.php?action=add_card';
				break;
			case 'tinkoff':
				$url = 'http://kvs.uptaxi.ru/pay/tinkoff/actions.php?action=add_card';
				break;
			case 'payme':
				$url = 'http://'.$_SERVER['HTTP_HOST'].str_replace('payment/add', '', $_SERVER['REQUEST_URI']).'payment/checkout?key='.trim(base64_encode(openssl_encrypt($user_auth->login.' '.date("Y-m-d H:i:s").' '.$user_auth->id_firm.' '.$user_auth->service.' '.$payment_system, 'AES-128-ECB', 'yabteb9vzlomal')), '=').'&token='.$this->request->getHeaderLine('Auth').'&phone='.$user_auth->login;
				return parent::success($url);
				break;
			case 'liqpay':
				//str_replace used for changing v1 in url with lang
				$url = 'http://'.$_SERVER['HTTP_HOST'].str_replace('payment/add', '', $_SERVER['REQUEST_URI']).'payment/checkout?key='.trim(base64_encode(openssl_encrypt($user_auth->login.' '.date("Y-m-d H:i:s").' '.$user_auth->id_firm.' '.$user_auth->service.' '.$payment_system, 'AES-128-ECB', 'yabteb9vzlomal')), '=');
				return parent::success($url);
				break;
			case 'wayforpay':
				$url = 'http://'.$_SERVER['HTTP_HOST'].str_replace('payment/add', '', $_SERVER['REQUEST_URI']).'payment/checkout?key='.trim(base64_encode(openssl_encrypt($user_auth->login.' '.date("Y-m-d H:i:s").' '.$user_auth->id_firm.' '.$user_auth->service.' '.$payment_system, 'AES-128-ECB', 'yabteb9vzlomal')), '=');
				return parent::success($url);
				break;
			case 'uniteller':
				$url = 'http://'.$_SERVER['HTTP_HOST'].str_replace('payment/add', '', $_SERVER['REQUEST_URI']).'payment/checkout?key='.trim(base64_encode(openssl_encrypt($user_auth->login.' '.date("Y-m-d H:i:s").' '.$user_auth->id_firm.' '.$user_auth->service.' '.$payment_system, 'AES-128-ECB', 'yabteb9vzlomal')), '=');
				return parent::success($url);
				break;
			case 'rncb':
				$url = 'http://'.$_SERVER['HTTP_HOST'].str_replace('payment/add', '', $_SERVER['REQUEST_URI']).'payment/checkout?key='.trim(base64_encode(openssl_encrypt($user_auth->login.' '.date("Y-m-d H:i:s").' '.$user_auth->id_firm.' '.$user_auth->service.' '.$payment_system, 'AES-128-ECB', 'yabteb9vzlomal')), '=');
				return parent::success($url);
				break;
			default:
				return parent::errSer('Can\'t determine payment system', -5);
		}
		
		//получаем id фирмы из БД
		$firm_id = (int)$dbfirm->GetRow('SELECT get_opt_s(\'id_firm\') AS val')['val'];
		//присваиваем значения параметрам для выполнения запроса
		$params = [
			'firm' => $firm_id,
			'phone' => urlencode($user_auth->login),
			'password' => md5($dbfirm->GetRow('SELECT pass FROM client WHERE phone_number = ? LIMIT 1', $user_auth->login)['pass'])
		];
		//открываем соедиение 
		$ch = curl_init();
		//Устанавливаем опции для создния запроса
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

		//запрашиваем страницу добавления карты 
		$response = curl_exec ($ch);
		//Закрываем соединение
		curl_close ($ch);
		//Отправляем на основной запрос ответ в виде url адрес платежа
		switch($payment_system){
			case 'ckassa':
				if (json_decode($response)->error != 0)
					return parent::errCli('Something wrong');
				return parent::success(json_decode($response)->regcard->url);
			break;
			case 'tinkoff':
				if (json_decode($response)->ErrorCode != 0)
					return parent::errCli('Something wrong');
				return parent::success(json_decode($response)->PaymentURL);
			break;
			default:
			return parent::errSer('Can\'t determine payment system', -5);
		}
	}
}