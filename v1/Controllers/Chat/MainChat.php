<?php

namespace Uptaxi\Controllers\Chat;

use Uptaxi\Controllers\MainController;

class MainChat extends MainController
{
	public function messages()
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

		$res = $dbfirm->GetAll('
			SELECT
				id_order as order_id,
				id,
				message AS text,
				TO_CHAR(dt, \'HH24:MI\') AS time,
				CASE WHEN direction_bort IS NOT NULL THEN \'received\' ELSE \'sent\' END AS type,
				status AS label
			FROM
				chat_driver_client
			WHERE id_order = ?
			ORDER BY id ASC;
		', $order_id);

		return parent::success($res);
	}

	public function send()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'];
		$message = trim($_POST['message']);

		if (empty($order_id))
			return parent::errCli('Post parameter order_id is empty or not valid', -4);

		if (empty($message))
			return parent::errCli('You can\'t send empty message', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$dbfirm->Execute('INSERT INTO chat_driver_client (dt, id_order, id_bort, message, status) VALUES (now(), ?, (SELECT id_bort FROM orders WHERE id=?), ?, 1) RETURNING id', [$order_id, $order_id, $message]);

		return parent::success(true);
	}

	public function read_or_received($type = 'received')
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		$order_id = (int)$_POST['order_id'];
		$messages = $_POST['messages'];

		if (empty($order_id) || empty($messages))
			return parent::errCli('Post parameter order_id or messages is empty or not valid', -4);

		$data = (array)@json_decode($messages);

		if ($data === null && json_last_error() !== JSON_ERROR_NONE)
			return parent::errCli('Post parameter messages must be in json', -4);

		$dbfirm = new \DBfirm($user_auth->id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$type = ($type == 'readed') ? 3 : 2;

		$sql = 'UPDATE chat_driver_client SET status = ? WHERE id_order = ? AND id IN ('.implode(',', array_fill(0, count($data), '?')).')';
		$params = [$type, $order_id];

		$params = array_merge($params, $data);

		$dbfirm->Execute($sql, $params);

		return parent::success(true);
	}
}