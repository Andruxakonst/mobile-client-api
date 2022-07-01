<?php
ini_set('max_execution_time', '300');

include_once __DIR__ . '/../../../classes/db/dbconfig.php';

$getopt = getopt("o:");
$order_id = (!empty($getopt['o'])) ? $getopt['o'] : '';

if (empty($order_id))
{
	echo 'ERROR: order id is empty';
	die();
}

$dbfirm = new \DBfirm();
if (!$dbfirm->checkConnection())
{
	echo 'ERROR: connecting to the DB';
	die();
}

$order_data = (object)$dbfirm->GetRow('
	SELECT
		o.id,
		o.phone_number,
		CASE WHEN o.price_vruchnuu IS NOT NULL THEN o.price_vruchnuu ELSE o.price_local_taxometr END AS price,
		o.selected_payment_method_type,
		get_opt_s(\'payment_url\') AS beeline_server,
		split_part(get_opt_s(\'terminal_key\'), \'::\', 1) AS merchant,
		(SELECT pozivnoi FROM bort WHERE id = o.id_bort) AS requisite,
		get_opt_s(\'secret_key\') AS password,
		get_opt_s(\'service_id\') AS service_id
	FROM
		orders o
	WHERE o.id = ?
', $order_id);

if (empty($order_data->price))
{
	echo 'ERROR: price is empty';
	die();
}

if ($order_data->selected_payment_method_type !== 'beeline.kg')
{
	echo 'ERROR: payment type is not valid';
	die();
}

$beeline_server = $order_data->beeline_server;
$user_phone = trim($order_data->phone_number, '+');

$auth_token = Post([
	'merchant' => $order_data->merchant,
	'password' => $order_data->password,
	'service_id' => $order_data->service_id
], 'auth', 'auth_token');

if (!$auth_token)
	die();

$payment_token = Post([
	'auth_token' => $auth_token,
	'merchant' => $order_data->merchant,
	'service_id' => $order_data->service_id,
	'amount' => $order_data->price,
	'requisite' => $order_data->requisite,
	'transaction_id' => $order_data->id
], 'request-token', 'payment_token');

if (!$payment_token)
	die();

$payment = Post([
	'payment_token' => $payment_token,
	'payer' => $user_phone,
	'merchant' => $order_data->merchant,
	'amount' => $order_data->price,
	'requisite' => $order_data->requisite,
	'comment' => 'Оплата поездки в такси'
], 'payment');

//Save token from second step
if ($payment)
{
	$dbfirm->Execute('UPDATE orders SET selected_payment_card_token = ? WHERE id = ?',[$payment_token.'$$'.$auth_token, $order_id]);

	$dbfirm->Execute('UPDATE
		driver
	SET
		text_dialoga = \'Ожидайте подтверждения оплаты клиентом в течении трех минут\',
		button_dialoga = \'OK:ok;sound:1\',
		time_dialoga = \'60000:ok\'
	WHERE
		id = (SELECT id_driver FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?))', $order_id);

	echo "\nPlace payment request\n";

	sleep(180);

	$check_order_pay_res = $dbfirm->GetRow('SELECT selected_payment_method_res AS val FROM orders WHERE id = ?', $order_id)['val'];

	echo "\ncheck_order_pay_res: $check_order_pay_res\n";

	if ($check_order_pay_res === null)
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Таймаут времени для подтверждения платежного поручения', false]);
} else {
	$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Пользователь не зарегистрирован в balance.kg', false]);
}

function Post($params = [], $url, $return = false)
{
	global $beeline_server;

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => "$beeline_server/site-api/acquiring/$url",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		// CURLOPT_POSTFIELDS => urldecode(http_build_query($params)),
		CURLOPT_POSTFIELDS => http_build_query($params),
		CURLOPT_HTTPHEADER => array(
			'Cache-Control: no-cache',
			'Content-Type: application/x-www-form-urlencoded'
		),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

	curl_close($curl);

	echo "Http code: $httpcode\n";
	echo "URL: $url\n";
	echo "Return: $return\n";
	echo "Params:";
	print_r($params);
	echo "Response:";
	echo $response."\n\n\n";

	if ($httpcode != 200)
		return false;

	if ($err) {
		echo "cURL Error #:" . $err;
		return false;
	} else {
		$json = json_decode($response);

		if ($return)
		{
			return $json->details->{$return};
		} else {
			return true;
		}
	}
}