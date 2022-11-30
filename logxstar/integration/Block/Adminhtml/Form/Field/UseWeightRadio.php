<?php
namespace Logxstar\Integration\Block\Adminhtml\Form\Field;

class UseWeightRadio implements \Magento\Framework\Option\ArrayInterface {

    public function toOptionArray()
    {
        return [
        	['value' => 'use_hardcoded', 'label' => __('Use default (1kg)')], 
        	['value' => 'use_weight', 'label' => __('Use real weight')],
        	['value' => 'use_count', 'label' => __('Use amount of products instead of weight')]];
    }

} 