<?php
// php uniteller.php -o34962
include_once __DIR__ . '/../../../classes/db/dbconfig.php';

$getopt = getopt("o:");
$order_id = (!empty($getopt['o'])) ? $getopt['o'] : '';

if (empty($order_id))
	die('ERROR: order id is empty');

$dbfirm = new \DBfirm();
if (!$dbfirm->checkConnection())
	die('ERROR: connecting to the DB');

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
		o.selected_payment_method_type,
		o.selected_payment_card_token,
		get_opt_s(\'terminal_key\') AS public_key,
		get_opt_s(\'secret_key\') AS private_key
	FROM
		orders o, slugbi s
	WHERE o.id = ? AND o.slugba = s.name
', $order_id);

if (empty($order_data->price))
	die('ERROR: price is empty');

if ($order_data->selected_payment_method_type !== 'uniteller')
	die('ERROR: payment type is not valid');

$check_order_pay_res = $dbfirm->GetRow('SELECT selected_payment_method_res AS val FROM orders WHERE id = ?', $order_id)['val'];

//Notify driver about payment status
if ($check_order_pay_res === null)
	$dbfirm->Execute('UPDATE
		driver
	SET
		text_dialoga = \'Оплата картой, средства будут зачислены на баланс, ожидайте подтверждения успешной оплаты\',
		button_dialoga = \'OK:ok;sound:1\',
		time_dialoga = \'60000:ok\'
	WHERE
		id = (SELECT id_driver FROM bort WHERE id = (SELECT id_bort FROM orders WHERE id = ?))', $order_id);

$key = trim(base64_encode(openssl_encrypt($order_data->phone_number.' '.date("Y-m-d H:i:s").' '.$order_data->firm_id.' '.$order_data->service.' uniteller', 'AES-128-ECB', 'yabteb9vzlomal')), '=');

$orderReference = trim(base64_encode(openssl_encrypt($order_data->id, 'AES-128-ECB', 'yabteb9vzlomal')), '=');

$params = [
	'Shop_IDP' => $order_data->public_key,
	'Order_IDP' => $orderReference,
	'Subtotal_P' => $order_data->price,
	'Parent_Order_IDP' => $order_data->selected_payment_card_token
];

$curl = curl_init();

curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://wpay.uniteller.ru/recurrent/',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_POSTFIELDS => http_build_query($params),
	CURLOPT_HTTPHEADER => array(
		'Cache-Control: no-cache',
		'Content-Type: application/x-www-form-urlencoded'
	),
));

$res = curl_exec($curl);
$err = curl_error($curl);
$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

print_r($res);

$res = csv_to_array($res, ';');

$check_order_pay_res = $dbfirm->GetRow('SELECT selected_payment_method_res AS val FROM orders WHERE id = ?', $order_id)['val'];

if (count($res) == 0 && $check_order_pay_res === null)
	$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Возьмите наличные. Нет доступа к списанию денежных средств с клиента', false]);

$success = $res[0]['Success'];
$ordernumber = $res[0]['OrderNumber'];
$response_code = $res[0]['Response_Code'];
$message = $res[0]['Message'];
$total = $res[0]['Total'];

$signature = strtoupper(md5($ordernumber.$total.$order_data->private_key));

if ($signature != $res[0]['Signature'])
{
	$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Возьмите наличные. Неверная подпись запроса', false]);
	die('ERROR: answer signature is not valid');
}

if ($success && $check_order_pay_res === null)
	$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата прошла успешно', true]);

function csv_to_array($filename_or_string = '', $delimiter = ',')
{
	$header = NULL;
	$data = [];

	if(!file_exists($filename_or_string) || !is_readable($filename_or_string))
	{
		//String
		foreach (explode("\n", $filename_or_string) as $str)
		{
			$row = explode($delimiter, $str);

			if (!$header)
				$header = $row;
			else
				$data[] = array_combine($header, $row);
		}
	} else {
		//File
		if (($handle = fopen($filename_or_string, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				if (!$header)
					$header = $row;
				else
					$data[] = array_combine($header, $row);
			}
			fclose($handle);
		}
	}

	return $data;
}