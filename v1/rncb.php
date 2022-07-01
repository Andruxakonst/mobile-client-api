<?php
// php rncb.php -o34962
include_once __DIR__ . '/../../../classes/db/dbconfig.php';

$getopt = getopt("o:h::c::");
$order_id = (!empty($getopt['o'])) ? $getopt['o'] : '';
$hold = (!empty($getopt['h']))?true:null;
$cancel = (!empty($getopt['c']))?true:null;


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
		extract(epoch from coalesce(o.data_end, now()))::integer AS order_date,
		get_opt_s(\'id_firm\') AS firm_id,
		coalesce(s.id, 0) AS service,
		get_opt_s(\'currency\') AS currency,
		get_opt_s(\'currency_unicode\') AS currency_unicode,
		o.phone_number,
		c.ip_auth AS client_ip,
		CASE WHEN o.price_vruchnuu IS NOT NULL THEN o.price_vruchnuu ELSE o.price_local_taxometr END AS price,
		calc_price_itogo_full2(o.id) AS calc_price,
		o.api_id_order AS transaction_id,
		o.holded_price,
		o.holded_price_res,
		o.selected_payment_method_type,
		o.selected_payment_method_res,
		o.selected_payment_card_token,
		get_opt_s(\'terminal_key\') AS public_key,
		get_opt_s(\'secret_key\') AS private_key
	FROM
		orders o, slugbi s, client c
	WHERE o.id = ? AND o.slugba = s.name AND c.phone_number = o.phone_number
', $order_id);

if ((!$hold && empty($order_data->price)) || ($hold && empty($order_data->calc_price)))
{
	echo 'ERROR: price is empty';
	die();
}

if ($order_data->selected_payment_method_type !== 'rncb')
{
	echo 'ERROR: payment type is not valid';
	die();
}

if ($order_data->selected_payment_method_res !== null)
{
	echo 'ERROR: payment result is already there';
	die();
}

if ($order_data->selected_payment_card_token === null || $order_data->selected_payment_card_token === '')
{
	echo 'ERROR: card token is not valid';
	die();
}

if (mb_substr($order_data->selected_payment_card_token, 0, 5) === 'gpay:')
{
	$order_data->selected_payment_card_token = mb_substr($order_data->selected_payment_card_token, 5);
}

// $order_data->selected_payment_card_token = str_replace('\"', '"', $order_data->selected_payment_card_token);
// $order_data->selected_payment_card_token = str_replace('\\\\u', '\\u', $order_data->selected_payment_card_token);

// print_r($order_data);
// die();

//Most likely not needed
$key = trim(base64_encode(openssl_encrypt($order_data->phone_number.' '.date("Y-m-d H:i:s").' '.$order_data->firm_id.' '.$order_data->service.' rncb', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

$accountId = trim(base64_encode(openssl_encrypt($order_data->phone_number.' '.$order_data->firm_id.' '.$order_data->service.' rncb', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

//Most likely not needed
$domain_name = $dbfirm->GetRow('SELECT get_opt_s(\'name_firm\') AS val')['val'];

$invoiceId = trim(base64_encode(openssl_encrypt($order_data->id, 'AES-128-ECB', 'yabteb9vzlomal')), '=');

if ($hold && !$cancel)
{
	// $res = Post('/tokens/auth', [
	// 	'Amount' => $order_data->calc_price,
	// 	'Currency' => 'RUB',
	// 	'AccountId' => $accountId,
	// 	'Token' => $order_data->selected_payment_card_token,
	// 	'InvoiceId' => $invoiceId,
	// 	'Description' => 'Оплата за поездку в такси'
	// ], true);
	$res = Post('/cards/auth', [
		'Amount' => $order_data->calc_price,
		'Currency' => 'RUB',
		//'IpAddress' => $order_data->client_ip,
		'AccountId' => $accountId,
		'CardCryptogramPacket' => $order_data->selected_payment_card_token,
		'InvoiceId' => $invoiceId,
		'Description' => 'Оплата за поездку в такси'
	], true);

	print_r(['res_in_hold' => $res]);

	if ($res->Success)
	{
		$dbfirm->Execute('UPDATE orders SET holded_price = ?, holded_price_res = TRUE, api_id_order = ? WHERE id = ?', [$order_data->calc_price, $res->Model->TransactionId, $order_id]);
		//$res->Model->TransactionId //Номер транзакции в системе нужен для подтверждения списания
	} else if (!$res->Success && $res->Message) {
		$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order_id, $res->Message]);
	} else {
		$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order_id, 'Ошибка взаимодействия с платежным шлюзом, укажите другой способ оплаты']);
		//TODO: create local curl post for send this message to alert show in socket
	}
}

if ($hold && $cancel)
{
	Post('/void', [
		'TransactionId' => $order_data->transaction_id
	], true);
}

if (!$hold && !$cancel)
{
	if (!$order_data->holded_price_res || !$order_data->transaction_id)
	{
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Ошибка взаимодействия с платежным шлюзом, возьмите наличные', false]);
		echo 'ERROR: no info about success hold';
		die();
	}

	//For check card token data in orders

	//select id, phone_number, selected_payment_method_type, selected_payment_card_token, event_end from orders where selected_payment_card_token is not null and selected_payment_card_token <> '' order by id desc limit 5

	//select id, phone_number, selected_payment_method_type, selected_payment_card_token, event_end from orders where selected_payment_method_type is not null order by id desc limit 20

	$price_for_sale = $order_data->holded_price;
	$add_price_for_sale = 0;

	if ($order_data->holded_price > $order_data->price)
		$price_for_sale = $order_data->price;

	if ($order_data->holded_price < $order_data->price)
		$add_price_for_sale = $order_data->price-$order_data->holded_price;

	$res = Post('/confirm', [
		'TransactionId' => $order_data->transaction_id,
		'Amount' => $price_for_sale
	], true);

	if (!$res->Success)
	{
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Ошибка: не удалось списать замороженные средства ('.$res->Model->CardHolderMessage.'), возьмите наличные', false]);
		echo 'ERROR: confirm: '.$res->Message."\n".'CardHolderMessage: '.$res->Model->CardHolderMessage;
		die();
	}

	if ($add_price_for_sale > 0)
	{
		$invoiceIdAdd = trim(base64_encode(openssl_encrypt($order_data->id.'_add', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

		// $res = Post('/tokens/charge', [
		// 	'Amount' => $add_price_for_sale,
		// 	'Currency' => 'RUB',
		// 	'AccountId' => $accountId,
		// 	'Token' => $order_data->selected_payment_card_token,
		// 	'InvoiceId' => $invoiceIdAdd,
		// 	'Description' => 'Доплата за поездку в такси'
		// ], true);
		$res = Post('/cards/charge', [
			'Amount' => $add_price_for_sale,
			'Currency' => 'RUB',
			// 'IpAddress' => $order_data->client_ip,
			'AccountId' => $accountId,
			'CardCryptogramPacket' => $order_data->selected_payment_card_token,
			'InvoiceId' => $invoiceIdAdd,
			'Description' => 'Доплата за поездку в такси'
		], true);

		//TODODO
		if ($res->Success)
		{
			$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата картой прошла успешно', true]);
			echo 'SUCCESS: confirm with additional charge, add price is: '.$add_price_for_sale;
			die();
		} else if (!$res->Success && $res->Message) {
			$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Частичная оплата картой, ВОЗЬМИТЕ НАЛИЧНЫМИ '.$add_price_for_sale.'руб. Ошибка досписания средств: '.$res->Message, false]);
		} else {
			$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Частичная оплата картой, ВОЗЬМИТЕ НАЛИЧНЫМИ '.$add_price_for_sale.'руб. Ошибка взаимодействия с платежным шлюзом', false]);
			//TODO: create local curl post for send this message to alert show in socket
		}
	} else {
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата картой прошла успешно', true]);
		echo 'SUCCESS: confirm';
		die();
	}
}

function Post($urlPath = '', $params = [], $return = false)
{
	global $order_data;

	if ($urlPath === '') {
		echo "cURL Error: urlPath doesn't presents";
		return false;
	}

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://api.cloudpayments.ru/payments'.$urlPath,
		CURLOPT_USERPWD => $order_data->public_key.':'.$order_data->private_key,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => json_encode($params),
		CURLOPT_HTTPHEADER => array(
			'Cache-Control: no-cache',
			'Content-Type: application/json'
			// 'Content-Type: application/x-www-form-urlencoded'
		),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);
	$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

	curl_close($curl);

	echo "Http code: $httpcode\n";
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
			return $json;
		} else {
			return true;
		}
	}
}