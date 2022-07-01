<?php

namespace Uptaxi\Controllers\Services;

use Uptaxi\Controllers\MainController;

class PhoneMask extends MainController
{
	public function Main()
	{
		$dbbirga = new \DBbirga();
		if (!$dbbirga->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbbirga->GetAll('SELECT name, country_code, phone_mask, flag_img FROM country ORDER BY name');

		return parent::success($res);
	}
}