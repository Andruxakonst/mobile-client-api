<?php

use Uptaxi\Classes\Language;
$lang = new Language(explode('/',$_SERVER['REQUEST_URI'])[4]);
$lang->check();

$app->group('/{lang:[a-z]+}', function() use ($app){
	$app->options('/{routes:.+}', function ($request, $response, $args) {
		return $response
			->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Auth')
			->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
			->withHeader('Access-Control-Max-Age', 2592000);
	});
	$app->post('/services/get/available[/]', function($request, $response, $arg) {
		$class = new \Uptaxi\Controllers\Services\Available($request, $response, $arg);
		return $class->main();
	});
	$app->post('/services/get/phone_mask[/]', function($request, $response, $arg) {
		$class = new \Uptaxi\Controllers\Services\PhoneMask($request, $response, $arg);
		return $class->main();
	});
	$app->post('/services/get/terms_of_use[/]', function($request, $response, $arg) {
		$class = new \Uptaxi\Controllers\Services\TermsOfUse($request, $response, $arg);
		return $class->main();
	});
	$app->post('/services/init_service[/]', function($request, $response, $arg) {
		$class = new \Uptaxi\Controllers\Services\Available($request, $response, $arg);
		return $class->init_service();
	});
	$app->post('/services/login_via_other_firm[/]', function($request, $response, $arg) {
		$class = new \Uptaxi\Controllers\Services\LoginViaOtherFirm($request, $response, $arg);
		return $class->main();
	});
	$app->post('/account/auth[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Auth($request, $response, $arg);
		return $class->main();
	});
	$app->post('/account/auth_via_bot[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Auth($request, $response, $arg);
		return $class->via_bot();
	});
	$app->post('/account/debug[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Debug($request, $response, $arg);
		return $class->main();
	});
	$app->post('/account/getpass/sms[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->pass('sms');
	});
	$app->post('/account/getpass/call[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->pass('call');
	});
	$app->post('/account/get/user_data[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->user_data();
	});
	$app->post('/account/get/driver_who_invited[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->driver_who_invited();
	});
	$app->post('/account/get/promo_actions_friend_list[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->promo_actions_friend_list();
	});
	$app->post('/account/get/promo_actions[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->promo_actions();
	});
	$app->post('/account/get/promocode[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->promocode();
	});
	$app->post('/account/edit/promocode[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->promocode_edit();
	});
	$app->post('/account/get/promodesc[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->promodesc();
	});
	$app->post('/account/get/not_ring[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Get($request, $response, $arg);
		return $class->not_ring();
	});
	$app->post('/account/set/device_token[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->device_token();
	});
	$app->post('/account/set/device_info[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->device_info();
	});
	$app->post('/account/set/driver_who_invited[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->driver_who_invited();
	});
	$app->post('/account/set/user_name[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->user_name();
	});
	$app->post('/account/set/user_email[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->user_email();
	});
	$app->post('/account/set/promocode[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->promocode();
	});
	$app->post('/account/set/pcode[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->pcode();
	});
	$app->post('/account/set/not_ring[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Account\Set($request, $response, $arg);
		return $class->not_ring();
	});
	$app->post('/banners/set/touched[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Banners\Set($request, $response, $arg);
		return $class->touched();
	});
	$app->post('/map/set/favorite_address[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\FavoriteAddress($request, $response, $arg);
		return $class->set();
	});
	$app->post('/map/get/favorite_address[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\FavoriteAddress($request, $response, $arg);
		return $class->get();
	});
	$app->post('/map/get/route[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\Route($request, $response, $arg);
		return $class->get();
	});
	$app->post('/map/update/favorite_address[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\FavoriteAddress($request, $response, $arg);
		return $class->update();
	});
	$app->post('/map/del/favorite_address[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\FavoriteAddress($request, $response, $arg);
		return $class->del();
	});
	$app->post('/map/searchaddress/get_city[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\SearchAddress($request, $response, $arg);
		return $class->get_city();
	});
	$app->post('/map/searchaddress/one_row[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\SearchAddress($request, $response, $arg);
		return $class->one_row();
	});
	$app->post('/map/searchaddress/multi_one_row[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\SearchAddress($request, $response, $arg);
		return $class->multi_one_row();
	});
	$app->post('/map/searchaddress/street[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\SearchAddress($request, $response, $arg);
		return $class->street();
	});
	$app->post('/map/searchaddress/house[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\SearchAddress($request, $response, $arg);
		return $class->house();
	});
	$app->post('/map/get/address_history[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\AddressHistory($request, $response, $arg);
		return $class->main();
	});
	$app->post('/map/get/address_by_coords[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\AddressByCoords($request, $response, $arg);
		return $class->main();
	});
	$app->post('/map/show_drivers[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Map\ShowDrivers($request, $response, $arg);
		return $class->main();
	});
	$app->post('/order/calc_price[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\CalcCreate($request, $response, $arg);
		return $class->main();
	});
	$app->post('/order/create[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\CalcCreate($request, $response, $arg);
		return $class->main('create');
	});
	$app->post('/order/cancel[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Cancel($request, $response, $arg);
		return $class->main();
	});
	$app->post('/order/get/options[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->options();
	});
	$app->post('/order/get/car_classes[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->car_classes();
	});
	$app->post('/order/get/bonuses[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->bonuses();
	});
	$app->post('/order/get/bonus_history[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->bonus_history();
	});
	$app->post('/order/get/current[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->current();
	});
	$app->post('/order/get/taximeter[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->taximeter();
	});
	$app->post('/order/get/problem_list[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->problem_list();
	});
	$app->post('/order/get/route[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->route();
	});
	$app->post('/order/get/entrance[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->entrance();
	});
	$app->post('/order/get/new_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->new_driver();
	});
	$app->post('/order/get/rating_reason[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Get($request, $response, $arg);
		return $class->rating_reason();
	});
	$app->post('/order/send/bonus[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Set($request, $response, $arg);
		return $class->send_bonus();
	});
	$app->post('/order/set/add_price[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Set($request, $response, $arg);
		return $class->add_price();
	});
	$app->post('/order/set/bonus[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Set($request, $response, $arg);
		return $class->bonus();
	});
	$app->post('/order/set/hybrid[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\Set($request, $response, $arg);
		return $class->hybrid();
	});
	$app->post('/black_list/add[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\BlackList\Main($request, $response, $arg);
		return $class->add();
	});
	$app->post('/black_list/drivers[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\BlackList\Main($request, $response, $arg);
		return $class->drivers();
	});
	$app->post('/black_list/unblock[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\BlackList\Main($request, $response, $arg);
		return $class->unblock();
	});
	$app->post('/black_list/unblock_all[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\BlackList\Main($request, $response, $arg);
		return $class->unblock_all();
	});
	$app->post('/favorite_list/add[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\FavoriteList\Main($request, $response, $arg);
		return $class->add();
	});
	$app->post('/favorite_list/drivers[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\FavoriteList\Main($request, $response, $arg);
		return $class->drivers();
	});
	$app->post('/favorite_list/remove[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\FavoriteList\Main($request, $response, $arg);
		return $class->remove();
	});
	$app->post('/favorite_list/remove_all[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\FavoriteList\Main($request, $response, $arg);
		return $class->remove_all();
	});
	$app->post('/history/get/last_rides[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->last_rides();
	});
	$app->post('/history/delete_order[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->delete_order();
	});
	$app->post('/history/block_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->blocking_driver('block');
	});
	$app->post('/history/unblock_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->blocking_driver();
	});
	$app->post('/history/favorite_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->favorite_driver('add');
	});
	$app->post('/history/unfavorite_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->favorite_driver();
	});
	$app->post('/history/rating[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\History\MainHistory($request, $response, $arg);
		return $class->rating();
	});
	$app->post('/payment/add[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Add($request, $response, $arg);
		return $class->main();
	});
	$app->post('/payment/apply[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Checkout($request, $response, $arg);
		return $class->apply();
	});
	$app->post('/payment/available[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Available($request, $response, $arg);
		return $class->main();
	});
	$app->post('/payment/confirm[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Checkout($request, $response, $arg);
		return $class->confirm();
	});
	$app->get('/payment/checkout[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Checkout($request, $response, $arg);
		return $class->main();
	});
	$app->post('/payment/checkout/pin_code[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Checkout($request, $response, $arg);
		return $class->pin_code();
	});
	$app->post('/payment/checkout/payme[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Checkout($request, $response, $arg);
		return $class->payme();
	});
	$app->post('/payment/otp[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Otp($request, $response, $arg);
		return $class->main();
	});
	$app->post('/payment/remove[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Payment\Remove($request, $response, $arg);
		return $class->main();
	});
	$app->post('/news/list[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\News\Get($request, $response, $arg);
		return $class->get_list();
	});
	$app->post('/news/item[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\News\Get($request, $response, $arg);
		return $class->get_item();
	});
	$app->post('/chat/messages[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Chat\MainChat($request, $response, $arg);
		return $class->messages();
	});
	$app->post('/chat/send[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Chat\MainChat($request, $response, $arg);
		return $class->send();
	});
	$app->post('/chat/received[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Chat\MainChat($request, $response, $arg);
		return $class->read_or_received();
	});
	$app->post('/chat/readed[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Chat\MainChat($request, $response, $arg);
		return $class->read_or_received('readed');
	});
	$app->post('/travel_guide/get/list', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\TravelGuide\Get($request, $response, $arg);
		return $class->list();
	});
	$app->post('/travel_guide/get/item', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\TravelGuide\Get($request, $response, $arg);
		return $class->item();
	});
	$app->get('/travel_guide/view', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\TravelGuide\View($request, $response, $arg);
		return $class->main();
	});
	$app->get('/travel_guide/{type}/{file}', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\TravelGuide\View($request, $response, $arg);
		return $class->assets();
	});
	$app->get('/system/get_time_to_driver[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\CalcCreate($request, $response, $arg);
		return $class->get_time_to_driver();
	});
	$app->get('/2gis/price[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\CalcCreate($request, $response, $arg);
		return $class->_2gis_price();
	});
	$app->get('/2gis/time[/]', function($request, $response, $arg) {
		$class = new Uptaxi\Controllers\Order\CalcCreate($request, $response, $arg);
		return $class->_2gis_time();
	});
});