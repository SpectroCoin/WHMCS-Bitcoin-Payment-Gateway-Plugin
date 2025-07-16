<?php

declare(strict_types=1);

require_once '../../../init.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/gatewayfunctions.php';
require_once '../../../includes/invoicefunctions.php';

use SpectroCoin\SCMerchantClient\SCMerchantClient;
use SpectroCoin\SCMerchantClient\Exception\ApiError;
use SpectroCoin\SCMerchantClient\Exception\GenericError;

require __DIR__ . '/vendor/autoload.php';

if (!defined("WHMCS")) {
    die('Access denied.');
}

try {
    $gateway = getGatewayVariables("spectrocoin");
    validateGateway($gateway);

    validateRequestMethod($_SERVER['REQUEST_METHOD']);

    $invoiceId = getInvoiceIdFromPost($_POST);
    $invoice_data = fetchInvoiceData($invoiceId);
    validateInvoiceData($invoice_data);

    $options = $_POST;
    $sc_merchant_client = initializeMerchantClient($gateway);

    $order_data = createOrderData($invoiceId, $invoice_data, $options);
    $response = $sc_merchant_client->createOrder($order_data);

    handleResponse($response);

} catch (Exception $e) {
    logActivity("SpectroCoin error: " . $e->getMessage());
    echo 'An error occurred while processing your SpectroCoin payment. Error message: ' . $e->getMessage();
}

/**
 * Validate gateway configuration.
 *
 * @param array $gateway The gateway configuration array.
 * @throws Exception If the gateway is not active.
 */
function validateGateway(array $gateway): void
{
    if (!$gateway) {
        throw new Exception('SpectroCoin module is not active.');
    }
}

/**
 * Validate the request method.
 *
 * @param string $method The HTTP request method.
 * @throws Exception If the request method is not POST.
 */
function validateRequestMethod(string $method): void
{
    if ($method !== 'POST') {
        throw new Exception('Invalid request method. POST required.');
    }
}

/**
 * Retrieve the invoice ID from POST data.
 *
 * @param array $post_data The POST data array.
 * @return int The invoice ID.
 */
function getInvoiceIdFromPost(array $post_data): int
{
    return isset($post_data['invoiceId']) ? (int)$post_data['invoiceId'] : 0;
}

/**
 * Fetch invoice data from the database.
 *
 * @param int $invoiceId The invoice ID.
 * @return array The invoice data array.
 * @throws Exception If the MySQL query fails or no invoice is found.
 */
function fetchInvoiceData(int $invoiceId): array
{
    $result = mysql_query("SELECT tblinvoices.total, tblinvoices.status, tblcurrencies.code 
                           FROM tblinvoices
                           JOIN tblclients ON tblinvoices.userid = tblclients.id
                           JOIN tblcurrencies ON tblclients.currency = tblcurrencies.id
                           WHERE tblinvoices.id = $invoiceId");

    if (!$result) {
        throw new Exception('MySQL query failed: ' . mysql_error());
    }

    $data = mysql_fetch_assoc($result);
    if (!$data) {
        throw new Exception('No invoice found for invoice id ' . $invoiceId);
    }

    return $data;
}

/**
 * Validate the fetched invoice data.
 *
 * @param array $data The invoice data array.
 * @throws Exception If the invoice status is not 'Unpaid'.
 */
function validateInvoiceData(array $data): void
{
    if ($data['status'] !== 'Unpaid') {
        throw new Exception('Invoice status must be Unpaid. Status: ' . $data['status']);
    }
}

/**
 * Initialize the SpectroCoin Merchant Client.
 *
 * @param array $gateway The gateway configuration array.
 * @return SCMerchantClient The SpectroCoin Merchant Client instance.
 * @throws Exception If the SpectroCoin configuration is incomplete.
 */
function initializeMerchantClient(array $gateway): SCMerchantClient
{
    $project_id = $gateway['projectId'];
    $client_id = $gateway['clientId'];
    $client_secret = $gateway['clientSecret'];

    if (!$project_id) {
        throw new Exception('SpectroCoin project ID is not configured. Please select a different payment method.');
    }
    
    if (!$client_id) {
        throw new Exception('SpectroCoin client ID is not configured. Please select a different payment method.');
    }
    
    if (!$client_secret) {
        throw new Exception('SpectroCoin client secret is not configured. Please select a different payment method.');
    }

    return new SCMerchantClient($project_id, $client_id, $client_secret);
}

/**
 * Create the order data array.
 *
 * @param int $invoiceId The invoice ID.
 * @param array $invoice_data The invoice data array.
 * @param array $options Additional options from POST data.
 * @return array The order data array.
 */
function createOrderData(int $invoiceId, array $invoice_data, array $options): array
{
    $orderData = [
        'orderId' => $invoiceId . "-" . rand(1000, 9999),
        'description' => "Order #{$invoiceId}",
        'receiveAmount' => $invoice_data['total'],
        'receiveCurrencyCode' => $invoice_data['code'],
        'callbackUrl' => $options['systemURL'] . 'modules/gateways/callback/spectrocoin.php?invoice_id=' . $invoiceId,
        'successUrl' => $options['systemURL'] . 'clientarea.php?action=invoices',
        'failureUrl' => $options['systemURL'] . 'clientarea.php?action=invoices'
    ];
    return $orderData;
}

/**
 * Handle the response from SpectroCoin.
 *
 * @param mixed $response The response from SpectroCoin.
 * @throws Exception If the response contains an API error.
 */
function handleResponse($response): void
{
    if ($response instanceof GenericError || $response instanceof ApiError) {
        throw new Exception('Error getting response from SpectroCoin. Error code: ' . $response->getCode() . ' Error message: ' . $response->getMessage());
    } else {
        $redirect_url = $response->getRedirectUrl();
        header('Location: ' . $redirect_url); 
    }
}