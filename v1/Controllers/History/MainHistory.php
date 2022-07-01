<?php

namespace Uptaxi\Controllers\History;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class MainHistory extends MainController
{
	public function last_rides()
	{
		$user_auth = parent::cau();

		$offset = (int)@$_POST['offset'] ?? 0;

		if (!isset($user_auth->login))
			return $user_auth;

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('
			SELECT
				orders.id AS order_id,
				driver.id AS driver_id,
				bort.id AS bort_id,
				--to_char(orders.date_zakaza, CASE get_opt_s(\'date_format\') WHEN \'0\' THEN \'yyyy.mm.dd\' WHEN \'2\' THEN \'mm.dd.yyyy\' ELSE \'dd.mm.yyyy\' END || \' \' || CASE get_opt_s(\'hour_calculus\') WHEN \'1\' THEN \'HH24:MI\' ELSE \'HH12:MI AM\' END) as date,
				to_char(orders.date_zakaza, \'dd.MM.YY hh24:mi\') AS date,
				CASE WHEN orders.data_end > now()-interval \'12 hours\' THEN
					CASE WHEN get_opt_s(\'show_driver_phone\') = \'1\' THEN
						CASE WHEN get_opt_s(\'show_driver_phone2\') = \'1\' THEN
							coalesce(driver.phone2, driver.phone_number)
						ELSE
							driver.phone_number
						END
					ELSE
						slugbi.phone_disp
					END
				ELSE
					NULL
				END AS driver_phone,
				fn_get_driver_face_photo(driver.id) AS driver_photo,
				coalesce(people.i, \'\') AS driver_name,
				--CASE WHEN driver.geo_p[0] is null OR driver.geo_p[1] is null THEN \'\'::character varying ELSE cast(driver.geo_p[1]||\',\'||driver.geo_p[0] AS character varying) END AS driver_point,
				\'\' AS driver_point,
				CASE WHEN orders.id_bort = (-1) THEN \'\'::character varying ELSE car.model END AS car_model,
				CASE WHEN car.color is null THEN \'\' ELSE car.color END AS car_color,
				CASE WHEN car.gos_nomer is null THEN \'\'::character varying ELSE car.gos_nomer::character varying END AS car_plate,
				CASE WHEN orders.id_bort = ANY(client.bort_black_list) THEN true ELSE false END AS bort_lock,
				CASE WHEN orders.id_bort = ANY(client.bort_favorite_list) THEN true ELSE false END AS bort_favorite,
				/*CASE WHEN orders.end_ IS NULL THEN (
					SELECT CASE 
						WHEN orders_status_mobile.status is null THEN \'\'
						WHEN orders_status_mobile.status ~ \'99\' THEN \'preliminary\'
						WHEN orders_status_mobile.status ~ \'горит\' OR
							orders_status_mobile.status ~ \'поиск машины\' OR
							orders_status_mobile.status ~ \'думает\' OR
							orders_status_mobile.status ~ \'ищем\' THEN \'search_car\'
						WHEN orders_status_mobile.status ~ \'едет\' OR
							orders_status_mobile.status ~ \'встречный\' THEN \'on_the_way\'
						WHEN orders_status_mobile.status ~ \'по адресу\' THEN \'on_the_spot\'
						WHEN orders_status_mobile.status ~ \'работает\' THEN \'running\'
						WHEN orders_status_mobile.status ~ \'Изменение заказа\' THEN \'change_order\'
						WHEN orders_status_mobile.status ~ \'завершён\' THEN \'end\'
						ELSE orders_status_mobile.status
					END AS status
					FROM orders_status_mobile
					WHERE orders_status_mobile.id = orders.id
				) ELSE \'end\' END status,*/
				\'end\' AS status,
				orders.length_order AS order_length,
				CASE WHEN orders.korp THEN \'cashless\' WHEN orders.selected_payment_method_type IS NOT NULL AND orders.selected_payment_method_type <> \'\' THEN orders.selected_payment_method_type ELSE \'cash\' END AS payment_method,
				CASE WHEN orders.price_vruchnuu IS NOT NULL THEN orders.price_vruchnuu ELSE orders.price_local_taxometr END AS price,
				orders.receipt_link,
				(SELECT json_agg(row_to_json(foo)) AS addresses
					FROM
						(SELECT
						row_number() over (ORDER BY tochki.date_create ASC) AS side,
						coalesce(tochki.id_adres, 0) AS address_id,
						coalesce(tochki.podezd, \'\') AS entrance,
						coalesce(tochki.id_org, 0) AS org_id,
						coalesce(tochki.id_all, 0) AS all_id,
						CASE WHEN tochki.id_org ISNULL THEN coalesce(dbo.adress.city, \'\') ELSE coalesce(dbo.org.city, \'\') END AS city,
						CASE WHEN tochki.id_org ISNULL THEN coalesce(dbo.adress.street, \'\') ELSE coalesce(dbo.org.street, \'\') END AS street,
						CASE WHEN tochki.id_org ISNULL THEN coalesce(dbo.adress.dom, \'\') ELSE coalesce(dbo.org.number, \'\') END AS number,
						coalesce(dbo.org.city, \'\') AS org_city, --Temporary solution remove after first december
						coalesce(dbo.org.street, \'\') AS org_street, --Temporary solution remove after first december
						coalesce(dbo.org.number, \'\') AS org_number, --Temporary solution remove after first december
						coalesce(dbo.org.org, \'\') AS org_name,
						coalesce(dbo.all.descr, \'\') AS all_name,
						CASE
							WHEN dbo.adress.y notnull THEN dbo.adress.y
							WHEN dbo.org.geo_y notnull THEN dbo.org.geo_y
							WHEN dbo.all.y notnull THEN dbo.all.y
						ELSE
							0
						END as coord_x,
						CASE
							WHEN dbo.adress.x notnull THEN dbo.adress.x
							WHEN dbo.org.geo_x notnull THEN dbo.org.geo_x
							WHEN dbo.all.x notnull THEN dbo.all.x
						ELSE
							0
						END as coord_y
					FROM
						tochki
					LEFT JOIN
						dbo.adress ON tochki.id_adres = dbo.adress.id
					LEFT JOIN
						dbo.org ON tochki.id_org = dbo.org.id
					LEFT JOIN
						dbo.all ON tochki.id_all = dbo.all.id
					WHERE
						orders.id = tochki.id_order
						and tochki.del_ is null
						AND (tochki.id_adres > 0 OR tochki.id_org > 0 OR tochki.id_all > 0)
						--AND ((dbo.adress.x notnull AND dbo.adress.y notnull) OR (dbo.org.geo_x notnull AND dbo.org.geo_y notnull) OR (dbo.all.x notnull AND dbo.all.y notnull))
							ORDER BY
						tochki.date_create ASC
							)foo)::json AS addresses,
				driver_rating.*,
				dr.rating AS order_star,
				dr.comment AS feedback
			FROM
				orders
				INNER JOIN (SELECT id FROM orders WHERE phone_number = ? AND orders.event_end = \'ok\'
			AND
				orders.hide_mobile IS NOT TRUE
			AND
				orders.p_pervoi IS NOT NULL
			ORDER BY orders.date_zakaza DESC
			LIMIT 10
			OFFSET ?
			) t ON t.id = orders.id
				LEFT JOIN client ON client.id = orders.id_client
				LEFT JOIN bort ON bort.id = orders.id_bort
				LEFT JOIN car ON orders.id_car = car.id
				LEFT JOIN driver ON bort.id_driver = driver.id
				LEFT JOIN people ON people.id = driver.id_people
				LEFT JOIN slugbi ON orders.slugba = slugbi.name
				LEFT JOIN driver_rating AS dr ON dr.id_order = orders.id,
				get_driver_stars(driver.id) AS driver_rating
		', [$user_auth->login, $offset]);

		//For decode addresses and driver_rating (json string) to array
		$res = array_map(function($val)
			{
				$val['addresses'] = json_decode($val['addresses']);
				$val['driver_rating'] = json_decode($val['driver_rating']);
				return $val;
			}, $res);

		return parent::success($res);
	}

	public function delete_order()
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

		$res = (false !== $dbfirm->Execute('UPDATE orders SET hide_mobile = true WHERE id = ?', $order_id));

		return parent::success($res);
	}

	public function blocking_driver($type = 'unblock')
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = @(int)$_POST['order_id'];
		$comment = $_POST['comment'] ?? null;

		if (empty($order_id))
			return parent::errCli('Post parameter order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		if ($type == 'block')
		{
			$sql = 'SELECT res FROM fn_client_fb_list(?, (SELECT pozivnoi FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?)), ?, \'black\', \'add\', NULL)';
		} else {
			$sql = 'SELECT res FROM fn_client_fb_list(?, (SELECT pozivnoi FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?)), ?, \'black\', \'remove\', NULL)';
		}

		$res = (0 === $dbfirm->GetRow($sql, [$user_auth->login, $order_id, $comment])['res']);

		return parent::success($res);
	}

	public function favorite_driver($type = 'remove')
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = @(int)$_POST['order_id'];
		$comment = $_POST['comment'] ?? null;

		if (empty($order_id))
			return parent::errCli('Post parameter order_id is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		if ($type == 'add')
		{
			$sql = 'SELECT res FROM fn_client_fb_list(?, (SELECT pozivnoi FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?)), ?, \'favorite\', \'add\', NULL)';
		} else {
			$sql = 'SELECT res FROM fn_client_fb_list(?, (SELECT pozivnoi FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?)), ?, \'favorite\', \'remove\', NULL)';
		}

		$res = (0 === $dbfirm->GetRow($sql, [$user_auth->login, $order_id, $comment])['res']);

		return parent::success($res);
	}

	public function rating()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'];
		$stars = (int)$_POST['stars'];
		$comment = $_POST['comment'];

		if (empty($order_id) || empty($stars))
			return parent::errCli('Post parameter order_id or stars is empty or not valid', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$rating = $dbfirm->GetRow('
			SELECT
				dr.id,
				b.id_driver,
				o.data_end
			FROM orders o
			LEFT JOIN driver_rating dr ON dr.id_order = o.id
			LEFT JOIN client c ON o.id_client = c.id
			LEFT JOIN bort b ON o.id_bort = b.id
			WHERE
				o.event_end = \'ok\' AND c.phone_number = ? AND o.id = ?', [$user_auth->login, $order_id]);

		if (empty($rating['data_end']))
			return parent::errCli('Order not found or not completed', -5);

		//Getting differences between end time of order and now
		$diff = date_diff(date_create($rating['data_end']), date_create("now"));
		$hours = $diff->h + ($diff->days*24);
		if ($hours >= 24)
			return parent::errCli(Language::data('history')['cant_set_or_edit_after_24'], -7);

		if (empty($rating['id']))
		{
			$dbfirm->Execute('INSERT INTO driver_rating (id_driver, id_order, rating, comment) VALUES (?, ?, ?, ?)', [$rating['id_driver'], $order_id, $stars, $comment]);
			return parent::success('saved');
		} else {
			$dbfirm->Execute('UPDATE driver_rating SET id_driver=?, id_order=?, rating=?, comment=? WHERE id=?', [$rating['id_driver'], $order_id, $stars, $comment, $rating['id']]);
			return parent::success('edited');
		}
	}
}