<?php
/**
 * OnePica_AvaTax
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0), a
 * copy of which is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   OnePica
 * @package    OnePica_AvaTax
 * @author     OnePica Codemaster <codemaster@onepica.com>
 * @copyright  Copyright (c) 2009 One Pica, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * The base AvaTax Helper class
 *
 * @category   OnePica
 * @package    OnePica_AvaTax
 * @author     OnePica Codemaster <codemaster@onepica.com>
 */
class OnePica_AvaTax_Helper_Address extends Mage_Core_Helper_Abstract
{
    /**
     * Determines if address normalization is enabled
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param int                            $storeId
     *
     * @return bool
     */
    public function isAddressNormalizationOn($address, $storeId)
    {
        if (!$this->isAddressActionable(
            $address,
            $storeId,
            OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_ALL, true)
        ) {
            return false;
        }

        $result = $this->_getConfigData()->getNormalizeAddress($storeId);
        $quote = $address->getQuote();
        if ($quote) {
            $flag = $quote->getAvataxNormalizationFlag();
            $flag = is_null($flag) ? 0 : $flag; //if no flag than normalization enabled
            switch ($flag) {
                case 1: // disabled
                    $result = false;
                    break;
                default:
                    break;
            }
        }

        return $result;
    }

    /**
     * Method used to restore origin customer address during normalization flag update
     * for multishipping checkout
     *
     * @param null $quote
     * @param bool $isMultishipping
     *
     * @return null
     */
    public function setOriginalCustomerAddresses($quote = null, $isMultishipping = false)
    {
        if ($quote && $isMultishipping) {
            switch ($quote->getAvataxNormalizationFlag()) {
                case 1:
                    $addresses = $quote->getAllShippingAddresses();
                    foreach ($addresses as $address) {
                        $customerAddressOriginal = Mage::getModel('customer/address')
                            ->load($address->getCustomerAddressId());

                        $quote->getAddressById($address->getId())
                            ->setStreet($customerAddressOriginal->getStreet())
                            ->setCity($customerAddressOriginal->getCity())
                            ->setRegionId($customerAddressOriginal->getRegionId())
                            ->setPostcode($customerAddressOriginal->getPostcode())
                            ->setCountryId($customerAddressOriginal->getCountryId())
                            ->save();
                    }
                    break;
                default:
                    break;
            }
        }
        return $quote;
    }

    /**
     * Determines if address validation is enabled
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param int                            $storeId
     *
     * @return bool
     */
    public function isAddressValidationOn($address, $storeId)
    {
        if (!$this->isAddressActionable(
            $address,
            $storeId,
            OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_ALL, true)
        ) {
            return false;
        }

        return $this->_getConfigData()->getValidateAddress($storeId);
    }

    /**
     * Get address validation countries
     *
     * @return array
     */
    public function getAddressValidationCountries()
    {
        return explode(',', $this->_getConfigData()->getAddressValidationCountries());
    }

    /**
     * Determines if the address should be filtered
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param int                            $storeId
     * @param int                            $filterMode
     * @param bool                           $isAddressValidation
     *
     * @return bool
     */
    public function isAddressActionable(
        $address,
        $storeId,
        $filterMode = OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_ALL,
        $isAddressValidation = false
    ) {
        $filter = false;

        if ($this->_getConfigData()->getStatusServiceAction($storeId)
            == OnePica_AvaTax_Model_Service_Abstract_Config::ACTION_DISABLE
        ) {
            return false;
        }

        if ($this->getRegionFilterModByStore($storeId) >= $filterMode) {
            $filter = $this->_getFilterRegion($address, $storeId);
        }

        if ($isAddressValidation
            && $filter
            && ((int)$this->getRegionFilterModByStore($storeId)
                !== OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_ALL
            )
        ) {
            $filter = false;
        }

        if (!in_array($address->getCountryId(), $this->getTaxableCountryByStore($storeId))) {
            $filter = 'country';
        }

        if ($isAddressValidation
            && !$filter
            && !in_array($address->getCountryId(), $this->getAddressValidationCountries())
        ) {
            $filter = 'country';
        }

        if ($filter && $this->_getHelper()->getLogMode($storeId)) {
            $logType = $this->_getLogTypeModel()->getFilterType();
            if (in_array($logType, $this->_getHelper()->getLogType($storeId))) {
                $filterLog = Mage::getSingleton('avatax/session')->getFilterLog();
                if (!is_array($filterLog)) {
                    $filterLog = array();
                }
                $key = $address->getCacheHashKey();

                //did we already log this filtered address?
                if (!in_array($key, $filterLog)) {
                    $filterLog[] = $key;
                    Mage::getSingleton('avatax/session')->setFilterLog($filterLog);

                    $type = ($filterMode == OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_TAX)
                        ? 'tax_calc'
                        : 'tax_calc|address_opts';
                    Mage::getModel('avatax_records/log')
                        ->setStoreId($storeId)
                        ->setLevel('Success')
                        ->setType($logType)
                        ->setRequest(print_r($address->debug(), true))
                        ->setResult('filter: ' . $filter . ', type: ' . $type)
                        ->save();
                }
            }
        }

        return $filter ? false : true;
    }

