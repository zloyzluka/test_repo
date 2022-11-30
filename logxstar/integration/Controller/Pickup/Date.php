<?php
namespace Logxstar\Integration\Controller\Pickup;
class Date extends \Magento\Framework\App\Action\Action
{
    protected $helper = null;
    public function __construct(
        \Logxstar\Integration\Helper\Data $helper,
        \Magento\Framework\App\Action\Context $context
    )
    {
        $this->helper = $helper;
        parent::__construct($context);
    }
    public function execute()
    {
        $request = $this->getRequest();
        $date = $request->getPostValue('date');
        $time = $request->getPostValue('time');
        $carrier_id = $request->getPostValue('carrier_id');
        $data = $this->helper->getSaveDeliveryDate(['date'=>$date,'time'=>$time,'carrier_id'=>$carrier_id]);
        $this->getResponse()->setBody(json_encode($data));
    }
}