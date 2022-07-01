<?php

namespace Uptaxi\Controllers\Order;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Set extends MainController
{
	public function add_price()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		$add_price = (round((float)$_POST['add_price'], 2))?:null;

		if (empty($order_id) || !isset($_POST['add_price']))
			return parent::errCli('Post params order_id or add_price is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//Checking on limits
		$dead_cost = $dbfirm->GetRow('SELECT coalesce(get_opt_s(\'granica_snijenia_stoimosti_v_cliente\')::numeric, 0) AS val')['val'];

		if((int)$add_price < $dead_cost)
			return parent::errCli('You cannot set add_price lower than '.$dead_cost);

		//Checking order and driver
		$check = $dbfirm->GetRow('SELECT coalesce(id_bort, 0) AS bort FROM orders WHERE end_ IS NULL AND id = ? AND phone_number = ?', [$order_id, $user_auth->login])['bort']??0;// operator_press_ok - removed by aab que

		if ($check > 0)
			return parent::errCli('You cannot set add_price when driver on order, order not found or order ended');

		//Update additional price
		$res = (false !== $dbfirm->Execute('/*mobile_API [order/set/add_price] (wxrjob)*/UPDATE orders SET nakrutka_ivr = ?::numeric, pereschitat = TRUE WHERE id = ?', [$add_price, $order_id]));

		if (!$res)
			return parent::errSer('An unknown error has occurred while update add_price on order #'.$order_id);

		return parent::success($res);
	}

	public function bonus()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		$bonus = (round((float)$_POST['bonus'], 2))?:null;

		if (empty($order_id) || !isset($_POST['bonus']))
			return parent::errCli('Post params order_id or bonus is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//Checking opt
		$disallow_change = ($dbfirm->GetRow('/*mobile_API [order/set/bonus] (wxrjob)*/SELECT coalesce(get_opt_i(\'disable_change_bonus_in_order\'), 0) AS val')['val'] === 1);

		if ($disallow_change) //Checking order and driver
			$check = $dbfirm->GetRow('/*mobile_API [order/set/bonus] (wxrjob)*/SELECT coalesce(id_bort, 0) AS bort FROM orders WHERE end_ IS NULL AND id = ? AND phone_number = ?', [$order_id, $user_auth->login])['bort']??0;

		if ($disallow_change && $check > 0)
			return parent::errCli('You cannot change bonus when driver on order, order not found or order ended');

		$res = $dbfirm->GetRow('/*mobile_API [order/set/bonus] (wxrjob)*/SELECT * FROM change_bonus_in_order(?::integer, ?::varchar, ?::integer, ?::numeric) AS val', [$order_id, $user_auth->login, $user_auth->service, $bonus])['val'];

		if (!$res)
			return parent::errSer('An unknown error has occurred while update bonus on order #'.$order_id);

		return parent::success($res);
	}

	public function send_bonus()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$phone_number = $login = str_replace(' ', '', str_replace('-', '', $_POST['phone_number'])) ?? null;
		$bonus = (round((float)$_POST['bonus'], 2))?:null;
		$sign = $_POST['sign'] ?? null;

		if (empty($phone_number) || !isset($_POST['bonus']) || empty($sign))
			return parent::errCli('Post params phone_number, bonus or sign is empty or not valid', -4);

		$check_sign = md5(md5($user_auth->login).md5($phone_number).$_POST['bonus']);

		if ($check_sign != $sign)
			return parent::errCli('Check your request on correct o_O');

		if ($bonus <= 0)
			return parent::errCli('Selected value out of range');

		if ($user_auth->login == $phone_number)
			return parent::errCli(Language::data('order')['disallow_send_yourself']);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT * FROM fn_bonus_transfer(?::varchar, ?::varchar, ?::numeric, ?::integer) AS val', [$user_auth->login, $phone_number, $bonus, $user_auth->service])['val'];

		if ($res != 'ok')
			return parent::errCli(Language::data('order')[$res] ?: Language::data('global')['unknown_error'].' #OSB1LNF');

		return parent::success(true);
	}

	public function hybrid()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		$hybrid = filter_var($_POST['hybrid']??null, FILTER_VALIDATE_BOOLEAN)?true:null;

		if (empty($order_id) || !isset($_POST['hybrid']))
			return parent::errCli('Post params order_id or hybrid is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//Checking opt
		$disallow_change = TRUE;//($dbfirm->GetRow('/*mobile_API [order/set/hybrid] (wxrjob)*/SELECT coalesce(get_opt_i(\'disable_change_hybrid_in_order\'), 0) AS val')['val'] === 1);

		if ($disallow_change) //Checking order and driver
			$check = $dbfirm->GetRow('/*mobile_API [order/set/hybrid] (wxrjob)*/SELECT coalesce(id_bort, 0) AS bort FROM orders WHERE end_ IS NULL AND id = ? AND phone_number = ?', [$order_id, $user_auth->login])['bort']??0;

		if ($disallow_change && $check > 0)
			return parent::errCli('You cannot change type when driver on order, order not found or order ended');

		$res = $dbfirm->GetRow('/*mobile_API [order/set/hybrid] (wxrjob)*/SELECT * FROM fn_change_hybrid_or_regular(?::integer, CASE WHEN ?::boolean IS NOT NULL THEN TRUE ELSE FALSE END) AS val', [$order_id, $hybrid])['val'];

		if (!$res)
			return parent::errSer(Language::data('global')['unknown_error'].' #OSH1LNF');

		return parent::success($res);
	}
}