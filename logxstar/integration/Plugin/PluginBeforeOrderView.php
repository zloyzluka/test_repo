<?php
namespace Logxstar\Integration\Plugin;

class PluginBeforeOrderView
{
    protected $urlbuilder;
    function __construct(\Magento\Framework\View\Element\Template\Context $context)
    {
        $this->urlbuilder = $context->getUrlBuilder();
    }

    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $view)
    {
        $params = array();
        $params['order_id'] = $view->getOrderId();
        $view->addButton(
            'logxstar_print_label',
            [
                'label' => __('Print Label'),
                'class' => 'myclass',
                'onclick' => 'setLocation(\'' . $this->getPrintLabelUrl($params) . '\')'
            ]
        );
    }
    public function getPrintLabelUrl($params){
        return $this->urlbuilder->getUrl('logxstar/sales_order/printlabels',$params);
    }
}