<?php

require_once '../../../../init.php';

\IPS\Session\Front::i();

try {

	$transaction = \IPS\nexus\Transaction::load(\IPS\Request::i()->nexusTransactionId);

	if ($transaction->status !== \IPS\nexus\Transaction::STATUS_PENDING) {

		throw new \OutofRangeException;
	}

} catch (\OutOfRangeException $e) {

	\IPS\Output::i()->redirect(\IPS\Http\Url::internal('app=nexus&module=payments&controller=checkout&do=transaction&id=&t=' . \IPS\Request::i()->nexusTransactionId, 'front', 'nexus_checkout', \IPS\Settings::i()->nexus_https));
}

try {

	$result = $transaction->method->common(array('transId' => \IPS\Request::i()->transId), TRUE);

	$factorNumber = isset($_SESSION['factorNumber']) ? $_SESSION['factorNumber'] : NULL;
	$transId      = isset($_SESSION['transId']) ? $_SESSION['transId'] : NULL;

	$cardNumber = \IPS\Request::i()->cardNumber;

	if ($result && isset($result->status) && $result->status == 1 && $factorNumber == $transaction->id && \IPS\Request::i()->transId == $transId) {

		//$transaction->gw_id = \IPS\Request::i()->transId . '-' . \IPS\Request::i()->factorNumber . '-' . $cardNumber;
		$transaction->gw_id = \IPS\Request::i()->transId . '-' . $cardNumber;

		$transaction->save();
		$transaction->checkFraudRulesAndCapture(NULL);
		$transaction->sendNotification();

		\IPS\Session::i()->setMember($transaction->invoice->member);
		\IPS\Output::i()->redirect($transaction->url());
	}
	
	throw new \OutofRangeException;	

} catch (\Exception $e) {

	\IPS\Output::i()->redirect($transaction->invoice->checkoutUrl()->setQueryString(array('_step' => 'checkout_pay', 'err' => $transaction->member->language()->get('gateway_err'))));
}
