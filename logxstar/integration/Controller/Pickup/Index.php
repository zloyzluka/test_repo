<?php
namespace Logxstar\Integration\Controller\Pickup;
class Index extends \Magento\Framework\App\Action\Action
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
        $data = $this->helper->getPickupPoints($request->getParam('method'));
        $this->getResponse()->setBody(json_encode($data));
    }
}