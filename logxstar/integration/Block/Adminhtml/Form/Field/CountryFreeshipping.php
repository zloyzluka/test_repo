<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Logxstar\Integration\Block\Adminhtml\Form\Field;

use Magento\Framework\DataObject;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Class CountryCreditCard
 */
class CountryFreeshipping extends AbstractFieldArray
{
    /**
     * @var Countries
     */
    protected $countryRenderer = null;

    /**
     * @var CcTypes
     */
    protected $ccTypesRenderer = null;

    /**
     * Returns renderer for country element
     *
     * @return Countries
     */
    protected function getCountryRenderer()
    {
        if (!$this->countryRenderer) {
            $this->countryRenderer = $this->getLayout()->createBlock(
                Countries::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->countryRenderer;
    }

    /**
     * Prepare to render
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'country_id',
            [
                'label'     => __('Country'),
                'renderer'  => $this->getCountryRenderer(),
            ]
        );
        $this->addColumn(
            'free_value',
            [
                'label' => __('Free shipping value'),
                'style' => 'width:50px'
            ]
        );
        $this->addColumn(
            'free_message',
            [
                'label' => __('Freeshipping Message'),
                'style' => 'min-width:250px'
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Rule');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $country = $row->getCountryId();
        $options = [];
        if ($country) {
            $options['option_' . $this->getCountryRenderer()->calcOptionHash($country)]
                = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    protected function _toHtml()
    {
        $res = parent::_toHtml();
        $res .='<style rel="stylesheet" type="text/css">';
        $res .='#carriers_logxstar .label{width:20%}';
        $res .='#carriers_logxstar .value{width:67%}';
        $res .='</style>';
        return $res;
    }

}
