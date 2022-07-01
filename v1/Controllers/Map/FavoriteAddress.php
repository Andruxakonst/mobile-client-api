<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class FavoriteAddress extends MainController
{
	public function set()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$res_type = $_POST['res_type'] ?? null;
		$desc = $_POST['desc'] ?? null;
		$sys_desc = $_POST['sys_desc'] ?? null;
		$city = $_POST['city'] ?? null;
		$street = $_POST['street'] ?? null;
		$house = $_POST['house'] ?? null;
		$entrance = $_POST['entrance'] ?? null;
		$comment = $_POST['comment'] ?? null;
		$x = floatval($_POST['x']) ?? null;
		$y = floatval($_POST['y']) ?? null;
		$id_org = (int)@$_POST['id_org'] ?? null;
		$id_all = (int)@$_POST['id_all'] ?? null;

		if (empty($res_type) || empty($desc) || empty($city) || empty($street) || empty($entrance) || empty($x) || empty($y))
			return parent::errCli('Post params res_type, desc, city, street, entrance, x or y is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$check = $dbfirm->GetRow('SELECT
			id
		FROM
			client_favorite_address
		WHERE
			id_client = (SELECT id FROM client WHERE phone_number = ?)
			AND res_type = ?::varchar
			AND "desc" = ?::varchar
			AND city = ?::varchar
			AND street = ?::varchar
			AND house = ?::varchar
			AND entrance = ?::varchar
			AND comment = ?::varchar
			AND y = ?::numeric
			AND x = ?::numeric
			AND id_org = ?::integer
			AND id_all = ?::integer
			AND sys_desc = ?::varchar LIMIT 1', [$user_auth->login, $res_type, $desc, $city, $street, $house, $entrance, $comment, $x, $y, $id_org, $id_all, $sys_desc])['id'];

		if (!empty($check))
			return parent::errCli(Language::data('map')['fav_addr_already_saved']);

		$dbfirm->beginTransaction();

		$res = $dbfirm->Execute('INSERT INTO client_favorite_address (id_client, dt, res_type, "desc", city, street, house, entrance, comment, y, x, id_org, id_all, sys_desc) VALUES ((SELECT id FROM client WHERE phone_number = ?), now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$user_auth->login, $res_type, $desc, $city, $street, $house, $entrance, $comment, $x, $y, $id_org, $id_all, $sys_desc]);

		$id = $dbfirm->lastInsertId('client_favorite_address');

		if (!$dbfirm->commit())
			return parent::errSer(Language::data('global')['unknown_error'].' #MS1ASIFA');

		return parent::success($id);
	}

	public function get()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$client_address = (@$_POST['client_address'] === 'true');

		$res = $dbfirm->GetAll('SELECT
			id,
			res_type,
			"desc",
			city,
			street,
			house,
			entrance,
			comment,
			y AS x,
			x AS y,
			id_org,
			id_all,
			sys_desc
		FROM
			client_favorite_address
		WHERE id_client = (SELECT id FROM client WHERE phone_number = ?)'.(($client_address)?'':' AND (sys_desc <> \'client_address\' OR sys_desc IS NULL)').' ORDER BY sys_desc', $user_auth->login);

		return parent::success($res);
	}

	public function update()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$id = (int)$_POST['id'] ?? null;
		$res_type = $_POST['res_type'] ?? null;
		$desc = $_POST['desc'] ?? null;
		$sys_desc = $_POST['sys_desc'] ?? null;
		$city = $_POST['city'] ?? null;
		$street = $_POST['street'] ?? null;
		$house = $_POST['house'] ?? null;
		$entrance = $_POST['entrance'] ?? null;
		$comment = $_POST['comment'] ?? null;
		$x = floatval($_POST['x']) ?? null;
		$y = floatval($_POST['y']) ?? null;
		$id_org = (int)$_POST['id_org'] ?? null;
		$id_all = (int)$_POST['id_all'] ?? null;

		if (empty($id) || empty($res_type) || empty($desc) || empty($city) || empty($street) || empty($entrance) || empty($x) || empty($y))
			return parent::errCli('Post params res_type, desc, city, street, entrance, x or y is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$check = $dbfirm->GetRow('SELECT
			id
		FROM
			client_favorite_address
		WHERE
			id_client = (SELECT id FROM client WHERE phone_number = ?)
			AND id = ? LIMIT 1', [$user_auth->login, $id])['id'];

		if (empty($check))
			return parent::errCli(Language::data('map')['fav_addr_not_found']);

		$res = (false !== $dbfirm->Execute('UPDATE client_favorite_address SET dt = now(), res_type = ?, "desc" = ?, city = ?, street = ?, house = ?, entrance = ?, comment = ?, y = ?, x = ?, id_org = ?, id_all = ?, sys_desc = ? WHERE id = ?', [$res_type, $desc, $city, $street, $house, $entrance, $comment, $x, $y, $id_org, $id_all, $sys_desc, $id]));

		if (!$res)
			return parent::errSer(Language::data('global')['unknown_error'].' #MS2UIFS');

		return parent::success(true);
	}

	public function del()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$id = (int)$_POST['id']??null;

		if (empty($id))
			return parent::errCli('Post param id is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->Execute('DELETE FROM client_favorite_address WHERE
			id_client = (SELECT id FROM client WHERE phone_number = ?) AND id = ?
		', [$user_auth->login, $id]);

		if ($res)
			return parent::success(true);

		return parent::errSer(Language::data('global')['unknown_error'].' #MD3ADIFR');
	}
}