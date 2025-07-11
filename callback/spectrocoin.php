<?php
declare(strict_types=1);

include '../../../init.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

use GuzzleHttp\Exception\RequestException;
use SpectroCoin\SCMerchantClient\SCMerchantClient;
use SpectroCoin\SCMerchantClient\Enum\OrderStatus;
use SpectroCoin\SCMerchantClient\Http\OldOrderCallback;

require __DIR__ . '/../spectrocoin/vendor/autoload.php';

if (!defined("WHMCS")) {
    die('Access denied.');
}

$gatewayModuleName = basename(__FILE__, '.php');
$GATEWAY = getGatewayVariables($gatewayModuleName);
if (!$GATEWAY["type"]) {
    logAndExit('SpectroCoin module not activated', 500);
}

/**
 * Centralized error logger + HTTP exit
 */
function logAndExit(string $message, int $httpCode): void
{
    global $gatewayModuleName;
    logTransaction($gatewayModuleName, $message, 'Error');
    http_response_code($httpCode);
    exit($message);
}

// Only allow POST callbacks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAndExit('Invalid request method.', 405);
}

try {
    $rawStatus      = null;
    $invoiceId      = null;
    $orderRequestId = null;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        // --- New JSON callback ---
        $body = file_get_contents('php://input') ?: '';
        if ($body === '') {
            logAndExit('Empty JSON callback payload', 400);
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || empty($data['id']) || empty($data['merchantApiId'])) {
            logAndExit('Invalid JSON callback payload', 400);
        }

        // Fetch latest status from SpectroCoin API
        $scClient  = new SCMerchantClient(
            $GATEWAY['projectId'],
            $GATEWAY['clientId'],
            $GATEWAY['clientSecret']
        );
        $orderData = $scClient->getOrderById($data['id']);
        if (!is_array($orderData) || empty($orderData['orderId']) || !isset($orderData['status'])) {
            throw new \InvalidArgumentException('Malformed order data from SpectroCoin API');
        }

        $invoiceId      = (int) explode('-', $orderData['orderId'], 2)[0];
        $rawStatus      = $orderData['status'];
        $orderRequestId = $orderData['merchantPreOrderId'] ?? $data['id'];

    } else {
        // --- Legacy form-encoded callback ---
        $expected = [
            'userId','merchantApiId','merchantId','apiId','orderId','payCurrency',
            'payAmount','receiveCurrency','receiveAmount','receivedAmount',
            'description','orderRequestId','status','sign'
        ];
        $cb = [];
        foreach ($expected as $key) {
            if (isset($_POST[$key])) {
                $cb[$key] = $_POST[$key];
            }
        }
        if (empty($cb)) {
            logAndExit('No data received in callback', 400);
        }

        $oldCb          = new OldOrderCallback($cb);
        $invoiceId      = (int) explode('-', $oldCb->getOrderId(), 2)[0];
        $rawStatus      = $oldCb->getStatus();
        $orderRequestId = $oldCb->getOrderRequestId();
    }

    // Normalize into your enum
    $statusEnum = OrderStatus::normalize($rawStatus);

    switch ($statusEnum) {
        case OrderStatus::NEW:
        case OrderStatus::PENDING:
            logTransaction(
                $gatewayModuleName,
                "Invoice {$invoiceId} status is {$statusEnum->value}.",
                'Status Update'
            );
            break;

        case OrderStatus::PAID:
            $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
            $transId   = 'SC' . $orderRequestId;
            checkCbTransID($transId);

            $res    = select_query('tblinvoices', 'total', ['id' => $invoiceId]);
            $data   = mysql_fetch_array($res);
            $amount = (float)$data['total'];

            addInvoicePayment(
                $invoiceId,
                $transId,
                $amount,
                0.0,
                $gatewayModuleName
            );
            logTransaction(
                $gatewayModuleName,
                "Payment successful for Invoice {$invoiceId}, TransID {$transId}, Amount {$amount}",
                'Payment Success'
            );
            break;

        case OrderStatus::FAILED:
        case OrderStatus::EXPIRED:
            logTransaction(
                $gatewayModuleName,
                "Invoice {$invoiceId} status is {$statusEnum->value}.",
                'Status Update'
            );
            update_query('tblinvoices', ['status' => 'Cancelled'], ['id' => $invoiceId]);
            break;

        default:
            // Should never happen because normalize() throws on unknown
            logAndExit("Unhandled status: {$statusEnum->value}", 500);
    }

    http_response_code(200);
    echo '*ok*';
    exit;

} catch (\JsonException | \InvalidArgumentException $e) {
    // Known issues (parsing, unknown status, malformed data)
    logAndExit('Error processing callback: ' . $e->getMessage(), 400);

} catch (\Throwable $e) {
    // Catch everything else (autoload failures, fatal errors, etc)
    logAndExit('SpectroCoin Callback Exception: ' . $e->getMessage(), 500);
}