    /**
     * Get region filter mod by store
     *
     * @param null|int $storeId
     *
     * @return int
     */
    public function getRegionFilterModByStore($storeId = null)
    {
        return $this->_getConfigData()->getRegionFilterMode($storeId);
    }

    /**
     * Get region filter mode by current scope
     *
     * @throws \Mage_Core_Exception
     * @return int
     */
    public function getRegionFilterModByCurrentScope()
    {
        $websiteId = Mage::app()->getRequest()->get('website');
        $storeId = Mage::app()->getRequest()->get('store');

        if ($websiteId && !$storeId) {
            return $this->getRegionFilterModByWebsite($websiteId);
        }

        return $this->getRegionFilterModByStore($storeId);
    }

    /**
     * Get region filter mod by website
     *
     * @param int $websiteId
     *
     * @return int
     * @throws \Mage_Core_Exception
     */
    public function getRegionFilterModByWebsite($websiteId)
    {
        return $this->_getConfigData()->getConfigRegionFilterModByWebsite($websiteId);
    }

    /**
     * Get region filter
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param int                            $storeId
     *
     * @return string|bool
     */
    protected function _getFilterRegion($address, $storeId)
    {
        $filter = false;
        $regionFilters = explode(',', $this->_getConfigData()->getRegionFilterList($storeId));
        $entityId = $address->getRegionId() ?: $address->getCountryId();
        if (!in_array($entityId, $regionFilters)) {
            $filter = 'region';
        }

        return $filter;
    }

    /**
     * Get taxable country by store
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getTaxableCountryByStore($storeId = null)
    {
        return explode(',', $this->_getConfigData()->getTaxableCountry($storeId));
    }

    /**
     * Get taxable country by website
     *
     * @param int $websiteId
     *
     * @return array
     */
    public function getTaxableCountryByWebSite($websiteId)
    {
        return explode(',', Mage::app()
            ->getWebsite($websiteId)
            ->getConfig(OnePica_AvaTax_Helper_Config::XML_PATH_TO_TAX_AVATAX_TAXABLE_COUNTRY)
        );
    }

    /**
     * Get taxable country by current scope
     *
     * Used in admin panel
     *
     * @return array
     */
    public function getTaxableCountryByCurrentScope()
    {
        $websiteId = Mage::app()->getRequest()->get('website');
        $storeId = Mage::app()->getRequest()->get('store');
        if ($websiteId && !$storeId) {
            return $this->getTaxableCountryByWebSite($websiteId);
        }

        return $this->getTaxableCountryByStore($storeId);
    }

