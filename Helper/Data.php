<?php

namespace Madeiranit\RedirectSimpleProducts\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * @codingStandardsIgnoreFile
 */
class Data extends AbstractHelper
{
    const XML_PATH_REDIRECTSIMPLEPRODUCTS = 'redirectsimpleproducts/';

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_REDIRECTSIMPLEPRODUCTS . 'general/' . $code, $storeId);
    }
}
