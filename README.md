SpectroCoin WHMCS Bitcoin Payment Extension
---------------

This is a [SpectroCoin Bitcoin Payment Module for WHMCS](https://spectrocoin.com/en/plugins/accept-bitcoin-whmcs.html). This extenstion allows to easily accept bitcoins (and other cryptocurrencies such as DASH) at your WHMCS powered website. You can find a  how to install this extenstion. You can view [a tutorial how to integrate bitcoin payments for WHMCS](https://www.youtube.com/watch?v=AwvrjjCfJgc).

To succesfully use this plugin, you have to have a SpectroCoin Bitcoin wallet. You can get it [here](https://spectrocoin.com/en/bitcoin-wallet.html). Also you have to create a merchant project to get merchant and project IDs, to do so create a new merchant project [here](https://spectrocoin.com/en/merchant/api/create.html).

**INSTALLATION**

1. Upload module content to your WHMCS folder.
2. Generate private key<br />
	i. Automatically<br />
	
	Go to [SpectroCoin](https://spectrocoin.com/) -> [Project list](https://spectrocoin.com/en/merchant/api/list.html)
	click on your project, then select "Edit Project and then click "Generate" (next to Public key field), as a result you will get an automatically generated private key, download and save it. The matching Public key will be generated automatically and added to your project.
	
	ii. Manually<br />
    	
	Private key:
    ```shell
    # generate a 2048-bit RSA private key
    openssl genrsa -out "C:\private" 2048
    ```
	<br />
    
**CONFIGURATION**

3. Go to Setup -> Payments -> Payment Gateways -> All Payment Gateways
4. Select "Bitcoin provided by SpectroCoin" and press Activate
5. Enter your Merchant ID, Project ID and Private key.

**INFORMATION** 

This plugin has been developed by SpectroCoin.com
If you need any further support regarding our services you can contact us via:<br />
E-mail: [info@spectrocoin.com](mailto:info@spectrocoin.com)<br />
Phone: +442037697306<br />
Skype: [spectrocoin_merchant](skype:spectrocoin_merchant)<br />
Web: [https://spectrocoin.com](https://spectrocoin.com)<br />
Twitter: [@spectrocoin](https://twitter.com/spectrocoin)<br />
Facebook: [SpectroCoin](https://www.facebook.com/spectrocoin)<br />
