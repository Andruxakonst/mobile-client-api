<?php

namespace Uptaxi\Controllers\Account;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Auth extends MainController
{
	public function Main()
	{
		$login = str_replace(' ', '', str_replace('-', '', $_SERVER['PHP_AUTH_USER'])) ?? null;
		$pass = $_SERVER['PHP_AUTH_PW'] ?? null;
		$id_firm = (int)$_POST['id_firm'] ?? null;
		$service = (int)$_POST['service'] ?? null;
		$package_name = $_POST['package_name'] ?? null;

		if (empty($login) || empty($pass) || empty($id_firm) || empty($service))
			return parent::errCli('Login, password, id_firm or service is empty', -4);

		$dbfirm = new \DBfirm($id_firm);
		if (!$dbfirm->checkConnection($error))
			return parent::errSer('Error connecting to the DB', -3);

		$check_service = $dbfirm->GetRow('SELECT id FROM slugbi WHERE (show_mobile OR delivery) AND id = ?', $service)['id'];
		if (empty($check_service))
			return parent::errCli('Service with id '.$service.' not found', -5);

		$country_iso = $dbfirm->GetRow('SELECT get_opt_s(\'country_iso\') as val')['val'];
		if (empty($country_iso))
			return parent::errSer('Firm doesn\'t have country_iso in options', -5);

		$user = $dbfirm->GetRow('SELECT * FROM client WHERE phone_number = ? LIMIT 1', $login);
		if (hash('sha512', $user['pass']) !== strtolower($pass))
			return parent::errCli('Login failed', -1);

		$iv = getenv('IV');
		$mk = getenv('MK');
		$method = getenv('METHOD');

		$data = [
			'login' => $login,
			'id_firm' => $id_firm,
			'service' => $service,
			'country_iso' => $country_iso,
			'au_dt' => date("Y-m-d H:i:s"),
			'serv_ini' => $_SERVER['HTTP_HOST'],
			'p_n' => $package_name
		];

		$secret = trim(openssl_encrypt(json_encode($data), $method, $mk, false, $iv), '=');

		return parent::success($secret);

	}

	public function via_bot()
	{
		$login = $_POST['login'] ?? null;
		$id_firm = (int)$_POST['id_firm'] ?? null;
		$service = (int)$_POST['service'] ?? null;
		$package_name = $_POST['package_name'] ?? null;
		$bot_name = $_POST['bot_name'] ?? null;
		$sign = $_POST['sign'] ?? null;

		if (empty($login) || empty($sign) || empty($id_firm) || empty($service))
			return parent::errCli('Login, sign, id_firm or service is empty', -4);

		$login = '+'.$login;

		if ($sign !== 'bbXUvW3Y83nYVibUE8LsxE6QKh5f')
			return parent::errCli('Incorrect sign', -4);

		$dbfirm = new \DBfirm($id_firm);
		if (!$dbfirm->checkConnection($error))
			return parent::errSer('Error connecting to the DB', -3);

		$check_service = $dbfirm->GetRow('SELECT id FROM slugbi WHERE (show_mobile OR delivery) AND id = ?', $service)['id'];
		if (empty($check_service))
			return parent::errCli('Service with id '.$service.' not found', -5);

		$country_iso = $dbfirm->GetRow('SELECT get_opt_s(\'country_iso\') as val')['val'];
		if (empty($country_iso))
			return parent::errSer('Firm doesn\'t have country_iso in options', -5);

		$res = $dbfirm->GetRow('SELECT fn_get_client_passwd(?, \'none\') AS res', $login)['res'];
		if (empty($res)||$res !== 'success')
			return parent::errCli('Login failed', -1);

		$dbfirm->Execute('INSERT INTO
			log_login_via_bot
		(
			client_login,
			service_id,
			package_name,
			bot_name
		) VALUES (?, ?, ?, ?)', [$login, $service, $package_name, $bot_name]);

		$iv = getenv('IV');
		$mk = getenv('MK');
		$method = getenv('METHOD');

		$data = [
			'login' => $login,
			'id_firm' => $id_firm,
			'service' => $service,
			'country_iso' => $country_iso,
			'au_dt' => date("Y-m-d H:i:s"),
			'serv_ini' => $_SERVER['HTTP_HOST'],
			'p_n' => $package_name
		];

		$secret = trim(openssl_encrypt(json_encode($data), $method, $mk, false, $iv), '=');

		return parent::success($secret);
	}
}