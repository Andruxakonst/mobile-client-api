<?php

namespace Uptaxi\Controllers\News;

use Uptaxi\Controllers\MainController;

class Get extends MainController
{
	public function get_list()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			n.id
			,TO_CHAR(coalesce(n.dt_begin, n.dt),\'YYYY.MM.DD HH24:MI\') AS dt
			,n.title
			,n.short_text
			,CASE WHEN array[c.id]::integer[] <@ n.id_clients::integer[] THEN true ELSE false END AS readed
		FROM
			news n, client c
		WHERE
			(razdel=\'client_news\' OR (razdel=\'advt\' AND array[?]::varchar[] <@ n.package_name::varchar[]))
			AND end_ is not TRUE
			AND c.phone_number = ?
			AND (n.gorod = (SELECT gorod FROM slugbi WHERE id=?) OR n.gorod = \'\' OR n.gorod IS NULL)
			AND (array[?]::integer[] <@ n.service_ids::integer[] OR n.service_ids IS NULL)
		ORDER BY razdel, id DESC', [($user_auth->p_n)??null, $user_auth->login, $user_auth->service, $user_auth->service]);

		return parent::success($res);
	}

	public function get_item()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$news_id = (int)$_POST['news_id'] ?? null;

		if (empty($news_id))
			return parent::errCli('Post params news_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			id
			,TO_CHAR(coalesce(dt_begin, dt),\'YYYY.MM.DD HH24:MI\') AS dt
			,title
			,body
		FROM
			news
		WHERE id=?
			AND (razdel=\'client_news\' OR razdel=\'promo_action\' OR razdel=\'advt\')
			AND end_ is not TRUE
			AND (gorod = (SELECT gorod FROM slugbi WHERE id=?) OR gorod = \'\' OR gorod IS NULL)
			AND (array[?]::integer[] <@ service_ids::integer[] OR service_ids IS NULL)', [$news_id, $user_auth->service, $user_auth->service]);

		if (count($res) == 0)
			return parent::errSer('News not found', -5);

		$dbfirm->Execute('UPDATE news SET id_clients=(SELECT array(SELECT unnest(id_clients)
			UNION SELECT client.id)) FROM client WHERE news.id = ? AND client.phone_number = ?', [$news_id, $user_auth->login]);

		return parent::success($res);
	}
}