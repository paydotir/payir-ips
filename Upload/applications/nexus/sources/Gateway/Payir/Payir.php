<?php

namespace IPS\nexus\Gateway;


if (!defined('\IPS\SUITE_UNIQUE_KEY')) {

	header((isset( $_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . '403 Forbidden');
	exit;
}

class _Payir extends \IPS\nexus\Gateway
{
	const PAYIR_SEND_URL  = 'https://pay.ir/payment/send';
	const PAYIR_GATE_URL  = 'https://pay.ir/payment/gateway/';
	const PAYIR_CHECK_URL = 'https://pay.ir/payment/verify';

	public function checkValidity(\IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress)
	{
		if ($amount->currency != 'IRR' && $amount->currency != 'IRT') {

			return FALSE;
		}

		return parent::checkValidity($amount, $billingAddress);
	}

	public function auth(\IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL)
	{
		$transaction->save();

		$amount_value = round((string) $transaction->amount->amount);

		if ($transaction->currency == 'IRT') {

			$amount_value = $amount_value * 10;
		}
		
		$data = array(

			'amount'       => $amount_value,
			'redirect'     => urlencode((string) \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/payir.php?nexusTransactionId=' . $transaction->id),
			'factorNumber' => $transaction->id
		);

		$result = $this->common($data);

		if ($result && isset($result->status) && $result->status == 1) {

			$_SESSION['factorNumber'] = $transaction->id;
			$_SESSION['transId']      = $result->transId;

			\IPS\Output::i()->redirect(\IPS\Http\Url::external(self::PAYIR_GATE_URL . $result->transId));
		}

		throw new \RuntimeException;
	}

	public function capture(\IPS\nexus\Transaction $transaction) {

	}

	public function settings(&$form)
	{
		$settings = json_decode($this->settings, TRUE);

		$form->add(new \IPS\Helpers\Form\Text('payir_api', $this->id ? $settings['api'] : NULL, TRUE));
	}

	public function testSettings($settings)
	{
		return $settings;
	}

	public function common($data, $verify = FALSE)
	{
		$data['api'] = json_decode($this->settings)->api;

		return json_decode(\IPS\Http\Url::external($verify ? self::PAYIR_CHECK_URL : self::PAYIR_SEND_URL)->request()->post($data));
	}
}
