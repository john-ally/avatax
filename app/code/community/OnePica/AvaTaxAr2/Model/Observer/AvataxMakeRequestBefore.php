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
 * @copyright  Copyright (c) 2015 One Pica, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * Avatax Observer LoadAvaTaxExternalLib
 *
 * @category   OnePica
 * @package    OnePica_AvaTax
 * @author     OnePica Codemaster <codemaster@onepica.com>
 */
class OnePica_AvaTaxAr2_Model_Observer_AvataxMakeRequestBefore extends Mage_Core_Model_Abstract
{
    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function execute(Varien_Event_Observer $observer)
    {
        $this->_addCertUpdatedDate($observer);

        return $this;
    }

    /**
     * Add magentoCertificateUpdatedDate param to recalculate tax
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    protected function _addCertUpdatedDate($observer)
    {
        /** @var \GetTaxRequest $request */
        $request = $observer->getRequest();

        if ($this->_getAvataxSession()->getCertUpdatedDate()) {
            $request->magentoCertificateUpdatedDate = $this->_getAvataxSession()->getCertUpdatedDate();
        }

        if (!$this->_getCustomerSession()->isLoggedIn() && $this->_getAvataxSession()->getCustomerNumber()) {
            $request->setCustomerCode($this->_getAvataxSession()->getCustomerNumber());
        }

        return $this;
    }

    /**
     * @return \OnePica_AvaTax_Model_Session
     */
    protected function _getAvataxSession()
    {
        return Mage::getSingleton('avatax/session');
    }
    /**
     * @return \Mage_Customer_Model_Session
     */
    protected function _getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }
}
