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

$merchantId = $GATEWAY['merchantId'];
$projectId = $GATEWAY['projectId'];
$receiveCurrency = $GATEWAY['receive_currency'];

$request = $_REQUEST;
$client = new SCMerchantClient($merchantApiUrl, $merchantId, $projectId);
$callback = $client->parseCreateOrderCallback($request);
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if ($callback != null && $client->validateCreateOrderCallback($callback)){
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
