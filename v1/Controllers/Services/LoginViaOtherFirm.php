<?php

namespace Uptaxi\Controllers\Services;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class LoginViaOtherFirm extends MainController
{
	public function Main()
	{
		//Check user auth
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		//Check post param 'idfirm' and 'service' //P.S. idfirm and service can't be zero (0)
		$idfirm = (int)$_POST['idfirm'] ?? null;
		$service = (int)$_POST['service'] ?? null;
		$package_name = $_POST['package_name'] ?? null;
		if (empty($idfirm) or empty($service))
			return parent::errCli('Required parameter is empty (post:idfirm and/or post:service)', -4);

		//Connect to DB and check connection to user firm
		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//Connect to Birga and check connection
		$dbbirga = new \DBbirga();
		if (!$dbbirga->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//Get list of real firms id
		$get_list_idfirm = $dbbirga->GetAll('select id, web_server from firms');

		//Check new firm id if exist in real list
		if (array_search($idfirm, array_column($get_list_idfirm, 'id')) === false)
			return parent::errSer('Firm with id: '.$idfirm.' does not exist', -5);

		//Connect and check connection to new firm
		$dbfirmnew = new \DBfirm($idfirm);
		if (!$dbfirmnew->checkConnection())
			return parent::errSer('Error connecting to the DBn', -3);

		//Get available service id in new firm
		$get_available_service = $dbfirmnew->GetAll('select id from slugbi where show_mobile=true');

		//Check availability of service id
		if (array_search($service, array_column($get_available_service, 'id')) === false)
			return parent::errSer('Service with id: '.$service.' does not exist', -5);

		//Get and check country iso of new firm
		$country_iso = $dbfirmnew->GetRow('SELECT get_opt_s(\'country_iso\') as val')['val'];
		if (empty($country_iso))
			return parent::errSer('Firm doesn\'t have country_iso in options', -5);

		//Run func for transfer user login data, token and fcmpro to new firm
		$login_via_other_firm = $dbfirm->Execute('select client_out2(?, ?)', [$user_auth->login, $idfirm]);

		//Check transfering user data
		if(!$login_via_other_firm)
			return parent::errSer('Unknown error #1222156', -1);

		$iv = getenv('IV');
		$mk = getenv('MK');
		$method = getenv('METHOD');

		$serv_ini = $get_list_idfirm[array_search($idfirm, array_column($get_list_idfirm, 'id'))]['web_server'];

		$data = [
			'login' => $user_auth->login,
			'id_firm' => $idfirm,
			'service' => $service,
			'country_iso' => $country_iso,
			'au_dt' => date("Y-m-d H:i:s"),
			'serv_ini' => $serv_ini,
			'p_n' => $package_name
		];

		$secret = trim(openssl_encrypt(json_encode($data), $method, $mk, false, $iv), '=');

		return parent::success($secret);
	}
}