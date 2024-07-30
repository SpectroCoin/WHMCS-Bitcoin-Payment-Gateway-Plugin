<?php

declare(strict_types=1);

include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

use SpectroCoin\SCMerchantClient\Enum\OrderStatus;
use SpectroCoin\SCMerchantClient\Http\OrderCallback;

require __DIR__ . '/../spectrocoin/vendor/autoload.php';

if (!defined("WHMCS")) {
    die('Access denied.');
}

$gatewayModuleName = basename(__FILE__, '.php');
$GATEWAY = getGatewayVariables($gatewayModuleName);

if (!$GATEWAY["type"]) {
    logAndExit('SpectroCoin module not activated', 500);
}

$project_id = $GATEWAY['projectId'];
$client_id = $GATEWAY['clientId'];
$client_secret = $GATEWAY['clientSecret'];
$receiveCurrency = $GATEWAY['receive_currency'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $expected_keys = [
            'userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 
            'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 
            'description', 'orderRequestId', 'status', 'sign'
        ];

        $callback_data = [];
        foreach ($expected_keys as $key) {
            if (isset($_POST[$key])) {
                $callback_data[$key] = $_POST[$key];
            }
        }

        if (empty($callback_data)) {
            logAndExit('No data received in callback', 400);
        }

        $order_callback = new OrderCallback($callback_data);
        if ($order_callback === null) {
            logAndExit('Invalid callback', 400);
        }

        $invoice_id = explode('-', ($order_callback->getOrderId()))[0];
        $status = $order_callback->getStatus();

        if ($invoice_id) {
            switch ($status) {
                case OrderStatus::New->value:
                case OrderStatus::Pending->value:
                    logTransaction($gatewayModuleName, "Invoice $invoice_id status is $status.", 'Status Update');
                    break;
                case OrderStatus::Paid->value:
                    $invoice_id = checkCbInvoiceID($invoice_id, $GATEWAY["name"]);
                    $transId = "SC" . $order_callback->getOrderRequestId();
                    checkCbTransID($transId);
                    $result = select_query('tblinvoices', 'total', ['id' => $invoice_id]);
                    $data = mysql_fetch_array($result);
                    $amount = (float) $data['total'];
                    $fee = 0.0;
                    addInvoicePayment($invoice_id, $transId, $amount, $fee, $gatewayModuleName);
                    logTransaction($gatewayModuleName, "Payment added for Invoice $invoice_id. Transaction ID: $transId, Amount: $amount", 'Payment Success');
                    break;
                case OrderStatus::Failed->value:
                case OrderStatus::Expired->value:
                    logTransaction($gatewayModuleName, "Invoice $invoice_id status is $status.", 'Status Update');
                    update_query('tblinvoices', ['status' => 'Cancelled'], ['id' => $invoice_id]);
                    break;
                default:
                    logAndExit('Unknown order status: '. $status, 400);
            }
            http_response_code(200); // OK
            echo '*ok*';
            exit;
        } else {
            logAndExit("Invoice '{$invoice_id}' not found!", 404);
        }
    } catch (Exception $e) {
        logAndExit($e->getMessage(), 500);
    }
} else {
    logTransaction($gatewayModuleName, 'Invalid request method.', 'Error');
    header('Location: /');
    logAndExit('Invalid request method', 405);
    exit;
}

/**
 * Logs a transaction and exits with a specified HTTP response code and message.
 *
 * @param string $message
 * @param int $httpCode
 * @return void
 */
function logAndExit(string $message, int $httpCode): void {
    global $gatewayModuleName;
    logTransaction($gatewayModuleName, $message, 'Error');
    http_response_code($httpCode);
    exit($message);
}
?>
