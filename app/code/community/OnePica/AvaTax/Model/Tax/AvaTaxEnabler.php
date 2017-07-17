<?php
/**
 * OnePica_AvaTax
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0), a
 * copy of which is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   OnePica
 * @package    OnePica_AvaTax
 * @author     OnePica Codemaster <codemaster@onepica.com>
 * @copyright  Copyright (c) 2016 One Pica, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * Singleton Class OnePica_AvaTax_Model_Tax_AvaTaxEnabler
 */
class OnePica_AvaTax_Model_Tax_AvaTaxEnabler
{
    /**
     * Is Switched flag. Shows if initTaxCollector method was executed.
     *
     * @var bool
     */
    protected $_isSwitched = false;

    /**
     * Init Tax Collector
     *
     * @param   int $storeId
     * @return  $this
     */
    public function initTaxCollector($storeId)
    {
        if (!$this->_isSwitched) {
            if (Mage::getStoreConfig('tax/avatax/action', $storeId) != OnePica_AvaTax_Model_Service_Abstract_Config::ACTION_DISABLE) {
                Mage::getConfig()->setNode('global/sales/quote/totals/tax/class', 'avatax/sales_quote_address_total_tax');
            }

            $this->_isSwitched = true;
        }

        return $this;
    }
}