<?php

namespace Uptaxi\Controllers\Services;

use Uptaxi\Controllers\MainController;
use Uptaxi\Classes\Language;

class TermsOfUse extends MainController
{
	public function Main()
	{
		$id_firm = (int)$_POST['id_firm'] ?? null;

		if (empty($id_firm))
			return parent::errCli('id_firm is empty or not valid', -4);

		$dbfirm = new \DBfirm($id_firm);
		if (!$dbfirm->checkConnection())
			return parent::errSer('Error connecting to the DB', -3);

		$terms_of_use = $dbfirm->GetRow('SELECT body FROM news WHERE razdel = \'offerte\' AND end_ is not TRUE LIMIT 1')['body'];

		if (empty($terms_of_use))
			return parent::success(file_get_contents('http://uptaxi.ru/oferta_all.php'));

		return parent::success($terms_of_use);
	}
}