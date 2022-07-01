<?php

namespace Uptaxi\Controllers\Map;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class Route extends MainController
{
	public function get()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$coords_x = $_POST['x'] ?? null;
		$coords_y = $_POST['y'] ?? null;

		if (empty($coords_x) || empty($coords_y))
			return parent::errCli('Post params x or y is empty', -4);

		//No array [START]
		$coords_x = (array)@json_decode($coords_x);

		if ($coords_x === null && json_last_error() !== JSON_ERROR_NONE)
			return parent::errCli('Post parameter "x" must be in json', -4);

		$coords_y = (array)@json_decode($coords_y);

		if ($coords_y === null && json_last_error() !== JSON_ERROR_NONE)
			return parent::errCli('Post parameter "y" must be in json', -4);
		//No array [END]

		if (count($coords_x) < 2 || count($coords_y) < 2)
			return parent::errCli('Minimum two points', -4);

		if (count($coords_x) !== count($coords_y))
			return parent::errCli('Count of coords are different', -4);

		$route_path = [];
		$route_length = 0;

		for ($i=0, $max = count($coords_x)-1; $i < $max; $i++) {
			try {
				if (false === ($data = file_get_contents('http://localhost:9100/?lon1='.$coords_y[$i].'&lat1='.$coords_x[$i].'&lon2='.$coords_y[$i+1].'&lat2='.$coords_x[$i+1])))
					return parent::errSer('Failed to open stream');

				$ans = json_decode($data);
				$route_path = array_merge($route_path, $ans->path);
				$route_length += $ans->length_order;
			} catch (Exception $err) {
				return parent::errSer($err->getMessage());
			}
		}

		$res = [
			'path' => $route_path,
			'length' => $route_length
		];

		return parent::success($res);
	}
}