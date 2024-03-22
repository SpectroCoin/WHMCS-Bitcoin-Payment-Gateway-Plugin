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
$project_id = $GATEWAY['projectId'];
$client_id = $GATEWAY['clientId'];
$client_secret = $GATEWAY['clientSecret'];

if (!$privateKey || !$userId || !$projectId)
{
    echo 'SpectroCoin is not fully configured. Please select different payment';
    exit;
}
if ($amount < 0) {
    error_log('SpectroCoin error. Amount is negative');
    echo 'SpectroCoin is not fully configured. Please select different payment';
    exit;
}

$auth_url = "https://test.spectrocoin.com/api/public/oauth/token";
$api_url = 'https://spectrocoin.com/api/merchant/1';

$orderDescription = "Order #{$invoiceId}";
$callbackUrl = $options['systemURL'] . 'modules/gateways/callback/spectrocoin.php?invoice_id=' . $invoiceId;
$successUrl = $options['systemURL'] . '';
$cancelUrl = $options['systemURL'] . 'modules/gateways/callback/spectrocoin.php?cancel&invoice_id=' . $invoiceId;

$client = new SCMerchantClient($merchantApiUrl, $userId, $projectId);
$orderRequest = new Spectrocoin_CreateOrderRequest(null, "BTC", null, $currency, $amount, $orderDescription, "en", $callbackUrl, $successUrl, $cancelUrl);
$response =$client->spectrocoinCreateOrder($orderRequest);
if ($response instanceof Spectrocoin_ApiError) {
    error_log('Error getting response from SpectroCoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage());
    echo 'Error getting response from SpectroCoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage();
    exit;
} else {
	$redirectUrl = $response->getRedirectUrl();
	header('Location: ' . $redirectUrl);
}