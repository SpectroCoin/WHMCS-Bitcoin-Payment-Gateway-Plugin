<?php

include '../../../dbconnect.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once 'lib/SCMerchantClient/SCMerchantClient.php';

function unitConversion($amount, $currencyFrom, $currencyTo)
{
    $currencyFrom = strtoupper($currencyFrom);
    $currencyTo = strtoupper($currencyTo);
    $url = "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.xchange%20where%20pair%20in%20%28%22{$currencyTo}{$currencyFrom}%22%20%29&env=store://datatables.org/alltableswithkeys&format=json";
    $content = file_get_contents($url);
    if ($content) {
        $obj = json_decode($content);
        if (!isset($obj->error) && isset($obj->query->results->rate->Rate)) {
            $rate = $obj->query->results->rate->Rate;
            return ($amount * 1.0) / $rate;
        }
    }
}

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
$appId = $GATEWAY['appId'];
$privateKeyFilePath = __DIR__ . '/keys/private';

if (!file_exists($privateKeyFilePath) || !is_file($privateKeyFilePath)
    || !$merchantId || !$appId
) {
    echo 'Spectrocoin is not fully configured. Please select different payment';
    exit;
}

$receiveCurrency = strtoupper(trim($GATEWAY['receive_currency']));

if ($currency != $receiveCurrency) {
    $receiveAmount = unitConversion($amount, $currency, $receiveCurrency);
} else {
    $receiveAmount = $amount;
}
if ($receiveAmount < 0) {
    error_log('Spectrocoin error. Could not convert amount to other currency');
    echo 'Spectrocoin is not fully configured. Please select different payment';
    exit;
}

$orderDescription = "Order #{$invoiceId}";
$callbackUrl = $options['systemURL'] . '/modules/gateways/callback/spectrocoin.php?invoice_id=' . $invoiceId;
$successUrl = $options['systemURL'] . '';
$cancelUrl = $options['systemURL'] . '/modules/gateways/callback/spectrocoin.php?cancel&invoice_id=' . $invoiceId;

$client = new SCMerchantClient($privateKeyFilePath, '', $merchantId, $appId);
$orderRequest = new CreateOrderRequest(null, 0, $receiveAmount, $orderDescription, "en", $callbackUrl, $successUrl, $cancelUrl);
$response = $client->createOrder($orderRequest);

if ($response instanceof ApiError) {
    error_log('Error getting response from Spectrocoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage());
    echo 'Error getting response from Spectrocoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage();
    exit;
} else {
    if ($response->getReceiveCurrency() != $receiveCurrency) {
        echo 'Currencies does not match';
        exit;
    } else {
        $redirectUrl = $response->getRedirectUrl();
        header('Location: ' . $redirectUrl);
    }

}