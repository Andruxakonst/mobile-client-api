<?php
// php wayforpay.php -o34962
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
		CASE WHEN o.price_vruchnuu IS NOT NULL THEN o.price_vruchnuu ELSE o.price_local_taxometr END AS price,
		calc_price_itogo_full2(o.id) AS calc_price,
		o.holded_price,
		o.holded_price_res,
		o.selected_payment_method_type,
		o.selected_payment_method_res,
		o.selected_payment_card_token,
		get_opt_s(\'terminal_key\') AS public_key,
		get_opt_s(\'secret_key\') AS private_key
	FROM
		orders o, slugbi s
	WHERE o.id = ? AND o.slugba = s.name
', $order_id);

if ((!$hold && empty($order_data->price)) || ($hold && empty($order_data->calc_price)))
{
	echo 'ERROR: price is empty';
	die();
}

if ($order_data->selected_payment_method_type !== 'wayforpay')
{
	echo 'ERROR: payment type is not valid';
	die();
}

if ($order_data->selected_payment_method_res !== null)
{
	echo 'ERROR: payment result is already there';
	die();
}

$key = trim(base64_encode(openssl_encrypt($order_data->phone_number.' '.date("Y-m-d H:i:s").' '.$order_data->firm_id.' '.$order_data->service.' wayforpay', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

$domain_name = $dbfirm->GetRow('SELECT get_opt_s(\'name_firm\') AS val')['val'];
$orderReference = trim(base64_encode(openssl_encrypt($order_data->id, 'AES-128-ECB', 'yabteb9vzlomal')), '=');

if ($hold && !$cancel)
{
	$sign_str = $order_data->public_key.';'.$domain_name.';'.$orderReference.';'.$order_data->order_date.';'.$order_data->calc_price.';'.$order_data->currency.';TAXI;1;'.$order_data->calc_price;

	$sign_str = hash_hmac('md5', $sign_str, $order_data->private_key);

	$res = Post([
		'transactionType' => 'CHARGE',
		'merchantAccount' => $order_data->public_key,
		'merchantAuthType' => 'SimpleSignature',
		'merchantDomainName' => $domain_name,
		'merchantTransactionType' => 'AUTH',
		'merchantTransactionSecureType' => 'NON3DS',
		'merchantSignature' => $sign_str,
		'apiVersion' => 1,
		'serviceUrl' => 'https://kvs.uptaxi.ru/apim/v1/ru/payment/apply?hold=true&key='.$key,
		'orderReference' => $orderReference,
		'orderDate' => $order_data->order_date,
		'amount' => $order_data->calc_price,
		'currency' => $order_data->currency,
		'recToken' => $order_data->selected_payment_card_token,
		'productName' => ['TAXI'],
		'productPrice' => [$order_data->calc_price],
		'productCount' => [1],
		'clientFirstName' => 'none',
		'clientLastName' => 'none',
		'clientEmail' => 'none',
		'clientPhone' => $order_data->phone_number,
		'clientCountry' => 'UKR'
	], true);

	if ($res)
	{
		$dbfirm->Execute('UPDATE orders SET holded_price = ? WHERE id = ?', [$order_data->calc_price, $order_id]);
	} else {
		$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order_id, 'Ошибка взаимодействия с платежным шлюзом, укажите другой способ оплаты']);
		//TODO: create local curl post for send this message to alert show in socket
	}
}

if ($hold && $cancel)
{
	$sign_str = $order_data->public_key.';'.$orderReference.';'.$order_data->holded_price.';'.$order_data->currency;

	$sign_str = hash_hmac('md5', $sign_str, $order_data->private_key);

	Post([
		'transactionType' => 'REFUND',
		'merchantAccount' => $order_data->public_key,
		'orderReference' => $orderReference,
		'amount' => $order_data->holded_price,
		'currency' => $order_data->currency,
		'comment' => 'Отмена заказа клиентом',
		'merchantSignature' => $sign_str,
		'apiVersion' => 1
	], true);
}

if (!$hold && !$cancel)
{
	if (!$order_data->holded_price_res)
	{
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Ошибка взаимодействия с платежным шлюзом, возьмите наличные', false]);
		echo 'ERROR: no info about success hold';
		die();
	}

	$price_for_sale = $order_data->holded_price;
	$add_price_for_sale = 0;

	if ($order_data->holded_price > $order_data->price)
		$price_for_sale = $order_data->price;

	if ($order_data->holded_price < $order_data->price)
		$add_price_for_sale = $order_data->price-$order_data->holded_price;

	$sign_str = $order_data->public_key.';'.$orderReference.';'.$price_for_sale.';'.$order_data->currency;

	$sign_str = hash_hmac('md5', $sign_str, $order_data->private_key);

	$res = Post([
		'transactionType' => 'SETTLE',
		'merchantAccount' => $order_data->public_key,
		'orderReference' => $orderReference,
		'amount' => $price_for_sale,
		'currency' => $order_data->currency,
		'merchantSignature' => $sign_str,
		'apiVersion' => 1
	], true);

	$sign_str = $res->merchantAccount.';'.$res->orderReference.';'.$res->transactionStatus.';'.$res->reasonCode;

	$sign_str = hash_hmac('md5', $sign_str, $order_data->private_key);

	if ($res->merchantSignature !== $sign_str)
	{
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Ошибка в подписи запроса, возьмите наличные', false]);
		echo 'ERROR: merchantSignature: '.$res->merchantSignature;
		die();
	}

	if ($res->transactionStatus !== 'Approved')
	{
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Не удалось списать замороженные средства, возьмите наличные', false]);
		echo 'ERROR: transactionStatus: '.$res->transactionStatus;
		die();
	}

	if ($add_price_for_sale > 0)
	{
		$orderReference = trim(base64_encode(openssl_encrypt($order_data->id.'_add', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

		$sign_str = $order_data->public_key.';'.$domain_name.';'.$orderReference.';'.$order_data->order_date.';'.$add_price_for_sale.';'.$order_data->currency.';TAXI;1;'.$add_price_for_sale;

		$sign_str = hash_hmac('md5', $sign_str, $order_data->private_key);

		$res = Post([
			'transactionType' => 'CHARGE',
			'merchantAccount' => $order_data->public_key,
			'merchantAuthType' => 'SimpleSignature',
			'merchantDomainName' => $domain_name,
			'merchantTransactionType' => 'SALE',
			'merchantTransactionSecureType' => 'NON3DS',
			'merchantSignature' => $sign_str,
			'apiVersion' => 1,
			'serviceUrl' => 'https://kvs.uptaxi.ru/apim/v1/ru/payment/apply?add=true&key='.$key,
			'orderReference' => $orderReference,
			'orderDate' => $order_data->order_date,
			'amount' => $add_price_for_sale,
			'currency' => $order_data->currency,
			'recToken' => $order_data->selected_payment_card_token,
			'productName' => ['TAXI'],
			'productPrice' => [$add_price_for_sale],
			'productCount' => [1],
			'clientFirstName' => 'none',
			'clientLastName' => 'none',
			'clientEmail' => 'none',
			'clientPhone' => $order_data->phone_number,
			'clientCountry' => 'UKR'
		], true);
	} else {
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата картой прошла успешно', true]);
		echo 'SUCCESS: transactionStatus: '.$res->transactionStatus;
		die();
	}
}

function Post($params = [], $return = false)
{
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://api.wayforpay.com/api',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => json_encode($params),
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