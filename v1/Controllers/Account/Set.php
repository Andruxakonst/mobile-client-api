<?php

namespace Uptaxi\Controllers\Account;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Set extends MainController
{
	public function device_token()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$device_token = $_POST['device_token'] ?? null;
		$fcmpro = (int)$_POST['fcmpro'] ?? null;

		if (empty($device_token))
			return parent::errCli('Device token is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$set_device_token = (false !== $dbfirm->Execute('UPDATE client SET device_token=?, fcmpro=? WHERE phone_number=?', [$device_token, $fcmpro, $user_auth->login]));

		return parent::success($set_device_token);
	}

	public function user_name()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$user_name = $_POST['name'] ?? null;

		if (empty($user_name))
			return parent::errCli('Name is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$set_user_name = (false !== $dbfirm->Execute('UPDATE client SET user_name=? WHERE phone_number=?', [$user_name, $user_auth->login]));

		return parent::success($set_user_name);
	}

	public function user_email()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$user_email = $_POST['email'] ?? null;

		if (empty($user_email))
			return parent::errCli('Email is empty', -4);

		if (!filter_var($user_email, FILTER_VALIDATE_EMAIL))
			return parent::errCli('Email is not valid');

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$set_user_email = (false !== $dbfirm->Execute('UPDATE client SET email=? WHERE phone_number=?', [$user_email, $user_auth->login]));

		return parent::success($set_user_email);
	}

	public function pcode()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$pcode = $_POST['pcode'] ?? null;

		if (empty($pcode))
			return parent::errCli('Promocode is empty', -4);

		// if (!preg_match('/^[A-Z0-9_.]{3,25}$/', $pcode))
		// 	return parent::errCli('Promo code is not valid', -7);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		// promocode_is_undefined - недействителен или не найден (код -1)
		// success - успешно (код 0)
		// already_activated - уже активирован (код -2)
		$res = $dbfirm->GetRow('SELECT * FROM fn_add_client_promo(NULL, ?, ?)', [$pcode, $user_auth->login])['res_message'];

		if ($res === 'promocode_is_undefined')
			return parent::errCli(Language::data('account')['promo_code_not_found'], -1);

		if ($res === 'already_activated')
			return parent::errCli(Language::data('account')['you_already_used_this_promotional_code'], -2);

		return parent::success(true);
	}

	public function promocode()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$promocode = $_POST['promocode'] ?? null;

		if (empty($promocode))
			return parent::errCli('Promocode is empty', -4);

		if (!preg_match('/^[A-Z0-9_.]{3,25}$/', $promocode))
			return parent::errCli('Promo code is not valid', -7);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT * FROM check_promocod2((SELECT id FROM client WHERE phone_number = ?), ?, ?)', [$user_auth->login, $user_auth->service, $promocode])['check_promocod2'];

		$status_id = (strpos($res, 'promo_code_activated') !== false)?1:2;

		$ans = explode('$$', $res);

		if (count($ans) == 1)
		{
			$res = Language::data('account')[$ans[0]] ?: Language::data('global')['unknown_error'].' #AS2LNF3PE';
		} else if (count($ans) == 2) {
			$ans_arr = explode('$$', Language::data('account')[$ans[0]]);
			$res = $ans_arr[0].$ans[1].$ans_arr[1] ?: Language::data('global')['unknown_error'].' #AS2LNF3PE';
		} else {
			$res = Language::data('global')['unknown_error'].' #AS2RNR3PE';
		}

		return parent::success($res, $status_id);
	}

	public function promocode_edit()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$promocode_old = $_POST['promocode_old'] ?? null;
		$promocode_new = $_POST['promocode_new'] ?? null;

		if (empty($promocode_old) || empty($promocode_new))
			return parent::errCli('Post param promocode_old or promocode_new is empty', -4);

		if (!preg_match('/^[A-Z0-9_.]{3,25}$/', $promocode_new))
			return parent::errCli('Promo code is not valid', -7);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$check_on_exist = $dbfirm->GetRow('SELECT id FROM promocods_clients_owner WHERE replace(promocod, \'0\', \'O\') = replace(upper(?), \'0\', \'O\')', $promocode_new)['id'];

		if (!empty($check_on_exist))
			return parent::errCli('Promo code exist', -7);

		$check_old_promocode = $dbfirm->GetRow('SELECT pco.id FROM promocods_clients_owner pco, client c WHERE pco.id_client_owner = c.id AND c.phone_number = ? AND promocod = ?', [$user_auth->login, $promocode_old]);

		if (empty($check_old_promocode))
			return parent::errCli('Old promo code is not yours', -7);

		$res = (false !== $dbfirm->Execute('UPDATE promocods_clients_owner SET promocod = ? WHERE promocod = ?', [$promocode_new, $promocode_old]));

		return parent::success($res);
	}

	public function device_info()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$info = $_POST['info'] ?? null;

		if (empty($info))
			return parent::errCli('Post param info is empty', -4);

		$info = str_replace('""AppVersion"=>', '","AppVersion"=>', $info); //Fix comma in string

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = (false !== $dbfirm->Execute('UPDATE client SET hs_info = ? WHERE phone_number = ?', [$info, $user_auth->login]));

		return parent::success($res);
	}

	public function driver_who_invited()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$driver_who_invited = trim($_POST['driver_who_invited']) ?? null;

		if (empty($driver_who_invited))
			return parent::errCli('Post param driver_who_invited is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT add_referal2(?, NULL, ?)', [$driver_who_invited, $user_auth->login])['add_referal2'];

		if ($res == 0)
			return parent::errCli(Language::data('account')['driver_who_invited_0']);

		if ($res == 1)
			return parent::success(Language::data('account')['driver_who_invited_1']);

		if ($res == 2)
			return parent::errCli(Language::data('account')['driver_who_invited_2']);

		if ($res == 3)
			return parent::errCli(Language::data('account')['driver_who_invited_3'], -7);

		return parent::errSer(Language::data('global')['unknown_error'].' #AS2DWI1L');
	}

	public function not_ring()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$not_ring = ($_POST['not_ring'] == 'true')?true:null;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$set_not_ring = (false !== $dbfirm->Execute('UPDATE client SET not_ring_if_online=? WHERE phone_number=?', [$not_ring, $user_auth->login]));

		return parent::success($set_not_ring);
	}
}