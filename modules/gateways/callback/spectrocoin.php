<?php
# Required File Includes
include '../../../dbconnect.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once '../spectrocoin/lib/SCMerchantClient/SCMerchantClient.php';

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

if (!$GATEWAY["type"]) {
    logTransaction($GATEWAY["name"], $_POST, 'Not activated');
    error_log('Spectrocoin module not activated');
    exit("Spectrocoin module not activated");
}

$privateKey = __DIR__ . '/../spectrocoin/keys/private';

if (!file_exists($privateKey) || !is_file($privateKey)) {
    error_log('SpectroCoin. No private key file found');
    exit('No private key file found');
}

$merchantId = $GATEWAY['merchantId'];
$appId = $GATEWAY['appId'];
$receiveCurrency = $GATEWAY['receive_currency'];

$request = $_REQUEST;
$client =  new SCMerchantClient($privateKey, '', $merchantId, $appId);
$callback = $client->parseCreateOrderCallback($request);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($client->validateCreateOrderCallback($callback)) {
        if ($callback->getReceiveCurrency() != $receiveCurrency) {
            error_log('SpectroCoin error. Currencies does not match in callback');
            exit('SpectroCoin error. Currencies does not match in callback');
        }
        if (!isset($_GET['invoice_id'])) {
            error_log('SpectroCoin error. invoice_id is not provided');
            exit('SpectroCoin error. invoice_id is not provided');
        }
        $invoiceId = intval($_GET['invoice_id']);
		$status = $callback->getStatus();
		
		$log = "Received SpectroCoin callback for invoice #$invoiceId. Status: $status";

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
				
				$log .= ", Transaction #$transId";
				
				$query = mysql_query("SELECT tblcurrencies.code FROM tblinvoices, tblclients, tblcurrencies where tblinvoices.userid = tblclients.id and tblclients.currency = tblcurrencies.id and tblinvoices.id=$invoiceId");
				$data = mysql_fetch_assoc($query);
				if (!$data) {
					error_log('No invoice found for invoice id' . $invoiceId);
					die("Invalid invoice");
				}
				$currency = $data['code'];
				$receivedAmount = $callback->getReceivedAmount();
				
				$log .= ", Invoice currency: '$currency', Receive currency: '$receiveCurrency', Received amount: $receivedAmount";
				
				if ($currency != $receiveCurrency) {
					$amount = unitConversion($receivedAmount, $receiveCurrency, $currency);
					$log .= ", Converted amount: $amount";
				} else {
					$amount = $receivedAmount;
				}
                
                $fee = 0;
                addInvoicePayment($invoiceId, $transId, $amount, $fee, $gatewaymodule);
                break;
            default:
                error_log('SpectroCoin callback error. Unknown order status: ' . $callback->getStatus());
                exit('Unknown order status: '.$callback->getStatus());
        }
        echo '*ok*';
		
		logActivity($log);
    } else {
		error_log('SpectroCoin error. Invalid callback');
		exit('SpectroCoin error. Invalid callback');
	}
} else {
    header('Location: /');
}
