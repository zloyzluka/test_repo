<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Logxstar\Integration\Block\Adminhtml\Form\Field;

use PayPal\Braintree\Helper\Country;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * Class Countries
 */
class Countries extends Select
{
    /**
     * @var Country
     */
    private $countryHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Country $countryHelper
     * @param array $data
     */
    public function __construct(Context $context, Country $countryHelper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->countryHelper = $countryHelper;
    }
    
    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->countryHelper->getCountries());
        }
        return parent::_toHtml();
    }

    /**
     * Sets name for input element
     * 
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
    public function getExtraParams(){
        return 'style="width:50px"';
    }
}
