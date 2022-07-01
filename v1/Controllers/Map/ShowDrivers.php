<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class ShowDrivers extends MainController
{
	public function Main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$coord_x = (float)$_POST['x'] ?? null;
		$coord_y = (float)$_POST['y'] ?? null;

		$options = $_POST['options'] ?? null;

		if (empty($coord_x) || empty($coord_y))
			return parent::errCli('Post params x or y is empty', -4);

		if (!empty($options))
		{
			$data = (array)@json_decode($options);

			if ($data === null && json_last_error() !== JSON_ERROR_NONE)
				return parent::errCli('Post parameter options must be in json', -4);
		}

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$number_of_nearby_drivers = $dbfirm->GetRow('SELECT coalesce(get_opt_i(\'number_of_nearby_drivers\'), 0) AS val')['val'];

		if ($number_of_nearby_drivers === 0)
			return parent::success([['id' => 1, 'x' => '0.0', 'y' => '0.0', 'duration' => null, 'deg' => 0]]);

		$sql='SELECT
			foo.id_bort AS id,
			foo.geo_p[1] AS x,
			foo.geo_p[0] AS y,
			coalesce(foo.last_a, 0)::integer AS deg,
			foo.busy,
			(fn_get_distance(?, ?, foo.geo_p[1], foo.geo_p[0])).distance_m
		FROM
		(SELECT foo.id_bort, foo.geo_p::point, foo.last_a, foo.busy
			FROM
			(
				SELECT foo.* FROM (
				SELECT DISTINCT bort.id id_bort, driver.geo_p::varchar, driver.last_a,
				CASE
					WHEN bort.available AND rezerv_any_firm isnull AND driver.last_gps_time > (now() - \'00:03:00\'::interval) THEN false
				ELSE
					true
				END AS busy
				, array_agg(id_options) aa
				FROM bort, driver, car_options
				WHERE
				bort.id_driver=driver.id
				AND driver.online 
				AND driver.last_ping_time > (now() - \'00:01:00\'::interval)
				AND driver.last_gps_time > (now() - \'00:05:00\'::interval)
				AND bort.id_car=car_options.id_car
			GROUP BY 1,2,3,4
			) foo '.((!empty($options))?'WHERE aa @> array['.implode(',', array_fill(0, count($data), '?')).']::integer[]':'').'
			) foo
			UNION ALL
			SELECT
				id_bort AS id,
				p,
				last_a::integer AS deg,
				CASE WHEN rezerv isnull THEN false ELSE true END AS busy
			FROM
				birga.bort
				'.((!empty($options))?'WHERE array['.implode(',', array_fill(0, count($data), '?')).']::integer[] <@ string_to_array(birga.bort.car_options, \',\')::integer[]':'').'
		)foo
		WHERE CASE WHEN get_opt_s(\'show_only_free_drivers\') = \'1\' THEN foo.busy = FALSE ELSE TRUE END
		ORDER BY distance(foo.geo_p::point, (?)::point)
		LIMIT coalesce(get_opt_i(\'number_of_nearby_drivers\'), 0)';

		$params = [$coord_y.','.$coord_x];

		$params = (!empty($options))?array_merge($data, $data, $params):$params;

		//Add coords for fn_get_distance ONLY AFTER MERGE OTHER PARAMS!
		$params = array_merge([$coord_x, $coord_y], $params);

		$res = $dbfirm->GetAll($sql, $params);

		if (count($res) != 0)
		{
			foreach ($res as $key => $val) {
				// $res[$key]['duration'] = ($key == 0)?self::duration_time($dbfirm, [$coord_x, $coord_y], [$res[$key]['x'], $res[$key]['y']]):null;
				$res[$key]['duration'] = ($key == 0)?ceil($res[$key]['distance_m']/($user_auth->id_firm === 730?10:5.55)/60):null;
				unset($res[$key]['distance_m']);
				if ($key !== 0)
					unset($res[$key]['duration']);
			}
		}

		if (count($res) != 0 && $res[0]['duration'] > 60)
			return parent::success([]);

		return parent::success($res);
	}

	// public function duration_time($dbfirm, $p1, $p2)
	// {
	// 	$ch = curl_init();
	// 	$lang = Language::get_current();
	// 	$api_key = $dbfirm->GetRow('SELECT get_opt_s(\'api_key_openrouteservice\') as val')['val'];

	// 	if (empty($api_key))
	// 		return null;

	// 	$url = "https://api.openrouteservice.org/directions?api_key=$api_key&profile=driving-car&preference=recommended&geometry=false&instructions=false&coordinates=$p1[1],$p1[0]%7C$p2[1],$p2[0]";

	// 	if ($ch === false) {
	// 		return null;
	// 	}

	// 	curl_setopt($ch, CURLOPT_URL, $url);
	// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	// 	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	// 	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	// 	$response = json_decode(curl_exec($ch), true);
	// 	curl_close($ch);

	// 	if ($response['routes']) {
	// 		if ($response['routes'][0]['summary']['duration'] == 0)
	// 			return 1;

	// 		return ceil($response['routes'][0]['summary']['duration']/60);
	// 	} elseif (@$response['error']['code'] == 2010) { //If distance between points less than 350m
	// 		return 1;
	// 	} else {
	// 		return null;
	// 	}
	// }
}