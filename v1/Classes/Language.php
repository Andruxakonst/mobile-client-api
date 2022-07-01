<?php

namespace Uptaxi\Classes;

class Language
{
	private static $lang = null;
	private static $availableLang = ['en','es','kk','ky','ru','tr','uk','uz','uzcyr'];
	private static $collection = [];

	function __construct($lang)
	{
		self::$lang = $lang;
	}

	public static function data($path, $lang = null)
	{
		$lang = ($lang == null) ? self::$lang : mb_strtolower($lang);
		
		if (!in_array($lang, self::$availableLang)) {
			header('HTTP/1.1 400 Bad Request');
			header('Content-type: application/vnd.api+json');
			$data = [
				'status' => 'error',
				'id' => -4,
				'message' => 'Language not found'
			];
			echo json_encode($data); //TODO make class for answering
			die();
		}

		if(file_exists('lang/' . mb_strtolower($lang) . '/' . mb_strtolower($path) . '.json')) {
			$language = file_get_contents('lang/' . mb_strtolower($lang) . '/' . mb_strtolower($path) . '.json');
		} else {
			header('HTTP/1.1 400 Bad Request');
			header('Content-type: application/vnd.api+json');
			$data = [
				'status' => 'error',
				'id' => -4,
				'message' => 'Language not found'
			];
			echo json_encode($data); //TODO make class for answering
			die();
		}

		self::$collection[$lang][$path] = json_decode($language, true);
		return self::$collection[$lang][$path];
	}

	public static function check()
	{
		if (!in_array(self::$lang, self::$availableLang)) {
			header('HTTP/1.1 400 Bad Request');
			header('Content-type: application/vnd.api+json');
			$data = [
				'status' => 'error',
				'id' => -4,
				'message' => 'Language not found'
			];
			echo json_encode($data); //TODO make class for answering
			die();
		}
	}

	public static function get_current()
	{
		return self::$lang;
	}
}