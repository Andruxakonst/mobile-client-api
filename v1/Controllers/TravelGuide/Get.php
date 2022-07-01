<?php

namespace Uptaxi\Controllers\TravelGuide;

use Uptaxi\Controllers\MainController;

class Get extends MainController
{

	public function list()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$tag = $_POST['tag']??null;

		$sort_by_coords = true;
		$coord_x = (float)@$_POST['x'] ?? null;
		$coord_y = (float)@$_POST['y'] ?? null;
		if (!$coord_x || !$coord_y)
			$sort_by_coords = false;

		$args = [$user_auth->login, $user_auth->service, $user_auth->service];

		if ($sort_by_coords)
			array_unshift($args, $coord_x, $coord_y);

		if ($tag)
			array_push($args, $tag);

		$res = $dbfirm->GetAll('SELECT
			n.id,
			n.image_preview,
			n.title,
			n.short_text,
			to_json(n.tags) AS tags,
			n.prime,
			CASE WHEN array[c.id]::integer[] <@ n.id_clients::integer[] THEN true ELSE false END AS readed,
			'.(($sort_by_coords)?'(fn_get_distance(?, ?, n.p[0], n.p[1])).distance_m':'NULL AS distance_m').'
		FROM
			news n, client c
		WHERE
			razdel=\'travel_guide\'
			AND end_ IS NULL
			AND tags IS NOT NULL
			AND c.phone_number = ?
			AND (n.gorod = (SELECT gorod FROM slugbi WHERE id=?) OR n.gorod = \'\' OR n.gorod IS NULL)
			AND (array[?]::integer[] <@ n.service_ids::integer[] OR n.service_ids IS NULL)
			'.(($tag)?' AND array[?]::varchar[] <@ n.tags::varchar[]':'').'
		ORDER BY '.(($sort_by_coords)?'distance_m NULLS LAST':'prime, id DESC'), $args);

		$tempRes = $res;
		foreach ($tempRes as $key => $val) {
			if ($val['prime'] && $key !== 0 && $key % 2 !== 0 && !$res[$key - 1]['prime'])
			{
				$res[$key - 1] = $tempRes[$key];
				$res[$key] = $tempRes[$key - 1];
			}
		}

		$res = array_map(function($val)
		{
			$val['tags'] = json_decode($val['tags']);

			return $val;
		}, $res);

		return parent::success($res);
	}

	public function item()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$tg_id = (int)$_POST['tg_id']??null;

		if (empty($tg_id))
			return parent::errCli('Post params tg_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetRow('SELECT
			id,
			image_preview,
			TO_CHAR(dt,\'YYYY.MM.DD HH24:MI\') AS dt,
			title,
			body
		FROM
			news
		WHERE id=?
			AND razdel=\'travel_guide\'
			AND end_ is not TRUE
			AND (gorod = (SELECT gorod FROM slugbi WHERE id=?) OR gorod = \'\' OR gorod IS NULL)
			AND (array[?]::integer[] <@ service_ids::integer[] OR service_ids IS NULL)', [$tg_id, $user_auth->service, $user_auth->service]);

		if (!$res)
			return parent::errSer('News not found', -5);

		$dbfirm->Execute('UPDATE news SET id_clients=(SELECT array(SELECT unnest(id_clients)
			UNION SELECT client.id)) FROM client WHERE news.id = ? AND client.phone_number = ?', [$tg_id, $user_auth->login]);

		return parent::success($res);
	}
}