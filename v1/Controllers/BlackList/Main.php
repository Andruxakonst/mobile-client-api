<?php

namespace Uptaxi\Controllers\BlackList;

use Uptaxi\Controllers\MainController;

class Main extends MainController
{
	public function add()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$driver_call_sign = $_POST['driver_call_sign'] ?? null;
		$comment = $_POST['comment'] ?? null;

		if (empty($driver_call_sign))
			return parent::errCli('Post parameter driver_call_sign is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = (0 === $dbfirm->GetRow('SELECT res FROM
			fn_client_fb_list(?, ?, ?, ?, ?, NULL)', [
				$user_auth->login,
				$driver_call_sign,
				$comment,
				'black',
				'add'
			])['res']);

		return parent::success($res);
	}

	public function drivers()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT * FROM fn_get_client_fb_list(?, ?)', [
			$user_auth->login,
			'black'
		]);

		return parent::success($res);
	}

	public function unblock()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$block_id = (int)$_POST['block_id'];

		if (empty($block_id))
			return parent::errCli('Post parameter block_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = (0 === $dbfirm->GetRow('SELECT res FROM
			fn_client_fb_list(?, (SELECT pozivnoi FROM bort WHERE id = ?), NULL, ?, ?, NULL)', [
				$user_auth->login,
				$block_id,
				'black',
				'remove'
			])['res']);

		return parent::success($res);
	}

	public function unblock_all()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = (0 === $dbfirm->GetRow('SELECT res FROM
			fn_client_fb_list(?, NULL, NULL, ?, NULL, TRUE)', [
				$user_auth->login,
				'favorite'
			])['res']);

		return parent::success($res);
	}
}