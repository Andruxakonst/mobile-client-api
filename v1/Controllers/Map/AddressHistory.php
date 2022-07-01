<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;

class AddressHistory extends MainController
{
	public function Main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$data = $dbfirm->GetAll('
			SELECT foo.ves AS weight, foo.kol_ AS count, foo.p AS p, foo.id_adres AS address_id, coalesce(adress.city, org.city) AS city, foo.adres AS address, adress.street, adress.dom AS house, foo.podezd AS entrance, foo.id_crossroad AS crossroad_id, foo.id_mesta AS place_id, foo.id_org, foo.id_all FROM 
			(SELECT * FROM get_last_adress_klienta2(?, now()::time) WHERE p IS NOT NULL LIMIT 5) foo
			LEFT JOIN dbo.adress ON id_adres=adress.id
			LEFT JOIN dbo.org ON id_org=org.id
			', $user_auth->login);

		$data = array_map(function($val)
		{
			$coords = array_reverse(explode(',', trim($val['p'], '()')));
			$val['p'] = [(float)$coords[0], (float)$coords[1]];
			return $val;
		}, $data);

		return parent::success($data);
	}
}