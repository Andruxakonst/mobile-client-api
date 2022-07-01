<?php

namespace Uptaxi\Controllers\TravelGuide;

use Uptaxi\Controllers\MainController;

class View extends MainController
{
	public function main()
	{
		$error = null;
		$dbfirm = new \DBfirm();
		if (!$dbfirm->checkConnection())
			$error = 'Ошибка подключения к базе данных';

		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			$error = 'Нет сессии авторизации';

		$sort_by_coords = true;
		$coord_x = (float)@$_GET['x'] ?? null;
		$coord_y = (float)@$_GET['y'] ?? null;
		if (!$coord_x || !$coord_y)
			$sort_by_coords = false;

		$android = (@$_GET['platform'] === 'android');
		$show_tag_all = false;

		$user_token = (array_key_exists('token', $_GET))?$_GET['token']:$this->request->getHeaderLine('Auth');

		if ($error)
		{
			include __DIR__.'/src/tpl/error.tpl';
			$template = ob_get_clean();
			return $this->response->write($template, 200)
				->withHeader('Content-type', 'text/html');
		}

		$tags = $dbfirm->GetAll('SELECT
			unnest(tags) AS tag,
			sum(array_length(id_clients, 1)) AS sort
		FROM
			news n, client c
		WHERE
			razdel=\'travel_guide\'
			AND end_ IS NULL
			AND tags IS NOT NULL
			AND c.phone_number = ?
			AND (n.gorod = (SELECT gorod FROM slugbi WHERE id=?) OR n.gorod = \'\' OR n.gorod IS NULL)
			AND (array[?]::integer[] <@ n.service_ids::integer[] OR n.service_ids IS NULL)
		GROUP BY tag ORDER BY sort DESC', [$user_auth->login, $user_auth->service, $user_auth->service]);

		if (count($tags) > 1)
			$show_tag_all = true;

		$args = [$user_auth->login, $user_auth->service, $user_auth->service];

		if ($sort_by_coords)
			array_unshift($args, $coord_x, $coord_y);

		$items = $dbfirm->GetAll('SELECT
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
		ORDER BY '.(($sort_by_coords)?'distance_m NULLS LAST':'prime, id DESC'), $args);

		$tempItems = $items;
		foreach ($tempItems as $key => $val) {
			if ($val['prime'] && $key !== 0 && $key % 2 !== 0 && !$items[$key - 1]['prime'])
			{
				$items[$key - 1] = $tempItems[$key];
				$items[$key] = $tempItems[$key - 1];
			}
		}

		include __DIR__.'/src/tpl/view.tpl';
		$template = ob_get_clean();
		//Remove tabs and new line
		$template = preg_replace('/[\r\n]+/', '', $template);
		$template = preg_replace('/[\t]+/', '', $template);
		return $this->response->write($template, 200)
			->withHeader('Content-type', 'text/html');
	}

	public function assets()
	{
		if ($this->arg['type'] === 'css' && $this->arg['file'] === 'main.css')
			return self::main_css();

		$file_arr = explode('.', $this->arg['file']);
		$extension = end($file_arr);

		include __DIR__.'/src/assets/'.$this->arg['type'].'/'.$this->arg['file'];
		$template = ob_get_clean();
		//Remove tabs and new line
		$template = preg_replace('/[\r\n]+/', '', $template);
		$template = preg_replace('/[\t]+/', '', $template);
		return $this->response->write($template, 200)
			->withHeader('Content-type', 'text/'.$extension);
	}

	public function main_css()
	{
		$android = (@$_GET['platform'] === 'android');

		include __DIR__.'/src/tpl/main_css.tpl';
		$template = ob_get_clean();
		//Remove tabs and new line
		$template = preg_replace('/[\r\n]+/', '', $template);
		$template = preg_replace('/[\t]+/', '', $template);
		return $this->response->write($template, 200)
			->withHeader('Content-type', 'text/css');
	}
}