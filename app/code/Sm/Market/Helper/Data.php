<?php
/*------------------------------------------------------------------------
# SM Market - Version 1.0.0
# Copyright (c) 2016 YouTech Company. All Rights Reserved.
# @license - Copyrighted Commercial Software
# Author: YouTech Company
# Websites: http://www.magentech.com
-------------------------------------------------------------------------*/

namespace Sm\Market\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Header\Logo;

class Data extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Logo
     */
    protected $logo;

    /**
     * Data constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Logo $logo
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Logo $logo
    ) {
        $this->storeManager = $storeManager;
        $this->logo = $logo;
        parent::__construct($context);
    }

    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    public function getUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    public function getUrlStatic()
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_STATIC);
    }

    public function getLocale()
    {
        return $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getMediaUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    public function getGeneral($name)
    {
        return $this->scopeConfig->getValue(
            'market/general/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getThemeLayout($name)
    {
        return $this->scopeConfig->getValue(
            'market/theme_layout/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getProductListing($name)
    {
        return $this->scopeConfig->getValue(
            'market/product_listing/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getProductDetail($name)
    {
        return $this->scopeConfig->getValue(
            'market/product_detail/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getSocial($name)
    {
        return $this->scopeConfig->getValue(
            'market/socials/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getAdvanced($name)
    {
        return $this->scopeConfig->getValue(
            'market/advanced/' . $name,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isHomePage()
    {
        return $this->logo->isHomePage();
    }
}
