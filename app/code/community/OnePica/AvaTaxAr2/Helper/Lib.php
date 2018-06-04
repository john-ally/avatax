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
class OnePica_AvaTaxAr2_Helper_Lib extends Mage_Core_Helper_Abstract
{
    /**
     * Returns the path to the AvaTax SDK lib directory.
     *
     * @return string
     */
    public function getLibPath()
    {
        return Mage::getBaseDir('lib') . DS . 'AvaTaxRestV2';
    }

    /**
     * Loads a class from the AvaTax library.
     *
     * @return OnePica_AvaTax_Helper_Data
     */
    public function loadClasses()
    {
        $classFile = $this->getLibPath() . DS . 'src' . DS . 'AvaTaxClient.php';
        require_once $classFile;
        return $this;
    }
}
