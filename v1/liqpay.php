<?php
// php liqpay.php -o34962
include_once __DIR__ . '/../../../classes/db/dbconfig.php';
include_once __DIR__ . '/Classes/LiqPay.php';

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
		get_opt_s(\'id_firm\') AS firm_id,
		coalesce(s.id, 0) AS service,
		get_opt_s(\'currency\') AS currency,
		get_opt_s(\'currency_unicode\') AS currency_unicode,
		o.phone_number,
		CASE WHEN o.price_vruchnuu IS NOT NULL THEN o.price_vruchnuu ELSE o.price_local_taxometr END AS price,
		o.selected_payment_method_type,
		o.selected_payment_card_token,
		get_opt_s(\'terminal_key\') AS public_key,
		get_opt_s(\'secret_key\') AS private_key
	FROM
		orders o, slugbi s
	WHERE o.id = ? AND o.slugba = s.name
', $order_id);

if (empty($order_data->price))
{
	echo 'ERROR: price is empty';
	die();
}

if ($order_data->selected_payment_method_type !== 'liqpay')
{
	echo 'ERROR: payment type is not valid';
	die();
}

$key = trim(base64_encode(openssl_encrypt($order_data->phone_number.' '.date("Y-m-d H:i:s").' '.$order_data->firm_id.' '.$order_data->service.' liqpay', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

$liqpay = new Uptaxi\Classes\LiqPay($order_data->public_key, $order_data->private_key);
$res = $liqpay->api("request", array(
	'action'         => 'paytoken',
	'version'        => '3',
	'phone'          => trim($order_data->phone_number, '+'),
	'amount'         => $order_data->price,
	'currency'       => $order_data->currency,
	'description'    => 'За поездку в такси '.$order_data->price.$order_data->currency_unicode,
	'order_id'       => trim(base64_encode(openssl_encrypt($order_data->id, 'AES-128-ECB', 'yabteb9vzlomal')), '='),
	'card_token'     => $order_data->selected_payment_card_token,
	'server_url'     => 'https://kvs.uptaxi.ru/apim/v1/ru/payment/apply?key='.$key
));

print_r($res);

if ($res->status == 'error')
{
	$check_order_pay_res = $dbfirm->GetRow('SELECT selected_payment_method_res AS val FROM orders WHERE id = ?', $order_id)['val'];

	if ($res->err_code == 'err_access')
	{
		if ($check_order_pay_res === null)
			$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Нет доступа к списанию денежных средств с клиента. Возьмите наличные', false]);
		echo "\nНет доступа к списанию денежных средств с клиента. Возьмите наличные";
		die();
	}

	if ($check_order_pay_res === null)
		$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Произошла неизвестная ошибка в процессе списания денежных средств. Возьмите наличные', false]);
	echo "\nПроизошла неизвестная ошибка в процессе списания денежных средств. Возьмите наличные";
}