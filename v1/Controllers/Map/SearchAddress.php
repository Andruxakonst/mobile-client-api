<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class SearchAddress extends MainController
{
	public function Street()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$data = $_POST['data'] ?? null;

		if (empty($data))
			return parent::errCli('Searching data is empty', -4);

		if (mb_strlen($data) < 4)
			return parent::errCli('Searching data must have 4 or more symbols', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		if (strtolower($user_auth->country_iso) == 'us')
		{
			$street = array();
			$error = '';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://maps.googleapis.com/maps/api/place/queryautocomplete/json?key=AIzaSyB80cTbS4fSt61fxjCKqE6rg6pjnQ7nIjE&language='.Language::get_current().'&input='.urlencode($data));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$out = curl_exec($ch);
			if(curl_exec($ch) === false){
				echo 'curl_error: ' . curl_error($ch);
			} else {
				$google_addr = json_decode($out);
				if($google_addr->status == 'OK'){
					for($i=0; $i<count($google_addr->predictions); ++$i){
						if(isset($google_addr->predictions[$i]->place_id)){
							$street[$i] = array();
							$street[$i]['description'] = $google_addr->predictions[$i]->description;
							$street[$i]['placeid'] = $google_addr->predictions[$i]->place_id;
						}
					}
				} else if($google_addr->status == 'ZERO_RESULTS') {
					$error = 'zero results';
				} else if($google_addr->status == 'OVER_QUERY_LIMIT') {
					$error = 'over query limit';
				} else if($google_addr->status == 'REQUEST_DENIED') {
					$error = 'request denied';
				} else if($google_addr->status == 'INVALID_REQUEST') {
					$error = 'invalid request';
				}
			}
			curl_close($ch);

			if (!empty($error))
				return parent::errSer($error, -1);

			return parent::success($street);
		} else {
			$street = $dbfirm->GetAll('SELECT id_org_, t_ as description, dbo.streets.name, dbo.streets.gorod AS city FROM ts_find(?) LEFT JOIN dbo.streets ON id_streets_=streets.id where id_crossroad_ isnull LIMIT 10', $data);
			return parent::success($street);
		}
	}

	public function House()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$city = $_POST['city'];
		$street = $_POST['street'];

		if (empty($city) || empty($street))
			return parent::errCli('City or street is empty', -4);

		if (strtolower($user_auth->country_iso) == 'us')
			return parent::errCli('House search not allowed', -7);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('
			SELECT id, dom,
				coalesce(dbo.adress.y, -1) as x,
				coalesce(dbo.adress.x, -1) as y
			FROM
				dbo.adress
			WHERE
				regionname=get_opt_s(\'default_city\') and city=? and street=?
				and dom is not null and dom<>\'\' and dom<>\' \' and dom<>\'.\' and dom<>\',\' and dom<>\'/\' and dom<>\'//\'
				and coalesce(del_, false)=false
			ORDER BY
				lpad(CAST((CAST(COALESCE(SUBSTRING(dom FROM \'^(\d+)$\'), SUBSTRING(dom FROM \'^(\d+)\'), \'10000\') AS BIGINT) * 1000000) + (CAST(COALESCE(SUBSTRING(regexp_replace(regexp_replace(dom, \'^(\d+)\', \'\'), \'^[^\d]+\', \'\') FROM \'^(\d+)\'), \'0\') AS BIGINT) * 1000) AS VARCHAR), 19, \'0\') || dom ASC;
			', [$city, $street]);

		return parent::success($res);
	}

	public function one_row()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$input = $_POST['input']??null;
		$streetid = $_POST['streetid']??null;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('
			SELECT * FROM public.searchaddress(?,\'\',?,?, 25, null) WHERE x_ IS NOT NULL OR y_ IS NOT NULL
			', [$input, $user_auth->login, $streetid]);

		// Invert x y from searchaddress function
		$res = array_map(function($val)
		{
			$x = $val['y_']??null;
			$y = $val['x_']??null;
			$val['x_'] = $x;
			$val['y_'] = $y;

			return $val;
		}, $res);

		return parent::success($res);
	}

	public function multi_one_row()
	{
		//Test
		// $dbfirm = new \DBfirm(18);
		// if (!$dbfirm->checkConnection())
		// 	return parent::errSer('Error connecting to the DB', -3);

		// $input = $_POST['input']??null;

		// $res = self::search_street_gy($dbfirm, 'Yandex', null, $input, 45.0377119619367, 38.9745577833341);
		// return parent::success($res);
		//End test

		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$city = $_POST['city']??null;
		$input = $_POST['input']??null;
		$streetid = $_POST['streetid']??null;

		if (empty($input))
			return parent::errCli('Post param input is empty', -4);

		$coord_x = (float)$_POST['x'] ?? null;
		$coord_y = (float)$_POST['y'] ?? null;

		if (empty($coord_x) || empty($coord_y))
			return parent::errCli('Post params x or y is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$get_opt_i = $dbfirm->GetRow('
			SELECT
				get_opt_i(\'use_only_base_adress\') AS use_only_base_adress,
				get_opt_i(\'hide_dom_while_not_enter_number\') AS hide_dom_while_not_enter_number,
				get_opt_i(\'use_translit_search_address\') AS use_translit_search_address');

		$use_only_base_adress = $get_opt_i['use_only_base_adress'];
		$hide_dom_while_not_enter_number = $get_opt_i['hide_dom_while_not_enter_number'];
		$use_translit_search_address = $get_opt_i['use_translit_search_address'];

		// $res = $dbfirm->GetAll('
		// 	SELECT
		// 		id_ AS id
		// 		,res_type_ AS res_type
		// 		,city_ AS city
		// 		,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',1) ELSE item_ END AS street
		// 		,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',2) ELSE \'\' END AS house
		// 		,item_ AS desc
		// 		,weight_ AS weight
		// 		,rate_ AS rate
		// 		,y_ AS x
		// 		,x_ AS y
		// 		,\'db\'::varchar AS from
		// 	FROM
		// 		public.searchaddress(?,\'\',?,?,25,null)
		// 	WHERE
		// 		((x_ IS NOT NULL AND y_ IS NOT NULL AND x_<>0 AND y_<>0) OR res_type_ = \'street\') AND res_type_ <> \'cross\' AND city_ IS NOT NULL
		// 	', [$input, $user_auth->login, $streetid]);

		// if ($hide_dom_while_not_enter_number == 1 && !$streetid)
		// {
		// 	$additional_streets = $dbfirm->GetAll('SELECT
		// 		id,
		// 		\'street\' AS res_type,
		// 		gorod AS city,
		// 		name AS street,
		// 		\'\' AS house,
		// 		name AS desc,
		// 		CASE WHEN get_opt_s(\'default_city\') = gorod THEN 1 ELSE 0 END AS weight,
		// 		(name <-> :input) sub_weight,
		// 		NULL AS rate,
		// 		0 AS x,
		// 		0 AS y,
		// 		\'db\' AS from
		// 	FROM
		// 		dbo.streets
		// 	WHERE
		// 		name ILIKE \'%\'||:input||\'%\'
		// 	ORDER BY weight DESC, sub_weight ASC
		// 	LIMIT 10', ['input' => $input]);
		// 	// $res = array_merge($additional_streets, $res);

		// 	if (count($additional_streets) !== 0)
		// 		return parent::success($additional_streets);
		// }

		if ($use_translit_search_address == 1)
		{
			$res = $dbfirm->GetAll('
				SELECT
					id,
					res_type,
					city,
					street,
					house,
					"desc",
					weight,
					rate,
					x,
					y,
					"from"
				FROM
				(WITH cte AS
					(SELECT
						id_ AS id
						,res_type_ AS res_type
						,city_ AS city
						,tr_city_ AS tr_city
						,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',1) ELSE item_ END AS street
						,CASE WHEN res_type_ = \'address\' THEN split_part(tr_item_,\', \',1) ELSE tr_item_ END AS tr_street
						,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',2) ELSE \'\' END AS house
						,CASE WHEN res_type_ = \'address\' THEN split_part(tr_item_,\', \',2) ELSE \'\' END AS tr_house
						,item_ AS desc
						,tr_item_ AS tr_desc
						,weight_ AS weight
						,rate_ AS rate
						,y_ AS x
						,x_ AS y
						,\'db\'::varchar AS from
					FROM
						public.searchaddress_translit(translit(:input),\'\',:login,:streetid,1000,null)
					WHERE
						((x_ IS NOT NULL AND y_ IS NOT NULL AND x_<>0 AND y_<>0) OR res_type_ = \'street\') AND res_type_ <> \'cross\' AND city_ IS NOT NULL)
					SELECT * FROM
					(
						(SELECT
							cte.id,
							cte.res_type,
							cte.city,
							cte.tr_city,
							cte.street,
							cte.tr_street,
							cte.house,
							cte.tr_house,
							cte.desc,
							cte.tr_desc,
							cte.weight/cte.rate::real as weight,
							cte.rate,
							cte.x,
							cte.y,
							cte.from
						FROM
							cte
						WHERE tr_city||\' \'||tr_desc ~~* all (array(select \'%\' || unnest(regexp_split_to_array(translit(:input), \' \')) || \'%\')) ORDER BY weight ASC NULLS LAST)
					UNION ALL
					(SELECT * FROM cte EXCEPT SELECT * FROM cte WHERE tr_city||\' \'||tr_desc ~~* all (array(select \'%\' || unnest(regexp_split_to_array(translit(:input), \' \')) || \'%\')) ORDER BY rate DESC NULLS LAST)
				) s LIMIT 25) a', [
					'input' => $input,
					'login' => $user_auth->login,
					'streetid' => $streetid
			]);
		} else {
			$res = $dbfirm->GetAll('WITH cte AS
				(SELECT
					id_ AS id
					,res_type_ AS res_type
					,city_ AS city
					,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',1) ELSE item_ END AS street
					,CASE WHEN res_type_ = \'address\' THEN split_part(item_,\', \',2) ELSE \'\' END AS house
					,item_ AS desc
					,weight_ AS weight
					,rate_ AS rate
					,y_ AS x
					,x_ AS y
					,\'db\'::varchar AS from
				FROM
					public.searchaddress(:input,\'\',:login,:streetid,1000,null)
				WHERE
					((x_ IS NOT NULL AND y_ IS NOT NULL AND x_<>0 AND y_<>0) OR res_type_ = \'street\') AND res_type_ <> \'cross\' AND city_ IS NOT NULL)
				SELECT * FROM
				(
					(SELECT
						cte.id,
						cte.res_type,
						cte.city,
						cte.street,
						cte.house,
						cte.desc,
						cte.weight/cte.rate::real as weight,
						cte.rate,
						cte.x,
						cte.y,
						cte.from
					FROM
						cte
					WHERE city||\' \'||"desc" ~~* all (array(select \'%\' || unnest(regexp_split_to_array((:input), \' \')) || \'%\')) ORDER BY weight ASC NULLS LAST)
				UNION ALL
				(SELECT * FROM cte EXCEPT SELECT * FROM cte WHERE city||\' \'||"desc" ~~* all (array(select \'%\' || unnest(regexp_split_to_array((:input), \' \')) || \'%\')) ORDER BY rate DESC NULLS LAST)
			) s LIMIT 25', [
				'input' => $input,
				'login' => $user_auth->login,
				'streetid' => $streetid
			]);
		}

		if ($hide_dom_while_not_enter_number == 1 && !$streetid)
		{
			$additional_streets = $dbfirm->GetAll('SELECT
				id,
				\'street\' AS res_type,
				gorod AS city,
				name AS street,
				\'\' AS house,
				name AS desc,
				CASE WHEN get_opt_s(\'default_city\') = gorod THEN 1 ELSE 0 END AS weight,
				(name <-> :input) sub_weight,
				NULL AS rate,
				0 AS x,
				0 AS y,
				\'db\' AS from
			FROM
				dbo.streets
			WHERE
				name ILIKE \'%\'||:input||\'%\'
			ORDER BY weight DESC, sub_weight ASC
			LIMIT 10', ['input' => $input]);
			$res = array_merge($additional_streets, $res);

			// if (count($additional_streets) !== 0)
			// 	return parent::success($additional_streets);
		}

		if ($use_only_base_adress == 1)
			return parent::success($res);

		$street = preg_replace('/^((?:\S+ +)+)\d+$/', '\1', $input);
		$house = preg_replace('/^(?:\S+ +)+(\d+)$/', '\1', $input);

		if ($streetid !== null || ($street == $house && count($res) >= 20))
			return parent::success($res);

		if(strtolower($user_auth->country_iso) != 'us')
		{
			$ans = [];
			$resinarr = [];

			$resinarr['ya'] = self::search_street_gy($dbfirm, 'Yandex', $city, $input, $coord_x, $coord_y);
			$resinarr['go'] = self::search_street_gy($dbfirm, 'Google', $city, $input, $coord_x, $coord_y);
			$resinarr['db'] = $res;
			$counts_res = [
				'ya' => count($resinarr['ya']),
				'go' => count($resinarr['go']),
				'db' => count($resinarr['db'])
			];

			$count_max_res = max($counts_res);

			for ($i=0; $i < $count_max_res; $i++) {
				if (isset($resinarr['db'][$i]) )
					$ans[] = $resinarr['db'][$i];
				if (isset($resinarr['go'][$i]))
					$ans[] = $resinarr['go'][$i];
				if (isset($resinarr['ya'][$i]))
					$ans[] = $resinarr['ya'][$i];
			}

			$ret_res = [];
			$key_array = [];

			for ($i=0; $i < count($ans); $i++) {
				if(empty($ans[$i]['desc']))
					continue;

				$desc = mb_strtolower($ans[$i]['desc']);
				$arrDesc = explode(' ', $desc);
				//Adding city to compare
				array_unshift($arrDesc, $ans[$i]['city']);
				$arrDesc = array_values(array_filter($arrDesc, function($val) {
					return (!in_array($val, ['', 'улица', 'ул', 'ул.'])) ? true : false;
				}));

				foreach ($key_array as $val) {
					$arrVal = explode(' ', $val);
					if (array_diff($arrVal, $arrDesc) === array_diff($arrDesc, $arrVal))
						continue(2);
				}

				$key_array[] = implode(' ', $arrDesc);
				$ret_res[] = $ans[$i];
			}

			return parent::success($ret_res);
		} else {
			//TODO: for U.S. later...
			return parent::success($res);
		}
	}

	public function search_street_gy($dbfirm, $type, $city, $input, $coord_x, $coord_y) {
		if ($type == 'Yandex')
		{
			$text = urlencode((!empty($city))?$city.', '.$input:$input);
			$obj = [];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_URL, 'http://suggest-maps.yandex.ru/suggest-geo?callback=show_suggestion&kind=street&lang='.Language::get_current().'&ll='.$coord_y.urlencode(',').$coord_x.'&spn=5&fullpath=1&v=9&search_type=all&part='.$text);
			$res_arr = self::jsonp_decode(curl_exec($ch), true)['results'];
			curl_close($ch);

			$ya_res = [];
			foreach ($res_arr as $ind => $key) {
				$arr_text = explode(', ', $key['text']);

				//For disable result with entrance
				if (count($key['tags']) !== 1)
					continue;

				if ($key['tags'][0] == 'house')
				{
					$city = '';
					$street = '';
					$house = '';
					$desc = '';

					switch (count($arr_text)) {
						case 4:
							$city = self::rpl_pre_long($arr_text[1]);
							$street = self::rpl_pre_long($arr_text[2]);
							$house = trim($arr_text[3]);
							$desc = $street.', '.$house;
							break;
						case 5:
							$city = (strpos($arr_text[2], 'микрорайон') !== false)?self::rpl_pre_long($arr_text[1]):self::rpl_pre_long($arr_text[2]);
							$street = self::rpl_pre_long($arr_text[3]);
							$house = trim($arr_text[4]);
							$desc = $street.', '.$house;
							break;
						case 6:
							$city = self::rpl_pre_long($arr_text[3]);
							$street = self::rpl_pre_long($arr_text[4]);
							$house = trim($arr_text[5]);
							$desc = $street.', '.$house;
							break;
						default: continue(2);
					}

					$ya_res[] = [
						'id' => null,
						'res_type' => 'address',
						'city' => $city,
						'street' => $street,
						'house' => $house,
						'desc' => $desc,
						'weight' => 1,
						'rate' => null,
						'x' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[1],
						'y' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[0],
						'from' => 'ya'
					];
				} else if ($key['tags'][0] == 'locality')
				{
					$city = '';
					$street = '';
					$house = '';
					$desc = '';

					switch (count($arr_text)) {
						case 2:
							$street = self::rpl_pre_long($arr_text[1]);
							break;
						case 4:
							$street = self::rpl_pre_long($arr_text[3]);
							break;
						default: continue(2);
					}

					$ya_res[] = [
						'id' => null,
						'res_type' => 'locality',
						'city' => null,
						'street' => $street,
						'house' => null,
						'desc' => $street,
						'weight' => 1,
						'rate' => null,
						'x' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[1],
						'y' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[0],
						'from' => 'ya'
					];
				}
			}

			return $ya_res;
		} else {
			return [];
			$ch = curl_init();
			$text = urlencode((!empty($city))?$city.', '.$input:$input);

			// $api_key = 'AIzaSyDsogh43357fGyY2E0fMiLqXIkJtPq0Y6E'; // old
			// $api_key = 'AIzaSyB80cTbS4fSt61fxjCKqE6rg6pjnQ7nIjE'; // new
			$api_key = $dbfirm->GetRow('SELECT get_opt_s(\'api_key_google\') as val')['val'];
			if (empty($api_key))
				$api_key = 'AIzaSyDsogh43357fGyY2E0fMiLqXIkJtPq0Y6E';
			$url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?input='.$text.'&location='.$coord_x.urlencode(',').$coord_y.'&radius=50000&types=geocode&language='.Language::get_current().'&key='.$api_key;

			$goo_res = [];

			if ($ch === false) {
				return 'Инициализация провалена';
			}

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

			$response = json_decode(curl_exec($ch), true);
			curl_close($ch);

			if ($response['status'] == 'OK') {
				return [];
				for ($i = 0; $i < count($response['predictions']); $i++) {
					if (array_search('route', $response['predictions'][$i]['types']) > -1)
					{
						// $goo_res[] = [
						// 	'city' => rplPreLong($response['predictions'][$i]['terms'][1]['value']),
						// 	'description' => rplPreLong($response['predictions'][$i]['terms'][0]['value']).' ('.rplPreLong($response['predictions'][$i]['terms'][1]['value']).')',
						// 	'name' => rplPreLong($response['predictions'][$i]['terms'][0]['value']),
						// 	'id_org_' => null,
						// 	'p' => 1
						// ];

						$city = '';
						$street = '';
						$house = '';
						$desc = '';

						$goo_res[] = [
							'id' => null,
							'res_type' => 'address',
							'city' => $city,
							'street' => $street,
							'house' => $house,
							'desc' => $desc,
							'weight' => 1,
							'rate' => null,
							'x' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[1],
							'y' => explode('%2C', preg_replace("/.*?ll=([^&]+)&.*/", "$1", $key['uri']))[0],
							'from' => 'go'
						];
					}
				}
			} else {
				return [];
			}
			return $goo_res;
		}
	}

	public function rpl_pre_long($text) {
		$text = preg_replace('/(^ул[.]?\s)|(\sул[.]?$)/', '', $text);
		$text = str_replace('улица', '', $text);
		$text = str_replace('микрорайон', 'мкр', $text);
		$text = str_replace('посёлок городского типа', 'пгт', $text);
		$text = str_replace('станица', 'ст-ца', $text);
		$text = str_replace('садовое товарищество', 'снт', $text);
		$text = str_replace('посёлок', 'пос', $text);
		$text = str_replace('городской округ', '', $text);
		return trim($text);
	}

	public function jsonp_decode($jsonp, $assoc = false)
	{
		if($jsonp !== '[' || $jsonp !== '{')
		{
			$jsonp = substr($jsonp, strpos($jsonp, '('));
		}
		return json_decode(trim($jsonp,'();'), $assoc);
	}

	public function get_city()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$coord_x = (float)$_POST['x'] ?? null;
		$coord_y = (float)$_POST['y'] ?? null;

		if (empty($coord_x) || empty($coord_y))
			return parent::errCli('Post params x or y is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT gorod AS city FROM get_adres_from_p(?)', $coord_y.', '.$coord_x)['city'];

		$res = (empty($res))?'':$res.', ';

		return parent::success($res);
	}
}