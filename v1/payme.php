<?php
// php payme.php
include_once __DIR__ . '/../../../classes/db/dbconfig.php';

$getopt = getopt("o:h::c::");
$order_id = (!empty($getopt['o'])) ? $getopt['o'] : '';
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
		get_opt_s(\'payment_url\') AS pay_url,
		get_opt_s(\'terminal_key\') AS public_key,
		get_opt_s(\'secret_key\') AS private_key,
		get_opt_s(\'ckassa_commission\') AS ckassa_commission
	FROM
		orders o, slugbi s
	WHERE o.id = ? AND o.slugba = s.name
', $order_id);

if ((empty($order_data->price)) || (empty($order_data->calc_price)))
{
	echo 'ERROR: price is empty';
	die();
}

if ($order_data->selected_payment_method_type !== 'payme')
{
	echo 'ERROR: payment type is not valid';
	die();
}

if ($order_data->selected_payment_method_res !== null)
{
	echo 'ERROR: payment result is already there';
	die();
}

$domain_name = $order_data->pay_url;
$PayId = $order_data->public_key; 
$PayKey = $order_data->private_key;
$data = [
	"id"=>0,
	"method" =>"",
	"params" => [],
];

if ($cancel)
{
	$recept_id = $dbfirm->GetRow('SELECT payment_method_ext_idv FROM orders WHERE id = ?', [$order_id])['id'];
	$data["id"] = $order_id;
	$data["method"] = "receipts.cancel";
	$data["params"]["id"] = $recept_id;
	$res = getPayMe($domain_name, $data, $PayId.":".$PayKey);
	if (!empty($res["result"])){
		echo 'SUCCESS cancel recept : OK'; 
	}else{
		echo 'ERROR: PayMe error cancel recept in receipts.cancel: '.json_encode($res);
	};

}else{
	$commission = (float) $order_data->ckassa_commission;
	$price_in_bd = (float) $order_data->calc_price;
	$amount = ($price_in_bd + (($price_in_bd/100)*$commission))*100;
	$data["id"] = $order_id;
	$data["method"] = "receipts.create";
	$data["params"]["amount"] = $amount;
	$data["params"]["account"]["order_id"] = $order_data->phone_number;
	//$data["params"]["detail"]["items"] =[["title"=>"TAXI","price"=>$order_data->calc_price]];
	//создаем чек на оплату
	$res = getPayMe($domain_name, $data, $PayId.":".$PayKey);
		if (!empty($res["result"]))
		{
			$payment_method_ext_idv = $res["result"]["receipt"]["_id"];
			$dbfirm->Execute('UPDATE orders SET holded_price = ?, payment_method_ext_idv = ? WHERE id = ?', [$order_data->calc_price, $payment_method_ext_idv ,$order_id]);
			//чек создан! Далее списание
			
			$data["id"] = $order_id;
			$data["method"] = "receipts.pay";
			$data["params"]["id"] = $payment_method_ext_idv;
			$data["params"]["token"] =  $order_data->selected_payment_card_token;
			$res = getPayMe($domain_name, $data, $PayId.":".$PayKey);

			if (!empty($res['result']))
			{
				//списание прошло успешно. Оповещаем сообщением клиента
				$data["id"] = $order_id;
				$data["method"] = "receipts.send";
				$data["params"]["id"] = $payment_method_ext_idv;
				$data["params"]["phone"] = $order_data->phone_number;

				$resMsg = getPayMe($domain_name, $data, $PayId.":".$PayKey);
				if(!empty($resMsg['result']) && $resMsg['result']["success"]){
					$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата картой прошла успешно', true]);
					echo 'SUCCESS: transactionStatus: Send. Massage sended';
					die();
				}else{
					$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Оплата картой прошла успешно', true]);
					echo 'SUCCESS: transactionStatus: Send. Massage error send '.json_encode($resMsg);
					die();
				}
				
			}else{
				$dbfirm->Execute('SELECT payment_result(?, ?, ?)', [$order_id, 'Ошибка! '.$res['error']['message'].' Возьмите наличные', false]);
				echo 'ERROR: PayMe error in receipts.pay: '.json_encode($res);
				die();
			}
		
		} else {
			$dbfirm->Execute('SELECT orders_cancel(?, \'API\', ?)', [$order_id, 'Ошибка взаимодействия с платежным шлюзом, укажите другой способ оплаты']);
			echo 'ERROR: PayMe error in receipts.create: '.json_encode($res);
			//TODO: create local curl post for send this message to alert show in socket
		}
}

function getPayMe($url,$dataSend, $Auth){
	$headers[] = 'X-Auth: '.$Auth;
	$data_string = json_encode ($dataSend, JSON_UNESCAPED_UNICODE);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);                                //устанавливаем адрес
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                        //Вывод в строку вместо прямого вывода в браузер
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                        //Игнорируем верификацию сертификата
	curl_setopt($ch, CURLOPT_POST, 1);                                  //Указываем, что у нас POST запрос
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                 //Добавляем переменные
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);                     //Добавляем массив заголовков
	$output = curl_exec($ch);                                           //Присваиваем ответ переменной
	curl_close($ch);
	if ($output === FALSE) {                                            //Проверяем что не ошибка
		// Если произошла ошибка! 
		return 'Error: ' . curl_error($ch);
	}else{
		return json_decode($output, true);                              //строку JSON в ассоциативный масиив
	};
}