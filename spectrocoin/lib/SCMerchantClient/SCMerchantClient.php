<?php

/**
 * Created by UAB Spectro Fincance.
 * This is a sample SpectroCoin Merchant v1.1 API PHP client
 */
 
include_once('Httpful/Bootstrap.php');
include_once('Httpful/Handlers/FormHandler.php');
include_once('Httpful/Response/Headers.php');
include_once('Httpful/Response.php');
include_once('Httpful/Httpful.php');
include_once('Httpful/Handlers/CsvHandler.php');
include_once('Httpful/Handlers/XmlHandler.php');
include_once('Httpful/Exception/JsonParseException.php');
include_once('Httpful/Exception/ConnectionErrorException.php');
include_once('Httpful/Handlers/JsonHandler.php');
include_once('Httpful/Http.php');
include_once('Httpful/Mime.php');
include_once('Httpful/Request.php');


include_once('components/FormattingUtil.php');
include_once('data/ApiError.php');
include_once('data/OrderStatusEnum.php');
include_once('data/OrderCallback.php');
include_once('messages/CreateOrderRequest.php');
include_once('messages/CreateOrderResponse.php');


class SCMerchantClient
{

	private $merchantApiUrl;
	private $privateMerchantCertLocation;
	private $publicSpectroCoinCertLocation;
	private $userId;
	private $merchantApiId;
	private $debug;
	private $privateMerchantKey;

	/**
	 * @param $merchantApiUrl
	 * @param $userId
	 * @param $merchantApiId
	 * @param bool $debug
	 */
	function __construct($merchantApiUrl, $userId, $merchantApiId, $debug = false)
	{
		$this->privateMerchantCertLocation = dirname(__FILE__) . '/../cert/mprivate.pem';
		$this->publicSpectroCoinCertLocation = 'https://spectrocoin.com/files/merchant.public.pem';
		$this->merchantApiUrl = $merchantApiUrl;
		$this->userId = $userId;
		$this->merchantApiId = $merchantApiId;
		$this->debug = $debug;
	}
	/**
	 * @param $privateKey
	 */
	public function setPrivateMerchantKey($privateKey) {
		$this->privateMerchantKey = $privateKey;
	}
	/**
	 * @param CreateOrderRequest $request
	 * @return ApiError|CreateOrderResponse
	 */
	public function createOrder(CreateOrderRequest $request)
	{
		$payload = array(
			'userId' => $this->userId,
			'merchantApiId' => $this->merchantApiId,
			'orderId' => $request->getOrderId(),
			'payCurrency' => $request->getPayCurrency(),
			'payAmount' => $request->getPayAmount(),
			'receiveCurrency' => $request->getReceiveCurrency(),
			'receiveAmount' => $request->getReceiveAmount(),
			'description' => $request->getDescription(),
			'culture' => $request->getCulture(),
			'callbackUrl' => $request->getCallbackUrl(),
			'successUrl' => $request->getSuccessUrl(),
			'failureUrl' => $request->getFailureUrl()
		);

		$formHandler = new \Httpful\Handlers\FormHandler();
		$data = $formHandler->serialize($payload);
		$signature = $this->generateSignature($data);
		$payload['sign'] = $signature;
		if (!$this->debug) {
			$response = \Httpful\Request::post($this->merchantApiUrl . '/createOrder', $payload, \Httpful\Mime::FORM)->expects(\Httpful\Mime::JSON)->send();
			if ($response != null) {
				$body = $response->body;
				if ($body != null) {
					if (is_array($body) && count($body) > 0 && isset($body[0]->code)) {
						return new ApiError($body[0]->code, $body[0]->message);
					} else {
						return new CreateOrderResponse($body->orderRequestId, $body->orderId, $body->depositAddress, $body->payAmount, $body->payCurrency, $body->receiveAmount, $body->receiveCurrency, $body->validUntil, $body->redirectUrl);
					}
				}
			}
		} else {
			$response = \Httpful\Request::post($this->merchantApiUrl . '/createOrder', $payload, \Httpful\Mime::FORM)->send();
			exit('<pre>'.print_r($response, true).'</pre>');
		}
	}

	private function generateSignature($data)
	{
		// fetch private key from file and ready it
		$privateKey = $this->privateMerchantKey != null ? $this->privateMerchantKey : file_get_contents($this->privateMerchantCertLocation);
		//$privateKey = $this->privateMerchantKey != null;
		$pkeyid = openssl_pkey_get_private($privateKey);

		// compute signature
		$s = openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA1);
		$encodedSignature = base64_encode($signature);
		// free the key from memory
		openssl_free_key($pkeyid);

		return $encodedSignature;
	}

	/**
	 * @param $r $_REQUEST
	 * @return OrderCallback|null
	 */
	public function parseCreateOrderCallback($r)
	{
		$result = null;

		if ($r != null && isset($r['userId'], $r['merchantApiId'], $r['merchantId'], $r['apiId'], $r['orderId'], $r['payCurrency'], $r['payAmount'], $r['receiveCurrency'], $r['receiveAmount'], $r['receivedAmount'], $r['description'], $r['orderRequestId'], $r['status'], $r['sign'])) {
			$result = new OrderCallback($r['userId'], $r['merchantApiId'], $r['merchantId'], $r['apiId'], $r['orderId'], $r['payCurrency'], $r['payAmount'], $r['receiveCurrency'], $r['receiveAmount'], $r['receivedAmount'], $r['description'], $r['orderRequestId'], $r['status'], $r['sign']);
		}

		return $result;
	}

	/**
	 * @param OrderCallback $c
	 * @return bool
	 */
	public function validateCreateOrderCallback(OrderCallback $c)
	{
		$valid = false;

		if ($c != null) {

			if ($this->userId != $c->getUserId() || $this->merchantApiId != $c->getMerchantApiId())
				return $valid;

			if (!$c->validate())
				return $valid;

			$payload = array(
				'merchantId' => $c->getMerchantId(),
				'apiId' => $c->getApiId(),
				'orderId' => $c->getOrderId(),
				'payCurrency' => $c->getPayCurrency(),
				'payAmount' => $c->getPayAmount(),
				'receiveCurrency' => $c->getReceiveCurrency(),
				'receiveAmount' => $c->getReceiveAmount(),
				'receivedAmount' => $c->getReceivedAmount(),
				'description' => $c->getDescription(),
				'orderRequestId' => $c->getOrderRequestId(),
				'status' => $c->getStatus(),
			);

			$formHandler = new \Httpful\Handlers\FormHandler();
			$data = $formHandler->serialize($payload);
			$valid = $this->validateSignature($data, $c->getSign());
		}

		return $valid;
	}

	/**
	 * @param $data
	 * @param $signature
	 * @return int
	 */
	private function validateSignature($data, $signature)
	{
		$sig = base64_decode($signature);
		$publicKey = file_get_contents($this->publicSpectroCoinCertLocation);
		$public_key_pem = openssl_pkey_get_public($publicKey);
		$r = openssl_verify($data, $sig, $public_key_pem, OPENSSL_ALGO_SHA1);
		openssl_free_key($public_key_pem);

		return $r;
	}

}