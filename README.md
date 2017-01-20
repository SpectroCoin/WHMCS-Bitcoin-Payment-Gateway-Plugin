SpectroCoin WHMCS Bitcoin Payment Extension
---------------

This is a [SpectroCoin](https://spectrocoin.com/) Bitcoin Payment Module for WHMCS. This extenstion allows to easily accept bitcoins at your WHMCS powered website. You can find a  how to install this extenstion. You can view [a tutorial how to integrate bitcoin payments for WHMCS](https://www.youtube.com/watch?v=AwvrjjCfJgc).

**INSTALLATION**

1. Upload module content to your WHMCS folder.
2. Generate private and public keys
	1. Automatically:
	Go to [SpectroCoin](https://spectrocoin.com/) -> [Project list](https://spectrocoin.com/en/merchant/api/list.html)
	Click on your project  -> Edit Project -> Click on Public key (You will get Automatically generated private key, you can download it. After that and Public key will be generated Automatically.)
	
	2. Manually<br />
    	Private key:
    ```shell
    # generate a 2048-bit RSA private key
    openssl genrsa -out "C:\private" 2048
	
    ```<br />
    	Public key:
    ```shell
    # output public key portion in PEM format
    openssl rsa -in "C:\private" -pubout -outform PEM -out "C:\public"
    ```

    
4. Save private key to modules/gateways/spectrocoin/keys as "private"

**CONFIGURATION**

3. Go to Setup -> Payments -> Payment Gateways -> All Payment Gateways
4. Select "Bitcoin provided by SpectroCoin" and press Activate
5. Enter your Merchant Id, Application Id.

**INFORMATION** 

This plugin has been developed by SpectroCoin.com
If you need further support regarding our services you can contact us via:<br />
E-mail: [info@spectrocoin.com](mailto:info@spectrocoin.com)<br />
Phone: +442037697306<br />
Skype: spectrocoin_merchant<br />
Web: https://spectrocoin.com(https://spectrocoin.com)
