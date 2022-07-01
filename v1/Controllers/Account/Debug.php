<?php

namespace Uptaxi\Controllers\Account;

use Uptaxi\Controllers\MainController;

class Debug extends MainController
{
	public function main()
	{
		$user_auth = parent::cau();

		if (!isset($user_auth->login))
			return $user_auth;

		return parent::success(true);
	}
}