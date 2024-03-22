<?php
include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once 'lib/SCMerchantClient/SCMerchantClient.php';
$GATEWAY = getGatewayVariables("spectrocoin");
$invoice_id = (int)$_POST['invoiceId'];
$price = $currency = false;
$result = mysql_query("SELECT tblinvoices.total, tblinvoices.status, tblcurrencies.code FROM tblinvoices, tblclients, tblcurrencies where tblinvoices.userid = tblclients.id and tblclients.currency = tblcurrencies.id and tblinvoices.id=$invoice_id");
$data = mysql_fetch_assoc($result);

if (!$data) {
    error_log('No invoice found for invoice id' . $invoice_id);
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

if (!$project_id || !$client_id || !$client_secret)
{
    echo 'SpectroCoin is not fully configured. Please select different payment';
    exit;
}
if ($amount < 0) {
    error_log('SpectroCoin error. Amount is negative');
    echo 'SpectroCoin is not fully configured. Please select different payment';
    exit;
}

$order_description = "Order #{$invoice_id}";
$callback_url = $options['systemURL'] . 'modules/gateways/callback/spectrocoin.php?invoice_id=' . $invoice_id;
$success_url = $options['systemURL'] . '';
$cancel_url = $options['systemURL'] . 'modules/gateways/callback/spectrocoin.php?cancel&invoice_id=' . $invoice_id;

$auth_url = "https://test.spectrocoin.com/api/public/oauth/token";
$api_url = 'https://spectrocoin.com/api/merchant/1';

$client = new SCMerchantClient($api_url, $project_id, $client_id, $client_secret, $auth_url);
$order_request = new Spectrocoin_CreateOrderRequest(null, "BTC", null, $currency, $amount, $order_description, "en", $callback_url, $success_url, $cancel_url);
$response =$client->spectrocoinCreateOrder($order_request);
if ($response instanceof Spectrocoin_ApiError) {
    error_log('Error getting response from SpectroCoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage());
    echo 'Error getting response from SpectroCoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage();
    exit;
} else {
	$redirect_url = $response->getRedirectUrl();
	header('Location: ' . $redirect_url);
}