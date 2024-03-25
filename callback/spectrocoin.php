<?php
# Required File Includes
include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once '../spectrocoin/lib/SCMerchantClient/SCMerchantClient.php';

$gatewaymodule = "spectrocoin";
$GATEWAY = getGatewayVariables($gatewaymodule);

if (!$GATEWAY["type"]) {
	logTransaction($GATEWAY["name"], $_POST, 'Not activated');
	error_log('Spectrocoin module not activated');
	exit("Spectrocoin module not activated");
}

$project_id = $GATEWAY['projectId'];
$client_id = $GATEWAY['clientId'];
$client_secret = $GATEWAY['clientSecret'];
$receiveCurrency = $GATEWAY['receive_currency'];

$request = $_REQUEST;
$auth_url = "https://test.spectrocoin.com/api/public/oauth/token";
$api_url = 'https://test.spectrocoin.com/api/public';
$client = new SCMerchantClient($api_url, $project_id, $client_id, $client_secret, $auth_url);


if ($_SERVER['REQUEST_METHOD'] == 'POST')
{	
	$expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

	$post_data = [];
	foreach ($expected_keys as $key) {
		if (isset($_POST[$key])) {
			$post_data[$key] = $_POST[$key];
		}
	}
	$callback = $this->scClient->spectrocoinProcessCallback($post_data);
	if ($callback != null){
		if (!isset($_GET['invoice_id'])) {
			error_log('SpectroCoin error. invoice_id is not provided');
			exit('SpectroCoin error. invoice_id is not provided');
		}
		$invoiceId = intval($_GET['invoice_id']);
		$status = $callback->getStatus();

		switch ($status) {
			case OrderStatusEnum::$Test:
			case OrderStatusEnum::$New:
			case OrderStatusEnum::$Pending:
			case OrderStatusEnum::$Expired:
			case OrderStatusEnum::$Failed:
				break;
			case OrderStatusEnum::$Paid:
				$invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
				$transId = "SC".$callback->getOrderRequestId();
				checkCbTransID($transId);
				$result = select_query('tblinvoices', 'total', array('id'=>$invoiceId));
				$data = mysql_fetch_array($result);
				$amount = $data['total'];
				$fee = 0;
				addInvoicePayment($invoiceId, $transId, $amount, $fee, $gatewaymodule);
				logActivity("Received SpectroCoin paid callback for invoice #$invoiceId. Transaction #$transId: Received amount: $amount $receiveCurrency, Paid amount: " . $callback->getReceivedAmount() . " ". $callback->getPayCurrency());
				break;
			default:
				error_log('SpectroCoin callback error. Unknown order status: ' . $status);
				exit('Unknown order status: ' . $status);
		}
		echo '*ok*';
		
		
	} else {
		error_log('SpectroCoin error. Invalid callback');
		exit('SpectroCoin error. Invalid callback');
	}
} else {
	header('Location: /');
}
