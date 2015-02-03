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
    die("Spectrocoin module not activated");
}

$privateKey = __DIR__ . '/../spectrocoin/keys/private';

if (!file_exists($privateKey) ||
    !is_file($privateKey)  ) {
    error_log('SpectroCoin. No private key file found');
    echo 'No private key file found';
    exit;
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
            echo 'SpectroCoin error. Currencies does not match in callback';
            exit;
        }
        if (!isset($_GET['invoice_id'])) {
            error_log('SpectroCoin error. invoice_id is not provided');
            echo 'SpectroCoin error. invoice_id is not provided';
            exit;
        }

        if (!isset($_GET['invoice_id'])) {
            die("Missing invoice_id parameter");
        }
        $invoiceId = intval($_GET['invoice_id']);

        switch ($callback->getStatus()) {
            case OrderStatusEnum::$Test:
                break;
            case OrderStatusEnum::$New:
                break;
            case OrderStatusEnum::$Pending:
                break;
            case OrderStatusEnum::$Expired:
                break;
            case OrderStatusEnum::$Failed:
                break;
            case OrderStatusEnum::$Paid:
                $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
				$transId = "SC".$callback->getOrderRequestId();
                checkCbTransID($transId);
				
				$query = mysql_query("SELECT tblcurrencies.code FROM tblinvoices, tblclients, tblcurrencies where tblinvoices.userid = tblclients.id and tblclients.currency = tblcurrencies.id and tblinvoices.id=$invoiceId");
				$data = mysql_fetch_assoc($query);
				if (!$data) {
					error_log('No invoice found for invoice id' . $invoiceId);
					die("Invalid invoice");
				}
				$currency = $data['code'];
				
				$receivedAmount = $callback->getReceivedAmount();
				if ($currency != $receiveCurrency) {
					$amount = unitConversion($receivedAmount, $receiveCurrency, $currency);
				} else {
					$amount = $receivedAmount;
				}
                
                $fee = 0;
                addInvoicePayment($invoiceId, $transId, $amount, $fee, $gatewaymodule);
                break;
            default:
                error_log('SpectroCoin callback error. Unknown order status: ' . $callback->getStatus());
                echo 'Unknown order status: '.$callback->getStatus();
                exit;
        }
        echo '*ok*';
    } else {
		error_log('SpectroCoin error. Invalid callback');
		echo 'SpectroCoin error. Invalid callback';
		exit;
	}
} else {
    header('Location: /');
}
