<?php

namespace Uptaxi\Controllers\Services;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Available extends MainController
{
	public function Main()
	{
		// if ($_SERVER['REMOTE_ADDR'] == '128.70.236.194')
		// {
		// 	$res = [[
		// 		'firm_id' => 37,
		// 		'service_id' => 9,
		// 		'country' => 'Россия',
		// 		'city' => 'Зеленогорск',
		// 		'service' => 'Корона (Эконом)',
		// 		'ip' => '5.9.41.14',
		// 		'port' => '8002',
		// 		'ws_port' => '3202',
		// 		'tariff' => 'Тариф: 60руб., далее 12руб./km',
		// 		'map_mobile' => 'Google',
		// 		'order_count' => 193
		// 	]];

		// 	return parent::success($res);
		// }

		$dbbirga = new \DBbirga();
		if (!$dbbirga->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$paramsSQL = @$_POST['firm_group'] ?: null;
		$additionalSQL = ($paramsSQL) ? 'c.firm_group ~ ?::varchar AND' : '';
		$paramsServiceType = @$_POST['service_type'] ?: null;
		$additionalDelivery = ($paramsServiceType === 'delivery')?'c.delivery IS TRUE AND' : 'c.delivery IS NOT TRUE AND c.show_mobile AND';
		$isq_exclude = ($paramsSQL == 'isq') ? '' : ' AND ssp.idfirm<>695 AND ssp.idfirm<>748 AND ssp.idfirm<>753';
		$coord_x = (float)@$_POST['x'] ?? null;
		$coord_y = (float)@$_POST['y'] ?? null;
		$by_coord = (!empty($coord_x) && !empty($coord_y))?"point($coord_y,$coord_x) <@ slugbi.pol AND":'';
		$firm_id = (int)@$_POST['firm_id'] ?: null;
		$service_id = (int)@$_POST['service_id'] ?: null;
		$by_firm_id_service_id = (!empty($firm_id) && !empty($service_id))?"firms.id = $firm_id AND serviceid = $service_id AND":'';

		$res = $dbbirga->GetAll('SELECT ssp.idfirm AS firm_id, serviceid AS service_id, c.country, c.city, service, ssp.ip, ssp.port_http AS port, firms.web_socket_port AS ws_port, tariff_json AS tariff, firms.map_mobile, coalesce(c.order_count, 0) AS order_count, pol_service FROM clientsite AS c LEFT JOIN firms ON c.firm_id = firms.id LEFT JOIN ssp ON firms.id = ssp.idfirm LEFT JOIN slugbi ON slugbi.firma= firms.name  and c.service = slugbi.slugba WHERE
			'.$by_firm_id_service_id.'
			'.$by_coord.'
			'.$additionalSQL.'
			'.$additionalDelivery.'
			firms.online AND ssp.idfirm notnull AND serviceid notnull AND c.country notnull AND c.city notnull AND service notnull AND ssp.ip notnull AND ssp.port_http notnull AND c.country<>\'\' AND c.city<>\'\' AND c.city<>\'Крым\' AND service<>\'\' AND ssp.ip<>\'\' AND ssp.port_http<>\'\''.$isq_exclude.' ORDER BY country, c.city, service', $paramsSQL);

		// For decode and combine tariff (json string)
		$res = array_map(function($val) use ($paramsServiceType)
		{
			if ($paramsServiceType === 'delivery')
				$val['delivery_url'] = 'https://'.str_pad($val['firm_id'], 4, '0', STR_PAD_LEFT).'.upphone.ru:'.($val['ws_port']+300).'/appFrame';

			$arrTariff = @json_decode($val['tariff']);
			if (empty($arrTariff))
			{
				$val['tariff'] = null;
				return $val;
			}

			$tariffStr = Language::data('services')['tariff'].': ';
			$tariffStr .= floatval($arrTariff->seat).$arrTariff->cur;
			if ((int)$arrTariff->dist_m !== 0)
			{
				$tariffStr .= ' '.Language::data('services')['until'].' ';
				$tariffStr .= floatval($arrTariff->dist_m / (($arrTariff->lm == 'km') ? 1000:1609.344000614692)).$arrTariff->lm;
			}
			$tariffStr .= ', '.Language::data('services')['next'].' ';
			$tariffStr .= floatval($arrTariff->pl).$arrTariff->cur.'/'.$arrTariff->lm;

			$val['tariff'] = $tariffStr;
			return $val;
		}, $res);

		return parent::success($res);
	}

	public function init_service()
	{
		$id_firm = (int)$_POST['id_firm'] ?? null;
		$service = (int)$_POST['service'] ?? null;

		if (empty($id_firm) || empty($service))
			return parent::errCli('id_firm or service is empty or not valid', -4);

		$dbfirm = new \DBfirm($id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$res = $dbfirm->GetAll('SELECT
			o.opt AS key,
			COALESCE(lr.custom, lr.initial, o.val_s) AS val
		FROM
			opt o
		LEFT JOIN
			lang_resource lr
		ON lr.key = o.opt AND for_what = \'t.opt\' AND lr.lang = ?
		WHERE
			(o.hs->\'for_api\')=\'1\'
		ORDER BY o.opt', Language::get_current());

		$arr = [];
		foreach ($res as $re) {
			$arr[$re['key']] = $re['val'];
		}

		//Additional opt from current service
		$from_service = $dbfirm->GetRow('SELECT
				phone_disp AS disp_phone,
				coalesce(type, \'taxi\') AS type_of_service,
				besplatnaya_poezdka AS free_ride_count,
				besplatnaya_poezdka_sum AS free_ride_price,
				besplatnaya_poezdka_proc_kompens AS free_ride_proc,
				delivery_frame
			FROM
				slugbi
			WHERE id=?', $service);
		$arr = array_merge($from_service, $arr);

		$map_center = explode(',', $arr['centr_karti']);
		$arr['centr_karti'] = $map_center[1].','.$map_center[0];

		$bad_rating_reason = implode(array_column($dbfirm->GetAll('SELECT
			COALESCE(lr.custom, lr.initial) AS val
		FROM
			rating_reason rr
		LEFT JOIN
			lang_resource lr
		ON lr.key::integer = rr.id AND for_what = \'t.rating_reason\' AND lr.lang = ?
		WHERE
			stars = (SELECT min(stars) FROM rating_reason)
		ORDER BY rr.sort', Language::get_current()), 'val'), ';');

		$arr['bad_rating_reason'] = $bad_rating_reason;

		$travel_guide = $dbfirm->GetRow('SELECT
			count(n.id) <> 0 AS prop
		FROM
			news n
		WHERE
			razdel=\'travel_guide\'
			AND end_ IS NULL
			AND tags IS NOT NULL
			AND (n.gorod = (SELECT gorod FROM slugbi WHERE id=?) OR n.gorod = \'\' OR n.gorod IS NULL)
			AND (array[?]::integer[] <@ n.service_ids::integer[] OR n.service_ids IS NULL)', [$service, $service]);

		$arr['travel_guide'] = (bool)$travel_guide['prop'];

		return parent::success($arr);
	}
}