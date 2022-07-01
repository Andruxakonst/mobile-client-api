<?php

namespace Uptaxi\Controllers\Order;

use Uptaxi\Controllers\MainController;

class Cancel extends MainController
{
	public function Main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		$reason = $_POST['reason'] ?? null;

		if (empty($order_id))
			return parent::errCli('Post params order_id or reason is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order_id, $reason]);
		return parent::success(true);
	}
}