SpectroCoin Payment Method
---------------

This module integrates [SpectroCoin](https://spectrocoin.com/) Payments with [WHMCS](http://www.whmcs.com/) to accept [Bitcoin](https://bitcoin.org) payments.

**INSTALLATION**

1. Upload module content to your WHMCS installation folder.
2. Generate private and public keys
2.1 Private key:
    # generate a 2048-bit RSA private key
    openssl genrsa -out "C:\private" 2048
2.2 Public key:
    # output public key portion in PEM format
    openssl rsa -in "C:\private" -pubout -outform PEM -out "C:\public"
2.3 Save private key to to modules/gateways/spectrocoin/keys as "private"

**CONFIGURATION**

3. Go to Setup -> Payments -> Payment Gateways
4. Select "SpectroCoin" and press Activate
5. Enter your Merchant Id, Application Id, Private key.