    /**
     * Determines if the object (quote, invoice, or credit memo) should use AvaTax services
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $object
     * @param Mage_Sales_Model_Quote_Address                                                          $shippingAddress
     *
     * @return bool
     */
    public function isObjectActionable($object, $shippingAddress = null)
    {
        $storeId = $object->getStore()->getId();

        //is action enabled?
        $action = $object->getOrder()
            ? OnePica_AvaTax_Model_Service_Abstract_Config::ACTION_CALC_SUBMIT
            : OnePica_AvaTax_Model_Service_Abstract_Config::ACTION_CALC;
        if ($this->_getConfigData()->getStatusServiceAction($storeId) < $action) {
            return false;
        }

        if (!$shippingAddress) {
            $shippingAddress = $object->getShippingAddress();
        }
        if (!$shippingAddress) {
            $shippingAddress = $object->getBillingAddress();
        }

        //is the region filtered?
        if (!$this->isAddressActionable(
            $shippingAddress,
            $storeId,
            OnePica_AvaTax_Model_Service_Abstract_Config::REGIONFILTER_TAX)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get config helper
     *
     * @return OnePica_AvaTax_Helper_Config
     */
    private function _getConfigData()
    {
        return Mage::helper('avatax/config');
    }

    /**
     * Get avatax data helper
     *
     * @return OnePica_AvaTax_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('avatax');
    }

    /**
     * Get log type model
     *
     * @return OnePica_AvaTax_Model_Source_Logtype
     */
    protected function _getLogTypeModel()
    {
        return Mage::getModel('avatax/source_logtype');
    }

    /**
     * Get HTML content of checkbox that can be used to disable normalization on store front
     * for multishipping checkout
     *
     * @param null $flag
     *
     * @return string
     */
    public function getDisableNormalizationCheckbox($flag = null)
    {
        $checked = $flag ? "checked='checked'" : '';

        $html = "<p>
            <input type='checkbox'
                    name='allow_normalize_shipping_address'
                    id='allow_normalize_shipping_address'
                    value='1'
                    class='checkbox'
                    onclick='window.avataxReloadShippingMethods();'
                    " . $checked . ">
            <label for='allow_normalize_shipping_address'>Disable normalization of shipping address</label>
            <script type='application/javascript'>
                window.avataxReloadShippingMethods = function() {
                    //debugger;
                    var isChecked = 0;
                    if ($('allow_normalize_shipping_address').checked){
                        isChecked = 1;
                    }
                    var request = new Ajax.Request(
                        '/avatax/normalization/update',
                        {
                            method:'post',
                            parameters:{flag:isChecked,multishipping:1},
                            onSuccess: function(response){
                                debugger;
                                window.location.href = window.location.href;
                            }
                        }
                    );
                };
            </script>
        </p>";

        return $html;
    }

    /**
     * Get HTML content of checkbox that can be used to disable normalization on store front
     * for onepage checkout
     *
     * @param null $flag
     *
     * @return string
     */
    public function getOnepageDisableNormalizationCheckbox($flag = null)
    {
        $checked = $flag ? "checked='checked'" : '';

        $loaderImgUrl = $this->getSkinUrl('images/opc-ajax-loader.gif');
        $html = "<p>
            <input type='checkbox'
                    name='allow_normalize_shipping_address'
                    id='allow_normalize_shipping_address'
                    value='1'
                    class='checkbox'
                    onclick='checkout.avataxReloadShippingMethodsAccordingNormalization();'
                    " . $checked . ">
            <label for='allow_normalize_shipping_address'>Disable normalization of shipping address</label>
            <span class='please-wait allow-normalize' id='allow-normalize-please-wait' style='display: none;'>
                <img src='$loaderImgUrl' alt='Updating addresses...' title='Updating addresses...' class='v-middle'>
                Updating addresses...
            </span>
            <style type='text/css'>
                .allow-normalize {
                    margin-top: -1px;
                    height: 22px;
                }
            </style>
            <script type='application/javascript'>
                //debugger;
                Checkout.prototype.avataxEnableContinue = function(step, isEnabled){
                    var container = $(step+'-buttons-container');
                    if(isEnabled){
                        container.removeClassName('disabled');
                        container.setStyle({opacity:1});
                    }
                    else {
                        container.addClassName('disabled');
                        container.setStyle({opacity:.5});
                    }
                    this._disableEnableAll(container, !isEnabled);
                };

                Checkout.prototype.avataxIsNormalizationAllowed = function() {
                    var isChecked = 0;
                    var allowNormilize = $('allow_normalize_shipping_address');
                    if (allowNormilize && allowNormilize.checked){
                        isChecked = 1;
                    }

                    return isChecked;
                };

                Checkout.prototype.avataxResetBillingAndShippingProgress = function() {
                    //debugger;
                    if (this.resetPreviousSteps != undefined && this.resetPreviousSteps != null) {
                        var step = this.currentStep;
                        this.currentStep = 'billing';
                        this.resetPreviousSteps();
                        this.currentStep = step;
                    }
                };

                Checkout.prototype.avataxUpdateProgress = function() {
                    if (this.reloadStep != undefined && this.reloadStep != null) {
                        this.reloadStep('billing');
                        this.reloadStep('shipping');
                    } else if(this.reloadProgressBlock != undefined && this.reloadProgressBlock != null) {
                        this.reloadProgressBlock();
                    }
                };

                Checkout.prototype.avataxSetNormalizationPleaseWait = function() {
                    if ($('allow-normalize-please-wait')) {
                        $('allow-normalize-please-wait').show();
                    }
                };

                Checkout.prototype.avataxReloadShippingMethodsAccordingNormalization = function() {
                    //debugger;
                    this.avataxSetNormalizationPleaseWait();
                    this.avataxEnableContinue('shipping-method', false);

                    var isChecked = this.avataxIsNormalizationAllowed();

                    var request = new Ajax.Request(
                        '/avatax/normalization/update',
                        {
                            method:'post',
                            parameters:{flag:isChecked},
                            onSuccess: function(response){
                                //debugger;
                                checkout.avataxResetBillingAndShippingProgress();

                                //wrap method
                                Checkout.prototype.setStepResponse = Checkout.prototype.setStepResponse.wrap(function(parentMethod, response){
                                    //debugger;

                                    var section = response.goto_section;
                                    switch(section) {
                                        case 'shipping': {
                                                response.goto_section = 'shipping_method';
                                                parentMethod(response);
                                                this.avataxEnableContinue('shipping-method', false);
                                                shipping.save();
                                            }
                                            break;
                                        case 'shipping_method': {
                                                parentMethod(response);

                                                //unwrap method
                                                Checkout.prototype.setStepResponse = parentMethod;

                                                this.avataxUpdateProgress();
                                                this.avataxEnableContinue('shipping-method', true);
                                            }
                                            break;
                                    }
                                });
                                billing.save();
                            }
                        }
                    );
                };
            </script>
        </p>";

        return $html;
    }

    /**
     * Retrieve url of skins file
     *
     * @param   string $file path to file in skin
     * @param   array $params
     * @return  string
     */
    public function getSkinUrl($file = null, array $params = array())
    {
        return Mage::getDesign()->getSkinUrl($file, $params);
    }
}
