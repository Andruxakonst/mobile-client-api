<?php

namespace Uptaxi\Controllers\Account;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Get extends MainController
{
	public function pass($type = 'sms')
	{
		$login = $_POST['login'];
		$id_firm = (int)$_POST['id_firm'] ?? null;
		$tr_ans = (bool)@$_POST['tr_ans'] ?? false; //bool of undefined index return notice
		$sign = $_POST['sign'] ?? null;

		if (empty($login) || empty($id_firm) || empty($sign))
			return parent::errCli('Login, id_firm or sign is empty or not valid', -4);

		$check_sign = md5(md5($login).md5($id_firm).$id_firm.md5($login).$login);

		if ($check_sign != $sign)
			return parent::errCli('Check your request on correct o_O');

		$dbfirm = new \DBfirm($id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT fn_get_client_passwd(?, ?) AS val', [$login, $type])['val'];

		if ($tr_ans)
		{
			$ans = '';
			switch ($res) {
				case 'not_allowed':
					$ans = Language::data('global')['unknown_error'].' #AG2RNR1PS';
					break;
				case 'not_allowed_for_firm':
					$ans_arr = explode('$$', Language::data('account')['not_allowed_for_firm']);
					$count_rides = $dbfirm->GetRow('SELECT coalesce(get_opt_i(\'count_uspeh_allow_pass_app\'), 1) AS val')['val'];
					$ans = $ans_arr[0].$count_rides.$ans_arr[1];
					break;
				case 'wait_sms':
					$ans = Language::data('account')['wait_sms'];
					break;
				case 'wait_call':
					$ans = Language::data('account')['wait_call'];
					break;
				case 'send_sms_exceed':
					$ans = Language::data('account')['send_sms_exceed'];
					break;
				case 'call_exceed':
					$ans = Language::data('account')['send_sms_exceed'];
					break;
				default:
					$ans = Language::data('global')['unknown_error'].' #AG2LNF1PS';
					break;
			}
			return parent::success(['key' => $res, 'message' => $ans]);
		}

		return parent::success($res);
	}

	public function user_data()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT
			email,
			user_name AS name,
			coalesce(adm, FALSE) AS reception,
			uspeh_ AS successful_orders
		FROM
			client
		WHERE
			phone_number=?', $user_auth->login);

		// $block = $dbfirm->GetRow('SELECT coalesce(c.block_do > now(), false) AS block, (SELECT lng_key FROM client_block_reason WHERE id = c.id_block_reason AND show_to_client IS TRUE) AS reason FROM client c WHERE phone_number = ?', $user_auth->login);
		$block = $dbfirm->GetRow('SELECT
			coalesce(block_do > now(), false) AS block,
			block_prichina AS reason
		FROM
			client
		WHERE
			phone_number = ?', $user_auth->login);

		$res['blocked'] = $block['block'];

		if ($res['blocked'])
			$res['block_reason'] = $block['reason'];
			// $res['block_reason'] = Language::data('block_reason')[$block['reason']];

		return parent::success($res);
	}

	public function driver_who_invited()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT pozivnoi AS val FROM bort b LEFT JOIN client c ON b.id = c.id_predka_bort WHERE c.phone_number = ?', $user_auth->login)['val'];

		return parent::success($res);
	}

	public function promo_actions_friend_list()
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

		$res = $dbfirm->GetAll('SELECT
			TO_CHAR(pc.dt,\'YYYY.MM.DD HH24:MI\') AS dt,
			(SELECT user_name FROM client WHERE id=pc.id_client) AS name,
			(SELECT regexp_replace(phone_number, \'(\d{4})(\d{4})(\d{3})\', \'\1****\3\') FROM client WHERE id=pc.id_client) AS phone
		FROM
			promocods_clients pc
		WHERE id_promocod = (SELECT id FROM promocods_clients_owner WHERE promocod = ?) ORDER BY pc.dt DESC', $promocode);
		return parent::success($res);
	}

	public function promo_actions()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			pa.id,
			pa.name_action,
			TO_CHAR(pa.dt_start,\'YYYY.MM.DD HH24:MI\') AS dt_start,
			TO_CHAR(pa.dt_end,\'YYYY.MM.DD HH24:MI\') AS dt_end,
			pa.id_news AS news_id,
			pa.text_for_friend,
			(SELECT 
					short_text
				FROM
					news
				WHERE
					id = id_news
					AND razdel=\'promo_action\'
					AND end_ is not TRUE
			) AS short_text,
			CASE WHEN array[(SELECT id FROM client WHERE phone_number=?)]::integer[] <@ n.id_clients::integer[] THEN true ELSE false END AS readed
			FROM
				promocod_actions pa RIGHT JOIN news n ON n.id=pa.id_news
			WHERE
				(pa.dt_end>now() OR pa.dt_end IS NULL)
				AND (now()>pa.dt_start OR pa.dt_start IS NULL)
				AND n.end_ is not TRUE
				AND n.razdel = \'promo_action\'
				AND (n.gorod = (SELECT gorod FROM slugbi WHERE id=?) OR n.gorod = \'\' OR n.gorod IS NULL)
				AND (array[?]::integer[] <@ n.service_ids::integer[] OR n.service_ids IS NULL)
				AND pa.id_news is not NULL
			ORDER BY pa.id DESC', [$user_auth->login, $user_auth->service, $user_auth->service]);
		return parent::success($res);
	}

	public function promocode()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$action_id = (int)$_POST['action_id'] ?? null;

		if (empty($action_id))
			return parent::errCli('Post params action_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT promocod_ AS promo_code, short_text_news, body_news FROM get_promocod_generate((SELECT id FROM client WHERE phone_number = ?),?)', [$user_auth->login, $action_id]);

		return parent::success($res);
	}

	public function promodesc()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		return parent::success('Делитесь своим промо-кодом с друзьями и получайте с каждой поездки 1% от стоимости на свой бонусный счет. Собирай бонусы и езди на такси бесплатно.');
	}

	public function not_ring()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT coalesce(not_ring_if_online, false) AS not_ring FROM client WHERE phone_number = ?', $user_auth->login)['not_ring'];

		return parent::success($res);
	}
}