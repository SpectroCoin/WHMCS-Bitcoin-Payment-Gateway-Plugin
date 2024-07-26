<?php

namespace SpectroCoin\SCMerchantClient\Exception;

if (!defined("WHMCS")) {
    die('Access denied.');
}

class GenericError extends \Exception
{
    /**
     * @param string $message
     * @param int $code
     */
    function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
