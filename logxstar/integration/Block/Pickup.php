<?php
namespace Logxstar\Integration\Block;
class Pickup extends \Magento\Checkout\Block\Onepage
{
    public function getWeekDays() {
        $days = array(
            array(
                'name'=>'monday',
                'label'=>__('monday')
            ),
            array(
                'name'=>'tuesday',
                'label'=>__('tuesday')
            ),
            array(
                'name'=>'wednesday',
                'label'=>__('wednesday')
            ),
            array(
                'name'=>'thursday',
                'label'=>__('thursday')
            ),
            array(
                'name'=>'friday',
                'label'=>__('friday')
            ),
            array(
                'name'=>'saturday',
                'label'=>__('saturday')
            ),
            array(
                'name'=>'sunday',
                'label'=>__('sunday')
            ),
        );
        return json_encode($days);
    }
    public function getJsLayout()
    {
        return \Zend_Json::encode($this->jsLayout);
    }
}