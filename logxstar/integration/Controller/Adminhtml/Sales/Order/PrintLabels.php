<?php
namespace Logxstar\Integration\Controller\Adminhtml\Sales\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class PrintLabels extends \Magento\Sales\Controller\Adminhtml\Order
{
    protected $shipmentLoader;
    protected $helper;

    public function __construct(
        Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->_fileFactory = $fileFactory;
        $this->_translateInline = $translateInline;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->shipmentLoader = $shipmentLoader;
        $this->helper = $context->getObjectManager()->get('Logxstar\Integration\Helper\Data');
        parent::__construct($context,$coreRegistry,$fileFactory,$translateInline,$resultPageFactory,
            $resultJsonFactory,$resultLayoutFactory,$resultRawFactory,$orderManagement,$orderRepository,$logger);
    }

    /**
     * View order detail
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $order = $this->_initOrder();
        $shipment = $order->getShipmentsCollection()->getFirstItem();
        if(!$shipment->getEntityId()){
            $shipment = $this->helper->createShipment($order);
            $shipment->unsetData('tracks');
            $order->getShipmentsCollection()->addItem($shipment);
        }

        $pdf = $this->helper->requestLabel($order);
        if($pdf){
            $pdfContent = $pdf->render();
            return $this->_fileFactory->create(
                'ShippingLabel(' . $order->getIncrementId() . ').pdf',
                $pdfContent,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        }

    }
}
