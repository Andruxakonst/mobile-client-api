<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class AddressByCoords extends MainController
{
	public function Main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$coord_x = (float)$_POST['x'] ?? null;
		$coord_y = (float)$_POST['y'] ?? null;
		$nearest = (@$_POST['nearest'] === 'true');

		if (empty($coord_x) || empty($coord_y))
			return parent::errCli('Post params x or y is empty', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$distance_m = (int)$dbfirm->GetRow('SELECT get_opt_s(\'distance_m_to_nearby_address\') as val')['val']?:25;

		$sqlArg = ($nearest)?[$distance_m, $coord_x.', '.$coord_y]:[$coord_x.', '.$coord_y];
		$res = $dbfirm->GetAll('SELECT gafpmm.*'.(($nearest)?', CASE WHEN distance_m > ? THEN TRUE ELSE FALSE END AS nearest':'').' FROM get_adres_from_p_mobile_multi3(?) gafpmm WHERE distance_m < 100 ORDER BY 8 LIMIT 5', $sqlArg);

		if (empty($res[0]['distance_m']) || (!$nearest && $res[0]['distance_m'] > $distance_m))
		{
			$o_res = self::googleReverseGeo($coord_x, $coord_y, $dbfirm);

			if ((empty($res[0]['distance_m']) && $o_res === null) || (!$nearest && $res[0]['distance_m'] > $distance_m))
				return parent::errSer('Can\'t find anything', -5);

			if ($o_res !== null)
			{
				$add_res['id_address'] = null;
				$add_res['region'] = $o_res['region'];
				$add_res['city'] = $o_res['city'];
				$add_res['street'] = $o_res['street'];
				$add_res['house'] = $o_res['house'];
				$add_res['x'] = $o_res['x'];
				$add_res['y'] = $o_res['y'];
				$add_res['distance_m'] = 0;
				$add_res['distance_ft'] = 0;
				$add_res['org'] = null;
				$add_res['purpose'] = null;
				$add_res['nearest'] = true;

				array_unshift($res, $add_res);
			} //else {
			// 	$add_res['id_address'] = null;
			// 	$add_res['region'] = null;
			// 	$add_res['city'] = null;
			// 	$add_res['street'] = null;
			// 	$add_res['house'] = null;
			// 	$add_res['x'] = null;
			// 	$add_res['y'] = null;
			// 	$add_res['distance_m'] = null;
			// 	$add_res['distance_ft'] = null;
			// 	$add_res['org'] = null;
			// 	$add_res['purpose'] = null;
			// 	$add_res['nearest'] = true;
			// }

			// return parent::success($add_res);
			// array_unshift($res, $add_res);
		}

		if ($res[0]['org'] != null && @count($res) >= 2 && $res[0]['street'] !== null && $res[0]['house'] !== null)//Org must have street and house
		{
			$founded_addr = false;
			$founded_addr_id = null;
			$many_org = false;

			foreach ($res as $key => $val) {
				if ($key == 0)
					continue;

				if ($res[0]['house'] == $val['house'] &&
					$res[0]['street'] == $val['street'] &&
					$res[0]['city'] == $val['city'] &&
					$res[0]['region'] == $val['region'])
				{
					if ($val['org'] == null)
					{
						$founded_addr_id = $val['id_address'];
						$founded_addr = true;
						unset($res[$key]);
					} else {
						$many_org = true;
					}
				}
			}

			if ($founded_addr)
				$res = array_values($res);

			if ($founded_addr || (!$founded_addr && $many_org))
			{
				$add_res['id_address'] = $founded_addr_id;
				$add_res['region'] = $res[0]['region'];
				$add_res['city'] = $res[0]['city'];
				$add_res['street'] = $res[0]['street'];
				$add_res['house'] = $res[0]['house'];
				$add_res['x'] = $res[0]['x'];
				$add_res['y'] = $res[0]['y'];
				$add_res['distance_m'] = $res[0]['distance_m'];
				$add_res['distance_ft'] = $res[0]['distance_ft'];
				$add_res['org'] = null;
				$add_res['purpose'] = null;
				$add_res['nearest'] = $res[0]['nearest'];

				array_unshift($res, $add_res);
			}
		}

		// if (empty($res[0]['id_address']) && (strtolower($user_auth->country_iso) == 'ru' || strtolower($user_auth->country_iso) == 'kg' || strtolower($user_auth->country_iso) == 'ab'))
		// {
		// 	// return self::yandexReverseGeo($coord_x, $coord_y);
		// 	return self::googleReverseGeo($coord_x, $coord_y, $dbfirm);
		// } else if (empty($res[0]['id_address']) && strtolower($user_auth->country_iso) != 'ru')
		// {
		// 	return self::googleReverseGeo($coord_x, $coord_y, $dbfirm);
		// }

		return parent::success($res);
	}

	public function yandexReverseGeo($coord_x, $coord_y)
	{
		$obj = [];
		$ch = curl_init();
		$coords = $coord_y.','.$coord_x;
		$lang = Language::get_current();
		$url = "https://geocode-maps.yandex.ru/1.x/?format=json&geocode=$coords&lang=$lang&results=1";

		if (FALSE === $ch) {
			// return parent::errSer('Geocoding initialization failed', -5);
			return null;
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		$featureMember = @$response['response']['GeoObjectCollection']['featureMember'];

		if (@count($featureMember) > 0) {
			$address = $featureMember[0]['GeoObject']['metaDataProperty']['GeocoderMetaData']['AddressDetails'];

			$obj['region'] = (isset($address['Country']['AdministrativeArea']['AdministrativeAreaName']))?$address['Country']['AdministrativeArea']['AdministrativeAreaName']:null;

			$obj['city'] = (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['LocalityName']))?$address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['LocalityName']:((isset($address['Country']['AdministrativeArea']['Locality']['LocalityName']))?$address['Country']['AdministrativeArea']['Locality']['LocalityName']:null);

			if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['Thoroughfare']['ThoroughfareName'])) {
				$obj['street'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['Thoroughfare']['ThoroughfareName'];
			} else if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Thoroughfare']['ThoroughfareName'])) {
				$obj['street'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Thoroughfare']['ThoroughfareName'];
			} else if (isset($address['Country']['AdministrativeArea']['Locality']['Thoroughfare']['ThoroughfareName'])) {
				$obj['street'] = $address['Country']['AdministrativeArea']['Locality']['Thoroughfare']['ThoroughfareName'];
			} else if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['DependentLocalityName'])) {
				$obj['street'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['DependentLocalityName'];
			} else {
				$obj['street'] = null;
			}

			if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['Thoroughfare']['Premise']['PremiseNumber'])) {
				$obj['house'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['Thoroughfare']['Premise']['PremiseNumber'];
			} else if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Thoroughfare']['Premise']['PremiseNumber'])) {
				$obj['house'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Thoroughfare']['Premise']['PremiseNumber'];
			} else if (isset($address['Country']['AdministrativeArea']['Locality']['Thoroughfare']['Premise']['PremiseNumber'])) {
				$obj['house'] = $address['Country']['AdministrativeArea']['Locality']['Thoroughfare']['Premise']['PremiseNumber'];
			} else if (isset($address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Premise']['PremiseNumber'])) {
				$obj['house'] = $address['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['DependentLocality']['Premise']['PremiseNumber'];
			} else {
				$obj['house'] = null;
			}

			$obj['x'] = $coord_x;
			$obj['y'] = $coord_y;
			// return parent::success([$obj]);
			return $obj;
		} else {
			// return parent::errSer('Can\'t find anything', -5);
			return null;
		}
	}

	public function googleReverseGeo($coord_x, $coord_y, $dbfirm)
	{
		$obj = [];
		$obj['region'] = null;
		$obj['city'] = null;
		$obj['street'] = null;
		$obj['house'] = null;

		$ch = curl_init();
		$address = $coord_x.','.$coord_y;
		$lang = Language::get_current();
		// $api_key = 'AIzaSyDsogh43357fGyY2E0fMiLqXIkJtPq0Y6E'; // old
		// $api_key = 'AIzaSyB80cTbS4fSt61fxjCKqE6rg6pjnQ7nIjE'; // new
		$api_key = $dbfirm->GetRow('SELECT get_opt_s(\'api_key_google\') as val')['val'];
		if (empty($api_key))
			$api_key = 'AIzaSyDsogh43357fGyY2E0fMiLqXIkJtPq0Y6E';
		$url = "https://maps.googleapis.com/maps/api/geocode/json?address=$address&language=$lang&key=$api_key";

		if ($ch === false) {
			// return parent::errSer('Geocoding initialization failed', -5);
			return null;
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		if ($response['status'] == 'OK') {
			for ($i = 0; $i < count($response['results'][0]['address_components']); $i++)
			{
				foreach ($response["results"][0]['address_components'][$i]['types'] as $key => $value)
				{
					switch($value)
					{
						case 'administrative_area_level_1':
							$obj['region'] = $response['results'][0]['address_components'][$i]['long_name'];
							break;

						case 'locality':
							$obj['city'] = $response['results'][0]['address_components'][$i]['long_name'];
							break;

						case 'route':
							$obj['street'] = $response['results'][0]['address_components'][$i]['short_name'];
							break;

						case 'street_number' :
							$obj['house'] = $response['results'][0]['address_components'][$i]['long_name'];
							break;
					}
				}
			}

			$obj['x'] = $coord_x;
			$obj['y'] = $coord_y;

			if ($obj['street'] == 'Unnamed Road')
				return self::yandexReverseGeo($coord_x, $coord_y);

			// return parent::success([$obj]);
			return $obj;
		} else {
			return self::yandexReverseGeo($coord_x, $coord_y);
		}
	}
}