<?php

namespace Uptaxi\Controllers\Banners;

use Uptaxi\Controllers\MainController;

class Set extends MainController
{
	public function touched()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$banner_id = (int)$_POST['banner_id'] ?? null;

		if (empty($banner_id))
			return parent::errCli('Post parameter banner_id is empty or not valid', -4);

		//Temporary exit for disable inserting
		return parent::success(true);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->Execute('INSERT INTO banners_shown (banner_id, client_id) VALUES (?, (SELECT id FROM client WHERE phone_number = ?))', [$banner_id, $user_auth->login]);

		if (!$res)
			return parent::errSer(Language::data('global')['unknown_error'].' #BS1TIF');

		return parent::success($res);
	}
}