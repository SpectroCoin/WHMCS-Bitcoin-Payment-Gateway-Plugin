<?php
# Required File Includes
include '../../../dbconnect.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require_once '../spectrocoin/lib/SCMerchantClient/SCMerchantClient.php';

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
    error_log('Spectrocoin. No private key file found');
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
            error_log('Spectrocoin error. Currencies does not match in callback');
            echo 'Spectrocoin error. Currencies does not match in callback';
            exit;
        }
        $invoiceId = $callback->getOrderId();
        switch ($callback->getStatus()) {
            case OrderStatusEnum::$Test:
                break;
            case OrderStatusEnum::$New:
                break;
            case OrderStatusEnum::$Pending:
                break;
            case OrderStatusEnum::$Expired:
                mysql_query("update tblorders set status='Cancelled' where invoiceid = $invoiceId");
                break;
            case OrderStatusEnum::$Failed:
                mysql_query("update tblorders set status='Cancelled' where invoiceid = $invoiceId");
                break;
            case OrderStatusEnum::$Paid:
                $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
                $transId = "SC" . $invoiceId;
                checkCbTransID($transId);
                $fee = 0;
                $amount = '';
                addInvoicePayment($invoiceId, $transId, $amount, $fee, $gatewaymodule);
                break;
            default:
                error_log('Spectrocoin callback error. Unknown order status: ' . $callback->getStatus());
                echo 'Unknown order status: '.$callback->getStatus();
                exit;
        }
        echo '*ok*';
    }
} else {
    // Cancel callback
    if (isset($_GET['cancel']) && isset($_GET['order_id'])) {
        $invoiceId = intval($_GET['order_id']);
        mysql_query("update tblorders set status='Cancelled' where invoiceid = $invoiceId");
        header('Location: /');
    }
}
