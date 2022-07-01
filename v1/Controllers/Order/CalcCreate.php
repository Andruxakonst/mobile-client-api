<?php

namespace Uptaxi\Controllers\Order;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class CalcCreate extends MainController
{
	public function Main($type = 'calc')
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$json = $_POST['json'];
		$data = @json_decode($json);

		if ($data === null && json_last_error() !== JSON_ERROR_NONE)
			return parent::errCli('Post parameter json is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		//TODO: check phone number or adm_

		$check = (int)$dbfirm->GetRow('SELECT fn_check_calc_create(?) AS val', $user_auth->login)['val'];
		if ($check > 0)
			return parent::errCli(str_replace('$1', ceil($check / 1000), Language::data('order')['limit_exceeded']));
		if ($type == 'calc')
		{
			return self::calc_price($dbfirm, $user_auth, $json);
		} else {
			return self::create($dbfirm, $user_auth, $json);
		}
	}

	public function calc_price($dbfirm, $user_auth, $json)
	{
		$json_decoded = json_decode($json);

		//For hard set to calc price
		// $json_decoded->operPressOk = false;
		// $json = json_encode($json_decoded, JSON_UNESCAPED_UNICODE);

		$order = $dbfirm->GetRow('SELECT id_order_ AS id, message_ AS text, CASE WHEN get_opt_s(\'system_length\')=\'1\' THEN \'mi\' ELSE \'km\' END AS system_length FROM fn_add_order_mobile(?)', $json);

		if (!$order)
			return parent::errSer(Language::data('global')['unknown_error'].' #DB_QUERY_ERR');

		if ($order['text'] != 'order_created') {
			if ($order['text'] == 'you_blocked')
			{
				$reason = $dbfirm->GetRow('SELECT block_prichina AS reason FROM client WHERE phone_number = ?', $user_auth->login)['reason'];
				// $reason = Language::data('block_reason')[$reason] ?: Language::data('global')['unknown_error'].' #OC2LNF1YAB';
				return parent::errCli($reason, -10);
			}

			if ($order['text'] == 'not_enough_funds')
			{
				$order['text'] = Language::data('order')[$order['text']] ?: Language::data('global')['unknown_error'].' #OC2LNF';
				return parent::errCli($order['text'], -4);
			}

			$order['text'] = Language::data('order')[$order['text']] ?: Language::data('global')['unknown_error'].' #OC2LNF';
			return parent::errCli($order['text']);
		}

		if (isset($order['id']) and ($order['id'] !=''))
		{
			$counter = 0;

			$length_type = $order['system_length'];

			while ($counter < 20)
			{
				$data = $dbfirm->GetRow('SELECT (calc_price_itogo_full2(id)+coalesce(price_podachi,0)) AS price, price_by_classes, pereschitat, d_minimalka, length_order::numeric, coalesce(orders.dop_spis_za_zonu_s_klienta,0) AS dop_spis_za_zonu_s_klienta FROM orders WHERE id = ?', $order['id']);
				$price = $data['price'];
				$price_by_classes = json_decode($data['price_by_classes']);
				$per_zones = $data['dop_spis_za_zonu_s_klienta'];
				$calc_run = $data['pereschitat'];
				$demand = $data['d_minimalka'];
				$length = number_format($data['length_order'] / (($length_type == 'km') ? 1000 : 1609.344000614692), 1, '.', '');

				if ($calc_run == null)// && count($json_decoded->points) > 1)
				{
					$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);
					/*$res = $dbfirm->GetRow('SELECT
							price - CASE WHEN orders.avto_skidka THEN 0 ELSE coalesce(bonus,0) END+ coalesce(nakrutka_ivr,0) + coalesce(d_minimalka,0) + CASE WHEN orders.dt_pre_order IS NOT NULL THEN coalesce(tarifi.nakrutka_za_predv, get_opt_i(\'za_predv\')) ELSE 0 END AS price,
							CASE
								WHEN orders.fix_price IS NOT NULL OR
									 orders.price_route IS NOT NULL OR
									 orders.price_for_gibrid IS NOT NULL THEN true
								ELSE false
							END AS fix_price
						FROM orders, tarifi WHERE orders.id=? AND orders.klass=tarifi.id', $order['id']);*/
					$res = $dbfirm->GetRow('SELECT
							CASE
								WHEN orders.fix_price IS NOT NULL OR
									 orders.price_route IS NOT NULL OR
									 orders.price_for_gibrid IS NOT NULL THEN true
								ELSE false
							END AS fix_price
						FROM orders WHERE orders.id=?', $order['id']);
					//Route exist if we calculated price!!!
					$route = self::get_route($dbfirm, $order['id']);

					//Now price from calc_price_itogo_full2 early from $res['price']
					return parent::success(['length' => $length, 'length_type' => $length_type, 'price' => $price, 'price_by_classes' => $price_by_classes, 'demand' => $demand, 'fix_price'=> $res['fix_price'], 'route' => $route]);
				}/* else if (count($json_decoded->points) == 1) {
					$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);
					/*$res = $dbfirm->GetRow('SELECT
							price - CASE WHEN orders.avto_skidka THEN 0 ELSE coalesce(orders.bonus,0) END+ coalesce(orders.nakrutka_ivr,0) + coalesce(orders.d_minimalka,0) + coalesce(orders.dop_spis_za_zonu_s_klienta,0) + CASE WHEN orders.dt_pre_order IS NOT NULL THEN coalesce(tarifi.nakrutka_za_predv, get_opt_i(\'za_predv\')) ELSE 0 END AS price
						FROM orders, tarifi WHERE orders.id=? AND orders.klass=tarifi.id', $order['id']);*./

					//Now price from calc_price_itogo_full2 early from $res['price']
					return parent::success(['length' => $length, 'length_type' => $length_type, 'price' => ($price+$per_zones), 'price_by_classes' => $price_by_classes, 'demand' => $demand, 'fix_price' => false, 'route' => ['points' => []]]);
				}*/
				$counter++;
				usleep(700*1000);
			}

			$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);
			//Route exist if we calculated price!!! [Here price not calculating]
			return parent::success(['length' => $length, 'length_type' => $length_type, 'price' => $price, 'price_by_classes' => $price_by_classes, 'demand' => $demand, 'fix_price' => false, 'order_id' => $order['id']]);
		}
	}

	public function create($dbfirm, $user_auth, $json)
	{
		//For hard set to calc price
		// $json_decoded = json_decode($json);
		// $json_decoded->operPressOk = true;
		// $json = json_encode($json_decoded, JSON_UNESCAPED_UNICODE);

		$json_decoded = json_decode($json);

		$check_card = self::check_card($dbfirm, $user_auth, @$json_decoded->selected_payment_method_type, @$json_decoded->selected_payment_card_token);

		if (!$check_card)
			return parent::errCli(Language::data('order')['check_card']);

		$order = $dbfirm->GetRow('SELECT id_order_ AS id, message_ AS text FROM fn_add_order_mobile(?)', $json);

		if ($order['text'] != 'order_created') {
			if ($order['text'] == 'you_blocked')
			{
				$reason = $dbfirm->GetRow('SELECT block_prichina AS reason FROM client WHERE phone_number = ?', $user_auth->login)['reason'];
				// $reason = Language::data('block_reason')[$reason] ?: Language::data('global')['unknown_error'].' #OC2LNF1YAB';
				return parent::errCli($reason, -10);
			}
			if ($order['text'] == 'disallow_online_order')
			{
				return parent::errCli(Language::data('order')['disallow_online_order']);
			}

			$order['text'] = Language::data('order')[$order['text']] ?: Language::data('global')['unknown_error'].' #OC2LNF';
			return parent::errCli($order['text']);
		}

		return parent::success($order);
	}

	public function get_route($dbfirm, $order_id)
	{
		$route = $dbfirm->GetRow('SELECT
			CASE WHEN (
				SELECT p_p_path FROM orders WHERE id = ?
			) notnull THEN (
			SELECT \'[\'||replace(replace(p_p_path, \'"(\', \'[\'), \')"\', \']\')||\']\' AS points FROM orders WHERE id = ?
			) ELSE 
			\'[\'||coalesce(array_to_string((array_agg((SELECT \'[\'||centr[0]||\',\'||centr[1])||\']\')),\',\'),\'\')||\']\'
			END AS points FROM (
				SELECT graf_small.k AS Id FROM (SELECT unnest((SELECT int_path FROM orders WHERE id = ?)) AS id) AS T1, graf_small WHERE graf_small.id = T1.id) AS T2, userslineymap WHERE userslineymap.id_line=T2.Id;', [$order_id, $order_id, $order_id]);

		if (isset($route['points']) && !empty($route['points']))
		{
			//Replace zones border
			$route['points'] = str_replace(';', ',', $route['points']);

			$route['points'] = json_decode($route['points']);

			//For revert x and y
			$rep_route = [];
			foreach ($route['points'] as $val) {
				$rep_route[] = [$val[1], $val[0]];
			}

			$route['points'] = $rep_route;
		}

		return $route;
	}

	public function check_card($dbfirm, $user_auth, $payment_system, $card_token)
	{
		if ($payment_system == 'ckassa')
		{
			if (substr($card_token, 0, 5) === 'gpay:' || substr($card_token, 0, 5) === 'apay:')
				return true;

			$url = 'http://kvs.uptaxi.ru/pay/ckassa/actions.php?action=get_cards_only';

			$firm_id = (int)$dbfirm->GetRow('SELECT get_opt_s(\'id_firm\') AS val')['val'];

			$params = [
				'firm' => $firm_id,
				'phone' => urlencode($user_auth->login),
				'password' => md5($dbfirm->GetRow('SELECT pass FROM client WHERE phone_number = ? LIMIT 1', $user_auth->login)['pass'])
			];

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

			$get_cards = curl_exec($ch);

			curl_close ($ch);

			$ans = json_decode($get_cards);

			if ($ans->error == 0)
			{
				foreach ($ans->cards as $val) {
					if ($val->cardToken == $card_token)
						return true;
				}

				return false;
			} else {
				return false;
			}
		}

		return true;
	}

	// public function _2gis_price()
	// {
	// 	$start_latitude = (float)@$_GET['start_latitude']?:null;
	// 	$start_longitude = (float)@$_GET['start_longitude']?:null;
	// 	$end_latitude = (float)@$_GET['end_latitude']?:null;
	// 	$end_longitude = (float)@$_GET['end_longitude']?:null;

	// 	if (empty($start_latitude) ||
	// 		empty($start_longitude) ||
	// 		empty($end_latitude) ||
	// 		empty($end_longitude))
	// 		return parent::errCli('Get params start_latitude, start_longitude, end_latitude or end_longitude is empty or not valid', -4);

	// 	$dbfirm = new \DBfirm();
	// 	if (!$dbfirm->checkConnection())
	// 		return parent::errSer('Error connecting to the DB', -3);

	// 	$data = $dbfirm->GetRow('SELECT get_opt_s(\'name_firm\') AS firm_name, get_opt_s(\'currency\') AS currency, get_opt_s(\'2gis_api_settings\') AS "2gis_api_settings"');

	// 	$firm_name = $data['firm_name'];
	// 	$currency = $data['currency'];
	// 	$_2gis_api_settings = @json_decode($data['2gis_api_settings']);

	// 	if (empty($firm_name))
	// 		return parent::errSer('Firm name is empty or not valid');

	// 	if (empty($currency))
	// 		return parent::errSer('Currency is empty or not valid');

	// 	if ($_2gis_api_settings === null && json_last_error() !== JSON_ERROR_NONE)
	// 		return parent::errSer('2gis api settings is empty or not valid');

	// 	$service_id = $_2gis_api_settings->service;

	// 	$car_classes = $_2gis_api_settings->car_classes;

	// 	$res = ['prices'=>[]];
	// 	// $res['start'] = date("Y-m-d H:i:s.").gettimeofday()["usec"];
	// 	foreach ($car_classes as $val) {
	// 		$service = (object)[];
	// 		$low_price = null;
	// 		$high_price = null;

	// 		$class_name = $dbfirm->GetRow('SELECT
	// 			COALESCE(lr.custom, lr.initial, col.name) AS name
	// 		FROM car_options_list col
	// 		LEFT JOIN lang_resource lr ON lr.key = \'option_\'||col.id||\'.name\' AND lr.lang = ? AND for_what = \'t.car_options_list\'
	// 		WHERE col.id = ?', [Language::get_current(), $val])['name'];

	// 		$class_name = trim(str_replace('класс', '', $class_name));

	// 		$json = '{"selected_payment_card_token":"null", "selected_payment_method_type":"", "cashless":false, "phone":"2Gis", "phone2":"", "comment":"", "entry":"", "points":[{"street":"'.$start_latitude.', '.$start_longitude.'", "textpoint":"'.$start_latitude.', '.$start_longitude.'"},{"street":"'.$end_latitude.', '.$end_longitude.'","textpoint":"'.$end_latitude.', '.$end_longitude.'"}], "options":['.$val.'], "date_pre":"", "who":"API", "service":'.$service_id.', "bonus":0, "operPressOk":false, "add_price":"0", "fix_price":"", "locale":"ru"}';

	// 		$json_decoded = json_decode($json);

	// 		$order = $dbfirm->GetRow('SELECT id_order_ AS id, message_ AS text FROM fn_add_order_mobile(?)', $json);

	// 		if ($order['text'] != 'order_created')
	// 			return parent::errSer($order['text']);

	// 		if (isset($order['id']) and ($order['id'] !=''))
	// 		{
	// 			$counter = 0;

	// 			while ($counter < 20)
	// 			{
	// 				$data = $dbfirm->GetRow('SELECT calc_price_itogo_full2(id) AS price, pereschitat FROM orders WHERE id = ?', $order['id']);
	// 				$price = $data['price'];
	// 				$calc_run = $data['pereschitat'];

	// 				if ($low_price == null)
	// 					$low_price = $price;

	// 				if ($calc_run == null && count($json_decoded->points) > 1)
	// 				{
	// 					$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);

	// 					$high_price = $price;
	// 					break;
	// 				} else if (count($json_decoded->points) == 1) {
	// 					$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);

	// 					$high_price = $price;
	// 					break;
	// 				}
	// 				$counter++;
	// 				usleep(700*1000);
	// 			}

	// 			//Here price not calculating
	// 			if ($high_price == null)
	// 			{
	// 				$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order['id'], Language::data('order')['occwm']]);
	// 				$high_price = $price;
	// 			}
	// 		}

	// 		$service->display_name = $firm_name.' '.$class_name;
	// 		$service->product_id = $order['id'];
	// 		$service->high_price = $high_price;
	// 		$service->low_price = $low_price;
	// 		$service->currency_code = $currency;
	// 		// $service->date = date("Y-m-d H:i:s.").gettimeofday()["usec"];

	// 		array_push($res['prices'], $service);
	// 	}

	// 	// $res['end'] = date("Y-m-d H:i:s.").gettimeofday()["usec"];

	// 	return parent::customAns($res);
	// }

	public function get_time_to_driver()
	{
		$order_id = (int)@$_GET['order_id']?:null;

		if (empty($order_id))
			return parent::errCli('Get params order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm();
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$order_data = $dbfirm->GetRow('SELECT
			o.id_bort,
			d.geo_p[1] AS driver_x,
			d.geo_p[0] AS driver_y,
			t.p[1] AS order_x,
			t.p[0] AS order_y
		FROM orders o
		JOIN bort b
		ON b.id = o.id_bort
		JOIN driver d
		ON d.id = b.id_driver
		JOIN tochki t
		ON t.id_order = o.id AND pervaya
		WHERE o.id = ?', $order_id);

		if (!$order_data['id_bort'])
			return parent::plainText('-1');// return parent::errCli('No any driver on order',-4);

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'http://localhost:9100/?lon1='.$order_data['driver_y'].'&lat1='.$order_data['driver_x'].'&lon2='.$order_data['order_y'].'&lat2='.$order_data['order_x'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
		));

		$response = curl_exec($curl);

		curl_close($curl);
		$data = json_decode($response);
		$distance_m = $response->length_order;

		$estimate = ceil(($distance_m/5.55)/60);

		return parent::plainText($estimate);
	}

	public function _2gis_time()
	{
		$coord_x = (float)@$_GET['start_latitude']?:null;
		$coord_y = (float)@$_GET['start_longitude']?:null;

		if (empty($coord_x) ||
			empty($coord_y))
			return parent::errCli('Get params start_latitude or start_longitude is empty or not valid', -4);

		$dbfirm = new \DBfirm();
			if (!$dbfirm->checkConnection())
				return parent::errSer('Error connecting to the DB', -3);

		$memcache = new \LocalMemcached();

		$data = $memcache->get('client_mobile.2gis_price');

		if ($data)
		{
			$firm_name = $data['firm_name'];
			$_2gis_api_settings = @json_decode($data['2gis_api_settings']);
		} else {
			$data = $dbfirm->GetRow('SELECT get_opt_s(\'name_firm\') AS firm_name, get_opt_s(\'2gis_api_settings\') AS "2gis_api_settings"');

			$firm_name = $data['firm_name'];
			$_2gis_api_settings = @json_decode($data['2gis_api_settings']);

			if (empty($firm_name))
				return parent::errSer('Firm name is empty or not valid');

			if ($_2gis_api_settings === null && json_last_error() !== JSON_ERROR_NONE)
				return parent::errSer('2gis api settings is empty or not valid');
		}

		$service_id = $_2gis_api_settings->service;
		$car_classes = $_2gis_api_settings->car_classes;

		$res = ['times'=>[]];

		foreach ($car_classes as $val) {
			$time = (object)[];

			$class_id = $val->class_id;
			$class_name = $val->class_name;

			$sql ='SELECT driver.id, (fn_get_distance(?, ?, driver.x, driver.y)).* FROM (SELECT
				foo.id_bort AS id,
				foo.geo_p[1] AS x,
				foo.geo_p[0] AS y
			FROM
			(SELECT foo.id_bort, foo.geo_p::point
				FROM
				(
					SELECT foo.* FROM (
					SELECT DISTINCT bort.id id_bort, driver.geo_p::varchar,
					array_agg(id_options) aa
					FROM bort, driver, car_options
					WHERE
					bort.id_driver=driver.id
					AND driver.online 
					AND driver.last_ping_time > (now() - \'00:01:00\'::interval)
					AND driver.last_gps_time > (now() - \'00:05:00\'::interval)
					AND bort.id_car=car_options.id_car
				GROUP BY 1,2
				) foo WHERE aa @> array[?]::integer[]
				) foo
				UNION ALL
				SELECT
					id_bort AS id,
					p
				FROM
					birga.bort
					WHERE array[?]::integer[] <@ string_to_array(birga.bort.car_options, \',\')::integer[]
			)foo
			ORDER BY distance(foo.geo_p::point, (?)::point)
			LIMIT 1) driver';

			$get_driver = $dbfirm->GetRow($sql, [$coord_x, $coord_y, $class_id, $class_id, $coord_y.','.$coord_x]);

			if (empty($get_driver))
				$get_driver = ['id' => 0, 'distance_m' => 8325];

			$estimate = ceil($get_driver['distance_m']/5.55);

			$time->estimate = $estimate;
			$time->display_name = $firm_name.' '.$class_name;
			$time->product_id = $get_driver['id'];

			array_push($res['times'], $time);
		}

		return parent::customAns($res);
	}

	public function _2gis_price()
	{
		$start_latitude = (float)@$_GET['start_latitude']?:null;
		$start_longitude = (float)@$_GET['start_longitude']?:null;
		$end_latitude = (float)@$_GET['end_latitude']?:null;
		$end_longitude = (float)@$_GET['end_longitude']?:null;

		if (empty($start_latitude) ||
			empty($start_longitude) ||
			empty($end_latitude) ||
			empty($end_longitude))
			return parent::errCli('Get params start_latitude, start_longitude, end_latitude or end_longitude is empty or not valid', -4);

		$memcache = new \LocalMemcached();

		$data = $memcache->get('client_mobile.2gis_price');

		if ($data)
		{
			$firm_name = $data['firm_name'];
			$currency = $data['currency'];
			$_2gis_api_settings = @json_decode($data['2gis_api_settings']);
			$system_length = ($data['system_length'] == 'km') ? 1000 : 1609.344000614692;
			$round_to = $data['round_to'];
		} else {
			$dbfirm = new \DBfirm();
			if (!$dbfirm->checkConnection())
				return parent::errSer('Error connecting to the DB', -3);

			$data = $dbfirm->GetRow('SELECT get_opt_s(\'name_firm\') AS firm_name, get_opt_s(\'currency\') AS currency, get_opt_s(\'2gis_api_settings\') AS "2gis_api_settings", CASE WHEN get_opt_s(\'system_length\')=\'1\' THEN \'mi\' ELSE \'km\' END AS system_length, get_opt_s(\'round_to\') AS round_to');

			$firm_name = $data['firm_name'];
			$currency = $data['currency'];
			$_2gis_api_settings = @json_decode($data['2gis_api_settings']);
			$system_length = ($data['system_length'] == 'km') ? 1000 : 1609.344000614692;
			$round_to = $data['round_to'];

			if (empty($firm_name))
				return parent::errSer('Firm name is empty or not valid');

			if (empty($currency))
				return parent::errSer('Currency is empty or not valid');

			if ($_2gis_api_settings === null && json_last_error() !== JSON_ERROR_NONE)
				return parent::errSer('2gis api settings is empty or not valid');

			$memcache->set(
				'client_mobile.2gis_price',
				[
					'firm_name' => $data['firm_name'],
					'currency' => $data['currency'],
					'2gis_api_settings' => $data['2gis_api_settings'],
					'system_length' => $data['system_length'],
					'round_to' => $data['round_to']
				],
				60
			);
		}

		$service_id = $_2gis_api_settings->service;

		$car_classes = $_2gis_api_settings->car_classes;

		$distance = self::distance($start_latitude, $start_longitude, $end_latitude, $end_longitude);

		$res = ['prices'=>[]];
		// $res['start'] = date("Y-m-d H:i:s.").gettimeofday()["usec"];
		foreach ($car_classes as $val) {
			$service = (object)[];

			$order_distance = $distance - $val->distance_free;

			$high_price = round($order_distance/$system_length*$val->price_per_lenght+$val->low_price, $round_to);

			if ($high_price < $val->low_price)
				$high_price = $val->low_price;

			$service->display_name = $firm_name.' '.$val->class_name;
			$service->product_id = $service_id;
			$service->high_price = $high_price;
			$service->low_price = $val->low_price;
			$service->currency_code = $currency;
			// $service->order_distance = $order_distance;
			// $service->date = date("Y-m-d H:i:s.").gettimeofday()["usec"];

			array_push($res['prices'], $service);
		}

		// $res['end'] = date("Y-m-d H:i:s.").gettimeofday()["usec"];

		return parent::customAns($res);
	}

	public static function distance($lat1, $lon1, $lat2, $lon2, $earthRadius = 6371000)
	{
		$latFrom = deg2rad($lat1);
		$lonFrom = deg2rad($lon1);
		$latTo = deg2rad($lat2);
		$lonTo = deg2rad($lon2);

		$lonDelta = $lonTo - $lonFrom;
		$a = pow(cos($latTo) * sin($lonDelta), 2) +
			pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
		$b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

		$angle = atan2(sqrt($a), $b);
		return $angle * $earthRadius;
	}
}