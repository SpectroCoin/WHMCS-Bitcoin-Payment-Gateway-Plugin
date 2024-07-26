# SpectroCoin WHMCS Crypto Payment Extension

Integrate cryptocurrency payments seamlessly into your WHMCS store with the [SpectroCoin WHMCS Payment Extension](https://spectrocoin.com/en/plugins/accept-bitcoin-whmcs.html). This extension facilitates the acceptance of a variety of cryptocurrencies, enhancing payment options for your customers. Easily configure and implement secure transactions for a streamlined payment process on your WHMCS website.

## Installation

1. Download latest release from github.
2. Extract the contents of the zip file.
3. Access WHMCS site root directory via ftp and navigate to <i>/modules/gateways/</i>, upload <i>spectrocoin</i>, <i>spectrocoin.php</i>, <i>logo.png</i> and <i>whmcs.json</i>
4. Navigate to <i>/callback/</i> folder and upload <i>spectrocoin.php</i> file.
5. From WHMCS admin dashboard navigate to <b>"Addons"</b> -> <b>"Apps & Integrations"</b> -> <b>"Payment Gateways"</b> -> <b>"All Payment Gateways"</b> -> <b>"Browse"</b> -> <b>"Payments"</b> -> <b>"SpectroCoin"</b> -> <b>"Manage"</b>.
6. Move to section [Setting up](#setting-up).

## Setting up

1. **[Sign up](https://auth.spectrocoin.com/signup)** for a SpectroCoin Account.
2. **[Log in](https://auth.spectrocoin.com/login)** to your SpectroCoin account.
3. On the dashboard, locate the **[Business](https://spectrocoin.com/en/merchants/projects)** tab and click on it.
4. Click on **[New project](https://spectrocoin.com/en/merchants/projects/new)**.
5. Fill in the project details and select desired settings (settings can be changed).
6. Click **"Submit"**.
7. Copy and paste the "Project id".
8. Click on the user icon in the top right and navigate to **[Settings](https://test.spectrocoin.com/en/settings/)**. Then click on **[API](https://test.spectrocoin.com/en/settings/api)** and choose **[Create New API](https://test.spectrocoin.com/en/settings/api/create)**.
9. Add "API name", in scope groups select **"View merchant preorders"**, **"Create merchant preorders"**, **"View merchant orders"**, **"Create merchant orders"**, **"Cancel merchant orders"** and click **"Create API"**.
10. Copy and store "Client id" and "Client secret". Save the settings.

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

## Information

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com </br>
Skype: spectrocoin_merchant </br>
[Web](https://spectrocoin.com) </br>
[X (formerly Twitter)](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)
