<?php

namespace Uptaxi\Controllers\Order;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;
use Uptaxi\Controllers\Order\CalcCreate;

class Get extends MainController
{
	public function options()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$car_options = $dbfirm->GetAll('
			SELECT
				col.critical,
				CASE WHEN col.fix IS NOT NULL THEN (CASE WHEN col.fix > 0 THEN \'+\'::text ELSE \'\'::text END) || col.fix || get_opt_s(\'valuta\' ::character varying)::text || \' \' ELSE \'\'::text END ||
				CASE WHEN col.proc IS NOT NULL THEN (CASE WHEN col.proc > 0 THEN \'+\'::text ELSE \'\'::text END) || col.proc || \'%\'::text ELSE \'\'::text END AS description,
				to_json(col.forbidden_options) AS forbidden_options,
				col.id,
				COALESCE(lr.custom, lr.initial, col.name) AS name
				FROM car_options_list col
				LEFT JOIN client
				ON client.phone_number = ?
				LEFT JOIN lang_resource lr ON lr.key = \'option_\'||col.id||\'.name\' AND lr.lang = ? AND for_what = \'t.car_options_list\'
				WHERE
					col.class_auto isnull
					AND col.for_mobile
					AND col.klass IS NULL
					AND col.id<>46
					AND (for_services @> ? OR for_services IS NULL)
					AND (col.ids_corp @> (\'{\'||client.id_korp||\'}\')::int[] OR col.ids_corp IS NULL)
				ORDER BY col.sort', [$user_auth->login, Language::get_current(), '{'.$user_auth->service.'}']);

		$car_options = array_map(function($val)
			{
				$val['forbidden_options'] = json_decode($val['forbidden_options']);
				return $val;
			}
		, $car_options);

		return parent::success($car_options);
	}

	public function car_classes()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$destination = filter_var($_POST['destination']??null, FILTER_VALIDATE_BOOLEAN);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('
			SELECT
				col.id,
				COALESCE(lr.custom, lr.initial, col.name) AS name,
				\'car/\'||col.image_name::text AS img,
				col.model_description AS model_desc'.(($destination)?',
				col.destination_firm_id,
				col.destination_service_id':'').',
				to_json(col.forbidden_options) AS forbidden_options,
				to_json(col.included_options) AS included_options,
				(SELECT json_agg(t) FROM (SELECT
					colai.type,
					colai.name,
					colai.required,
					COALESCE(lr.custom, lr.initial, colai.order_button_text) AS order_button_text,
					COALESCE(lr2.custom, lr2.initial, colai.title) AS title,
					COALESCE(lr3.custom, lr3.initial, colai.descr) AS desc,
					colai.data
				FROM
					car_options_list_add_info colai
				LEFT JOIN
					lang_resource lr
				ON
					lr.key = colai.id||\'.order_button_text\'
					AND lr.lang = :lang
					AND lr.for_what = \'t.car_options_list_add_info\'
				LEFT JOIN
					lang_resource lr2
				ON
					lr2.key = colai.id||\'.title\'
					AND lr2.lang = :lang
					AND lr2.for_what = \'t.car_options_list_add_info\'
				LEFT JOIN
					lang_resource lr3
				ON
					lr3.key = colai.id||\'.descr\'
					AND lr3.lang = :lang
					AND lr3.for_what = \'t.car_options_list_add_info\'
				WHERE car_options_list_id = col.id) t) AS add_info
			FROM
				car_options_list col
			LEFT JOIN
				lang_resource lr
			ON
				lr.key = \'option_\'||col.id||\'.name\'
				AND lr.lang = :lang
				AND for_what = \'t.car_options_list\'
			WHERE
				col.class_auto
				AND (for_services @> :services or for_services is null)'.((!$destination)?'
				AND (col.destination_firm_id IS NULL OR col.destination_service_id IS NULL)':'').'
				AND col.id NOT IN (SELECT id FROM unnest((SELECT disabled_options FROM korp k, client c WHERE c.phone_number = :phone AND c.id_korp = k.id)) AS id)
			ORDER BY col.sort
		', ['lang' => Language::get_current(), 'services' => '{'.$user_auth->service.'}', 'phone' => $user_auth->login]);

		$res = array_map(function($val) use ($destination)
		{
			$val['name'] = mb_strtoupper(trim(str_replace('класс', '', $val['name'])), 'UTF-8');

			if ($destination && (empty($val['destination_firm_id']) || empty($val['destination_service_id'])))
			{
				unset($val['destination_firm_id']);
				unset($val['destination_service_id']);
			}
			if (empty($val['add_info']))
			{
				unset($val['add_info']);
			} else {
				$val['add_info'] = json_decode($val['add_info']);
			}

			$val['forbidden_options'] = json_decode($val['forbidden_options']);
			$val['included_options'] = json_decode($val['included_options']);
			return $val;
		}, $res);

		return parent::success($res);
	}

	public function bonuses()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT * FROM balans_client_6(?, ?)', [$user_auth->service, $user_auth->login]);

		return parent::success($res);
	}

	public function bonus_history()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = json_decode($dbfirm->GetRow('SELECT json_agg(x) AS balans_client_report FROM (SELECT dt, com, dvig FROM balans_client_report4(?, ?) ORDER BY dt DESC, balance DESC) x', [$user_auth->login, $user_auth->service])['balans_client_report']);

		$current = $dbfirm->GetRow('SELECT bonuses FROM balans_client_6(?, ?)', [$user_auth->service, $user_auth->login])['bonuses'];

		return parent::success(['current' => $current, 'history' => $res]);
	}

	public function current()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('
			SELECT get_info_order_mobile5.*
				FROM orders o, get_info_order_mobile5(o.id)
				WHERE o.end_ IS NULL AND o.phone_number=? AND o.operator_press_ok
			ORDER BY o.date_create DESC
		', $user_auth->login);

		//For decode addresses (json string) to array
		$res = array_map(function($val)
			{
				$val['addresses'] = json_decode($val['addresses']);
				$val['options'] = json_decode($val['options']);

				//Temporary solution remove after first december [START]
				$val['addresses'] = array_map(function($val)
				{
					if ($val->org_id > 0)
					{
						$val->org_city = $val->city;
						$val->org_street = $val->street;
						$val->org_number = $val->number;
					}
					return $val;
				}, $val['addresses']);
				//Temporary solution remove after first december [END]

				return $val;
			}, $res);

		return parent::success($res);
	}

	public function taximeter()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		if (empty($order_id))
			return parent::errCli('Post params order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$check = $dbfirm->GetRow('SELECT end_ AS val FROM orders WHERE id = ?', $order_id)['val'];

		if ($check == true)
			return parent::errCli(Language::data('global')['action_prohibited']);

		$count_point = (int)$dbfirm->GetRow('SELECT count(1) AS val FROM tochki WHERE id_order = ? AND end_ IS NULL', $order_id)['val'];

		//Add taximeter point and remove all uncomplited points except last (taximeter)
		if ($count_point >= 1)
		{
			$dbfirm->beginTransaction();

			$dbfirm->Execute('SELECT add_tochka(null, ?, null, \'таксометр\', now() + interval \'5 sec\', null, 0, null, null, null, null), orders_ok2(?)', [$order_id, $order_id]);

			$dbfirm->Execute('
				SELECT id, del_tochka(id) AS res FROM tochki WHERE id_order = ? AND tochki.del_ ISNULL AND (tochki.id_adres > 0 OR tochki.id_org > 0 OR tochki.id_all > 0) AND tochki.pervaya IS NULL AND poslednyaya IS NULL AND end_ IS NULL
			', $order_id);

			// $res = $dbfirm->Execute('SELECT orders_ok2(?)', $order_id); //Do not use, this close order!
			$res = (false !== $dbfirm->Execute('UPDATE orders SET operator_press_ok = TRUE, pereschitat = TRUE WHERE id = ?', $order_id));

			if (!$dbfirm->commit())
				return parent::errSer(Language::data('global')['unknown_error'].' #OG6TOC1');

			return parent::success(true);
		} else {
			return parent::errCli(Language::data('global')['action_prohibited'].' #OG6TOC2');
		}
	}

	public function problem_list()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			op.id,
			COALESCE(lr.custom, lr.initial, op.name) AS name,
			COALESCE(lr2.custom, lr2.initial, op.description) AS description,
			op.parent_id,
			op.category
		FROM
			order_problems op
		LEFT JOIN
			lang_resource lr
		ON lr.key = op.id||\'.name\' AND lr.lang = :lang AND lr.for_what = \'t.order_problems\'
		LEFT JOIN
			lang_resource lr2
		ON lr2.key = op.id||\'.desc\' AND lr2.lang = :lang AND lr2.for_what = \'t.order_problems\'
		ORDER BY 1', ['lang' => Language::get_current()]);

		if (count($res) == 0)
			return parent::errSer('Nothing to show', -5);

		return parent::success($res);
	}

	public function route()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'];

		if (empty($order_id))
			return parent::errCli('Post parameter order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//TODO: check the customer access to this order

		$route = CalcCreate::get_route($dbfirm, $order_id)['points'];

		if (count($route) == 0)
			return parent::errSer('Route of order is undefined', -5);

		return parent::success($route);
	}

	public function entrance()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$entrance = Language::data('entrance')['main'];

		return parent::success($entrance);
	}

	public function new_driver()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'] ?? null;
		if (empty($order_id))
			return parent::errCli('Post params order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$allow_count = (int)$dbfirm->GetRow('SELECT get_opt_s(\'count_iskl_bort_mobile\') AS val')['val'];

		if ($allow_count == 0)
			return parent::errSer('Method not allowed by settings firm');

		$count = (int)$dbfirm->GetRow('SELECT coalesce(count_iskl_bort_mobile, 0) AS val FROM orders WHERE id = ?', $order_id)['val'];

		if ($count < $allow_count)
		{
			//TODO: check the customer access to this order

			$dbfirm->Execute('SELECT iskl_bort(?, \'API\')', $order_id);
			$dbfirm->Execute('UPDATE orders SET operator_press_ok = true, count_iskl_bort_mobile=coalesce(count_iskl_bort_mobile, 0)+1 WHERE id = ?', $order_id);

			return parent::success($allow_count-$count-1);
		} else {
			return parent::errCli('You have reached limit of finding new driver on this order');
		}
	}

	public function rating_reason()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			stars,
			COALESCE(lr.custom, lr.initial) AS val
		FROM
			rating_reason rr
		LEFT JOIN
			lang_resource lr
		ON lr.key::integer = rr.id AND for_what = \'t.rating_reason\' AND lr.lang = ?
		ORDER BY rr.sort', Language::get_current());

		if (count($res) == 0)
			return parent::errSer('Nothing to show', -5);

		$ans = [];
		foreach ($res as $row) {
			$ans[$row['stars']][] = $row['val'];
		}

		return parent::success($ans);
	}
}