<?php
include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once 'lib/SCMerchantClient/SCMerchantClient.php';
$gatewaymodule = "spectrocoin";
$GATEWAY = getGatewayVariables($gatewaymodule);
// get invoice
$invoiceId = (int)$_POST['invoiceId'];
$price = $currency = false;
$result = mysql_query("SELECT tblinvoices.total, tblinvoices.status, tblcurrencies.code FROM tblinvoices, tblclients, tblcurrencies where tblinvoices.userid = tblclients.id and tblclients.currency = tblcurrencies.id and tblinvoices.id=$invoiceId");
$data = mysql_fetch_assoc($result);

if (!$data) {
    error_log('No invoice found for invoice id' . $invoiceId);
    die("Invalid invoice");
}

$price    = $data['total'];
$amount   = $price;
$currency = $data['code'];
$status   = $data['status'];

if ($status != 'Unpaid') {
    error_log("Invoice status must be Unpaid.  Status: " . $status);
    die('Bad invoice status');
}

$options = $_POST;
$merchantId = $GATEWAY['merchantId'];
$projectId = $GATEWAY['projectId'];
$privateKey = $GATEWAY['privateKey'];

if (!file_exists($privateKeyFilePath) || !is_file($privateKeyFilePath)
    || !$merchantId || !$projectId)

if (!$privateKey || !$merchantId || !$projectId)
{
    echo 'Spectrocoin is not fully configured. Please select different payment';
    exit;
}
if ($amount < 0) {
    error_log('Spectrocoin error. Amount is negativ');
    echo 'Spectrocoin is not fully configured. Please select different payment';
    exit;
}

$orderDescription = "Order #{$invoiceId}";
$callbackUrl = $options['systemURL'] . '/modules/gateways/callback/spectrocoin.php?invoice_id=' . $invoiceId;
$successUrl = $options['systemURL'] . '';
$cancelUrl = $options['systemURL'] . '/modules/gateways/callback/spectrocoin.php?cancel&invoice_id=' . $invoiceId;
$merchantApiUrl = 'https://spectrocoin.com/api/merchant/1';
$client = new SCMerchantClient($merchantApiUrl, $merchantId, $projectId);
$client->setPrivateMerchantKey($privateKey);
$orderRequest = new CreateOrderRequest(null, "BTC", null, $currency, $amount, $orderDescription, "en", $callbackUrl, $successUrl, $cancelUrl);
$response =$client->createOrder($orderRequest);
if ($response instanceof ApiError) {
    error_log('Error getting response from Spectrocoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage());
    echo 'Error getting response from Spectrocoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage();
    exit;
} else {
	$redirectUrl = $response->getRedirectUrl();
	header('Location: ' . $redirectUrl);
}