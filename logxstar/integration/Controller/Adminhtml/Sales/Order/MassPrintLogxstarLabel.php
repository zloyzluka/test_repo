<?php
namespace Logxstar\Integration\Controller\Adminhtml\Sales\Order;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Ui\Component\MassAction\Filter;

class MassPrintLogxstarLabel extends \Magento\Sales\Controller\Adminhtml\Order
{
    protected $shipmentLoader;
    protected $helper;
    protected $filter;

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
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader,
        Filter $filter
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
        $this->filter = $filter;
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
        $ids = $this->getRequest()->getParam('selected');
        $pdf = new \Zend_Pdf();
        $page_added = false;
        $order_inc_ids = array();
        foreach($ids as $id){
            $order = $this->orderRepository->get($id);
            if ($order->canShip()) {
                $this->helper->createShipment($order);
            }
            $order_inc_ids[] = $order->getIncrementId();
            $tmp_pdf = $this->helper->requestLabel($order);
            if (!($tmp_pdf instanceof \Zend_Pdf)){
                continue;
            }
            foreach ($tmp_pdf->pages as $page) {
                $page_added = true;
                $pdf->pages[] = clone $page;
            }
        }
        if($page_added){
            $pdfContent = $pdf->render();
            return $this->_fileFactory->create(
                'ShippingLabels(' . implode('-',$order_inc_ids) . ').pdf',
                $pdfContent,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } else {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath($this->getComponentRefererUrl());
            return $resultRedirect;
        }
    }
    protected function getComponentRefererUrl()
    {
        return $this->filter->getComponentRefererUrl()?: 'customer/*/index';
    }
}
