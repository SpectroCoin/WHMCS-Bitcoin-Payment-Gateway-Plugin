# SpectroCoin WHMCS Crypto Payment Extension

Integrate cryptocurrency payments seamlessly into your WHMCS store with the [SpectroCoin WHMCS Payment Extension](https://spectrocoin.com/en/plugins/accept-bitcoin-whmcs.html). This extension facilitates the acceptance of a variety of cryptocurrencies, enhancing payment options for your customers. Easily configure and implement secure transactions for a streamlined payment process on your WHMCS website.

------------PRATESTI------

## Installation

1. Upload module content to your `WHMCS/modules/gateways` folder.<br />
2. Go to Setup -> Payments -> Payment Gateways -> All Payment Gateways
3. Select "Bitcoin provided by SpectroCoin" and press Activate
4. Enter your Merchant ID, Project ID and Private key.

## Test order creation on localhost

We gently suggest trying out the plugin in a server environment, as it will not be capable of receiving callbacks from SpectroCoin if it will be hosted on localhost. To successfully create an order on localhost for testing purposes, <b>change these 3 lines in <em>SCMechantClient.php spectrocoinCreateOrder() function</em></b>:

`'callbackUrl' => $request->getCallbackUrl()`, <br>
`'successUrl' => $request->getSuccessUrl()`, <br>
`'failureUrl' => $request->getFailureUrl()`

<b>To</b>

`'callbackUrl' => 'http://localhost.com'`, <br>
`'successUrl' => 'http://localhost.com'`, <br>
`'failureUrl' => 'http://localhost.com'`

Adjust it appropriately if your local environment URL differs.
Don't forget to change it back when migrating website to public.

## Changelog

### 1.0.0 MAJOR ():

_Updated_: Order creation API endpoint has been updated for enhanced performance and security.

_Removed_: Private key functionality and merchant ID requirement have been removed to streamline integration.

_Added_: OAuth functionality introduced for authentication, requiring Client ID and Client Secret for secure API access.

_Added_: API error logging and message displaying in order creation process.

_Migrated_: Since HTTPful is no longer maintained, we migrated to GuzzleHttp. In this case /vendor directory was added which contains GuzzleHttp dependencies.

_Reworked_: SpectroCoin callback handling was reworked. Added appropriate callback routing for success, fail and callback.

## Information

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com </br>
Skype: spectrocoin_merchant </br>
[Web](https://spectrocoin.com) </br>
[X (formerly Twitter)](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)
