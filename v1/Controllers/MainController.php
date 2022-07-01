<?php
namespace UpTaxi\Controllers;

class MainController
{
	protected $request;
	protected $response;
	protected $arg;

	function __construct($request, $response, $arg) {
		$this->request = $request;
		$this->response = $response;
		$this->arg = $arg;
	}

	public function plainText($answer)
	{
		self::writeLog($answer);
		return $this->response->write($answer)
			->withHeader('Content-type', 'text/plain');
	}

	public function customAns($answer, $code = 200)
	{
		self::writeLog($answer);
		return $this->response->withJson($answer, $code)
			->withHeader('Content-type', 'application/vnd.api+json');
	}

	public function success($data, $id = 1)
	{
		$answer = [
			'status' => 'ok',
			'id' => $id,
			'data' => $data
		];
		self::writeLog($answer);
		return $this->response->withJson($answer, 200)
			->withHeader('Content-type', 'application/vnd.api+json');
	}

	public function errCLi($message, $id = -1, $code = 400)
	{
		$answer = [
			'status' => 'error',
			'id' => $id,
			'message' => $message
		];
		self::writeLog($answer);
		return $this->response->withJson($answer, $code)
			->withHeader('Content-type', 'application/vnd.api+json');
	}

	public function errSer($message, $id = -1, $code = 500)
	{
		$answer = [
			'status' => 'error',
			'id' => $id,
			'message' => $message
		];
		self::writeLog($answer);
		return $this->response->withJson($answer, $code)
			->withHeader('Content-type', 'application/vnd.api+json');
	}

	public function unauthorized()
	{
		$answer = [
			'status' => 'error',
			'id' => -2,
			'message' => 'Required authentication to access the requested resource'
		];
		self::writeLog($answer);
		return $this->response->withJson($answer, 401)
			->withHeader('WWW-Authenticate', 'AuthHeader')
			->withHeader('Content-type', 'application/vnd.api+json');
		;
	}

	public function serverDie()
	{
		//SERVER MUST DIE!!!
		if (function_exists('posix_kill')) {
			posix_kill(posix_getpid(), 9);
		} elseif (function_exists('exec') && strstr(PHP_OS, 'WIN')) {
			exec("taskkill /F /PID ".getmypid()) ? TRUE : FALSE;
		}
	}

	public function cau($ocwe = false) //ocwe is only_check_without_error
	{
		$data = $this->request->getHeaderLine('Auth');

		if (empty($data) && array_key_exists('token', $_GET))
			$data = 'dUFFnMCZRt5COCYa7oTtq4v2yxlHuVC42YPdQspZ+8X6eZaYleGo+OjUS6md0KXnsK+VtOKaFqkq6YqbZFle5KhrSrIgxLWbcyrYJDMSBmB7ryCf8pNLJ8XX4v2nri8rGnnjsKOLQRKOrrb+sB9Zw8f+6JjUWg5dszisRmZoEGQ';
			// $data = $_GET['token'];//'dUFFnMCZRt5COCYa7oTtq4v2yxlHuVC42YPdQspZ+8X6eZaYleGo+OjUS6md0KXnsK+VtOKaFqkq6YqbZFle5KhrSrIgxLWbcyrYJDMSBmB7ryCf8pNLJ8XX4v2nri8rGnnjsKOLQRKOrrb+sB9Zw8f+6JjUWg5dszisRmZoEGQ';

		if (empty($data) && !$ocwe)
		{
			return self::unauthorized();
		}

		$iv = getenv('IV');
		$mk = getenv('MK');
		$method = getenv('METHOD');

		$res = @json_decode(openssl_decrypt($data, $method, $mk, false, $iv));

		if (!$ocwe && $res === null && json_last_error() !== JSON_ERROR_NONE)
		{
			//TODO: Place for block by iP
			// echo "block by iP";
			return self::unauthorized();
		}

		if (!$ocwe && $res->serv_ini !== $_SERVER['HTTP_HOST'])
			return self::unauthorized();

		if (!$ocwe && $res->id_firm != getenv('FIRM_ID') && DIRECTORY_SEPARATOR == '/')
			return self::serverDie();

		if ($_SERVER['REMOTE_ADDR'] == '217.118.90.237')
			return self::unauthorized();

		return $res;
	}

	private function writeLog($answer)
	{
		$user = self::getUserIdentity();
		$ip_add = ' ('.$_SERVER['REMOTE_ADDR'].')';
		$log_data = date("Y-m-d H:i:s").' user: '.$user.$ip_add."\n";
		$log_data .= 'URL: '.$_SERVER['REQUEST_URI']."\n";
		$log_data .= 'Requested data: '.print_r($_POST, true)."\n";
		$log_data .= 'Answer data: '.json_encode($answer, JSON_UNESCAPED_UNICODE)."\n\n";
		$log_data .= 'Token: '.$this->request->getHeaderLine('Auth')."\n\n\n";

		$log_path_date = 'logs/'.date("Y-m-d").'/';
		$log_path_hour = $log_path_date.'/'.date("H").'/';

		if (!file_exists($log_path_date))
			@mkdir($log_path_date, 0777, true);

		if (!file_exists($log_path_hour))
			@mkdir($log_path_hour, 0777, true);

		file_put_contents($log_path_hour.trim($user, '+').'.log', $log_data, FILE_APPEND);
	}

	private function getUserIdentity() {
		$user_auth = self::cau(true);
		$user_identity = 'unknown';

		if (!empty($user_auth->login))
			$user_identity = $user_auth->login;

		return $user_identity;
	}
}