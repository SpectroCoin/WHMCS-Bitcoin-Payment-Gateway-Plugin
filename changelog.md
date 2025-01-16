## Changelog

### 2.0.0 ()

_Updated_ SCMerchantClient was reworked to adhere to better coding standards.

_Updated_ Order creation API endpoint has been updated for enhanced performance and security.

_Removed_ Private key functionality and merchant ID requirement have been removed to streamline integration.

_Added_ OAuth functionality introduced for authentication, requiring Client ID and Client Secret for secure API access.

_Added_ API error logging and message displaying in order creation process.

_Migrated_ Since HTTPful is no longer maintained, we migrated to GuzzleHttp. In this case /vendor directory was added which contains GuzzleHttp dependencies.

_Updated_ Class and some method names have been updated based on PSR-12 standards.

_Updated_ Composer class autoloading has been implemented.

_Added_ _Config.php_ file has been added to store plugin configuration.

_Added_ _Utils.php_ file has been added to store utility functions.

_Added_ _GenericError.php_ file has been added to handle generic errors.

_Added_ Strict types have been added to all classes.

_Added_ Error logging using WHMCS in-build logActivity() function.

_Added_ documentation with parameters and return variables before every function

_Added_ validation and sanitization when request payload is created and also received.

_Added_ validation and sanitization when callback is received.

_Added_ Expired/Failed orders handling as "Cancelled" invoice status.

_Changed_ success and failure URL's to clientarea.php?action=invoices'.
