<?php

declare(strict_types=1);

include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

use GuzzleHttp\Exception\RequestException;
use SpectroCoin\SCMerchantClient\Enum\OrderStatus;
use SpectroCoin\SCMerchantClient\Http\OrderCallback;
use SpectroCoin\SCMerchantClient\Http\OldOrderCallback;
use SpectroCoin\SCMerchantClient\SCMerchantClient;

require __DIR__ . '/../spectrocoin/vendor/autoload.php';

if (!defined("WHMCS")) {
    die('Access denied.');
}

$gatewayModuleName = basename(__FILE__, '.php');
$GATEWAY = getGatewayVariables($gatewayModuleName);

if (!$GATEWAY["type"]) {
    logAndExit('SpectroCoin module not activated', 500);
}

$project_id    = $GATEWAY['projectId'];
$client_id     = $GATEWAY['clientId'];
$client_secret = $GATEWAY['clientSecret'];

$sc_merchant_client = new SCMerchantClient($project_id, $client_id, $client_secret);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAndExit('Invalid request method.', 405);
}

try {
    // Determine callback format by Content-Type header
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        // --- New JSON callback ---
        $body = (string) file_get_contents('php://input');
        if ($body === '') {
            logAndExit('Empty JSON callback payload', 400);
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            logAndExit('JSON callback payload is not an object', 400);
        }

        // Validate via OrderCallback (throws on invalid data)
        $orderCallback = new OrderCallback(
            $data['id'] ?? null,
            $data['merchantApiId'] ?? null
        );

        // Fetch full order details from API
        $orderData = $sc_merchant_client->getOrderById($orderCallback->getUuid());
        if (!is_array($orderData)
            || empty($orderData['orderId'])
            || empty($orderData['status'])
        ) {
            throw new InvalidArgumentException('Malformed order data from API');
        }

        $invoiceId = (int) explode('-', (string) $orderData['orderId'], 2)[0];
        $status    = (string) $orderData['status'];

    } else {
        // --- Legacy form-encoded callback ---
        $expectedKeys = [
            'userId','merchantApiId','merchantId','apiId','orderId','payCurrency',
            'payAmount','receiveCurrency','receiveAmount','receivedAmount',
            'description','orderRequestId','status','sign'
        ];
        $callbackData = [];
        foreach ($expectedKeys as $key) {
            if (isset($_POST[$key])) {
                $callbackData[$key] = $_POST[$key];
            }
        }
        if (empty($callbackData)) {
            logAndExit('No data received in callback', 400);
        }

        // Validate via OldOrderCallback (deprecated signature)
        $orderCallback = new OldOrderCallback($callbackData);

        $invoiceId = (int) explode('-', (string) $orderCallback->getOrderId(), 2)[0];
        $status    = (string) $orderCallback->getStatus();
    }

    // Now handle status updates
    switch ($status) {
        case OrderStatus::New->value:
        case OrderStatus::Pending->value:
            logTransaction($gatewayModuleName, "Invoice {$invoiceId} status is {$status}.", 'Status Update');
            break;

        case OrderStatus::Paid->value:
            $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
            $transId   = 'SC' . $orderCallback->getOrderRequestId();
            checkCbTransID($transId);

            $result = select_query('tblinvoices', 'total', ['id' => $invoiceId]);
            $data   = mysql_fetch_array($result);
            $amount = (float) $data['total'];

            addInvoicePayment($invoiceId, $transId, $amount, 0.0, $gatewayModuleName);
            logTransaction(
                $gatewayModuleName,
                "Payment added for Invoice {$invoiceId}. TransID: {$transId}, Amount: {$amount}",
                'Payment Success'
            );
            break;

        case OrderStatus::Failed->value:
        case OrderStatus::Expired->value:
            logTransaction($gatewayModuleName, "Invoice {$invoiceId} status is {$status}.", 'Status Update');
            update_query('tblinvoices', ['status' => 'Cancelled'], ['id' => $invoiceId]);
            break;

        default:
            logAndExit('Unknown order status: ' . $status, 400);
    }

    http_response_code(200);
    echo '*ok*';
    exit;
    
} catch (JsonException | InvalidArgumentException $e) {
    logAndExit('Error processing callback: ' . $e->getMessage(), 400);

} catch (Exception $e) {
    logAndExit('SpectroCoin Callback Exception: ' . $e->getMessage(), 500);
}

/**
 * Logs a transaction and exits with the given HTTP code & message.
 */
function logAndExit(string $message, int $httpCode): void {
    global $gatewayModuleName;
    logTransaction($gatewayModuleName, $message, 'Error');
    http_response_code($httpCode);
    exit($message);
}
