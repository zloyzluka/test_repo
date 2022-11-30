<?php

namespace Logxstar\Integration\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Framework\Xml\Security;

/**
 * Flat rate shipping model
 *
 * @category   Mage
 * @package    Mage_Shipping
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Logxstar extends \Magento\Shipping\Model\Carrier\AbstractCarrierOnline implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{

    protected $_code = 'logxstar';
    protected $_isFixed = true;
    protected $_activeFlag = 'active';
    protected $_defaultGatewayUrl = 'https://os.logxstar.com/';
    protected static $_quotesCache = array();
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Shipping\Model\Order\TrackFactory
     */
    protected $_ordertrackFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory
     */
    protected $_trackCollectionFactory;
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    protected $_storeManager; 

    protected $lgxstrHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Order\TrackFactory $ordertrackFactory,
        \Magento\Shipping\Model\ResourceModel\Order\Track\CollectionFactory $trackCollectionFactory,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Logxstar\Integration\Helper\Data $lgxstrHelper,
        \Magento\Framework\Pricing\Helper\Data $pricing,
        array $data = []
    )
    {
        $this->pricingHelper = $pricing;
        $this->lgxstrHelper = $lgxstrHelper;
        $this->_ordertrackFactory = $ordertrackFactory;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_storeManager = $storeManager; 
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_customerSession = $customerSession;
        $this->_trackCollectionFactory = $trackCollectionFactory;
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateResultFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    public function setActiveFlag($code = 'active')
    {
        $this->_activeFlag = $code;
        return $this;
    }


    /**
     * Enter description here...
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag($this->_activeFlag)) {
            return false;
        }
        
        
        if($this->getConfigFlag('disable_for_stores')) {
            $disableForStores = $this->getConfigData('disable_for_stores');
            $codes = explode(',', $disableForStores);
            if(in_array($this->_storeManager->getStore()->getCode(), $codes)) {
                return false;
            }
        }
        $requestLgxstr = clone $request;

        $this->setRequest($requestLgxstr);
        $this->_result = $this->_doRequest();
        //$this->_updateFreeMethodQuote($request);

        return $this->getResult();
    }

    /**
     * @return \Magento\Quote\Model\Quote\Address\RateRequest
     */
    public function getRequest()
    {
        return $this->_request;
    }

    public function setRequest($request)
    {
        $this->_request = $request;

        $shipment_detail = new \Magento\Framework\DataObject();
        $service_points = new \Magento\Framework\DataObject();

        $street['street'] = $request->getDestStreet();
        if(!empty($street)) {
            $houseNr =  $this->lgxstrHelper->extractHouseNumber($street);    
            if(!empty($houseNr)) {
                $service_points->setData('houseNr',$houseNr);    
            }
        }

        $shipment_detail->setId(1);

        $weight_settings = $this->getConfigData('weight_settings');
        switch ($weight_settings) {
            case 'use_count':
                
                $shippingWeight = $request->getPackageQty();
                $products = $request->getAllItems();

                $height = 0;
                $width  = 0;
                $depth  = 0;

                foreach($products as $product)
                {
                   $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                   $product = $objectManager->create('Magento\Catalog\Model\Product')->load($product->getProduct()->getId());
                    if($product->getPrice() == 0) {
                        continue; //skip basic item
                    }
                    $productSize = $product->getData('afmetingopen');
                    $productSizeInfo = explode('x', $productSize);
                    $depth  += (isset($productSizeInfo[0]) && !empty($productSizeInfo[0]))?$productSizeInfo[0]:0;
                    $width  += isset($productSizeInfo[1])?$productSizeInfo[1]:0;
                    $height += isset($productSizeInfo[2])?$productSizeInfo[2]:0;
                }


                $shipment_detail->addData(array(
                    'width'  => (!empty($width))?$width:10, 
                    'height' => (!empty($height))?$height:10, 
                    'depth'  => (!empty($depth))?$depth:10
                ));


                break;

            case 'use_weight':
                $shippingWeight = $request->getPackageWeight();
                $shipment_detail->addData(array(
                    'width'  => (!empty($request->getPackageWidth()))?$request->getPackageWidth():10, 
                    'height' => (!empty($request->getPackageHeight()))?$request->getPackageHeight():10, 
                    'depth'  => (!empty($request->getPackageDepth()))?$request->getPackageDepth():10
                ));
                break;

            case 'use_hardcoded':
                
            default:
                $shippingWeight = 1;
                $shipment_detail->addData(array('width' => 10, 'height' => 10, 'depth' => 10));
                break;
        }
        
        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = 'NL';
        }
        $shipment_detail->setWeight($shippingWeight);
        $shipment_detail->setValue((string)round($request->getPackageValue(), 2));
        $shipment_detail->setData('country_code', $destCountry);
        
        if($this->getConfigFlag('use_business')) {
            $shipment_detail->setData('business', false);
            if($this->_customerSession->isLoggedIn()){
                $groupId = $this->_customerSession->getCustomer()->getGroupId();
            }
            $businessIds = explode(',', $this->getConfigData('business_id'));
            if(isset($groupId) && in_array($groupId, $businessIds)) {
                $shipment_detail->setData('business', true);   
            }
        }

        $postcode = $request->getDestPostcode();
        if (!$postcode) {
            $postcode = '';
        }
        
        if ($postcode) {
            $service_points->setPostCode(str_replace(' ', '', $postcode));
        }

        $service_points->setCountryCode($destCountry);

        $r = array(
            'shipment_details' => array($shipment_detail->getData()),
            'service_points' => $service_points->getData()
        );
        file_put_contents('/tmp/tmp.log', print_r($r, true), FILE_APPEND);
        $this->_rawRequest = $r;
        return $this;
    }

    protected function _doRequest()
    {
        $http_code = null;
        $request = $this->_rawRequest;
        $responseBody = null;//$this->_getCachedQuotes($request);
        if ($responseBody === null) {
            $debugData = array('request' => $request);
            try {
                $url = $this->getConfigData('logxstar_host');
                if (!$url) {
                    $url = $this->_defaultGatewayUrl;
                }
                $logxstarApiKey = $this->getConfigData('logxstar_key');
                $url = rtrim($url, '/');
                $url .= '/api/v1.1/shipping/carrier-options';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER,
                    array(
                        "api-key: " . $logxstarApiKey
                    )
                );
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
                $responseBody = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $debugData['result'] = $responseBody;
                file_put_contents('/tmp/r.log', $responseBody);
                $this->_setCachedQuotes($request, $responseBody);
            } catch (\Exception $e) {
                $debugData['result'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
                $responseBody = '';
            }
            $this->_debug($debugData);
        }
        if ($http_code == 200 || (!$http_code && $responseBody)) {
            return $this->_parseResponse($responseBody);
        }
    }

    public function getAllowedMethods()
    {
        return array('logxstar' => $this->getConfigData('title'));
    }

    protected function _getCachedQuotes($requestParams)
    {
        $key = $this->_getQuotesCacheKey($requestParams);
        return isset(self::$_quotesCache[$key]) ? self::$_quotesCache[$key] : null;
    }

    protected function _getQuotesCacheKey($requestParams)
    {
        if (is_array($requestParams)) {
            $requestParams = implode(',', array_merge(
                    array($this->getCarrierCode()),
                    array(serialize($requestParams)))
            );
        }
        return crc32($requestParams);
    }

    protected function _setCachedQuotes($requestParams, $response)
    {
        $key = $this->_getQuotesCacheKey($requestParams);
        //file_put_contents('/tmp/key.log',  $key);
        self::$_quotesCache[$key] = $response;
        return $this;
    }

    protected function _parseResponse($response)
    {
        $r = $this->_rawRequest;
        $quote_errors = array();
        $errorTitle = 'Unable to retrieve quotes';
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $session = $objectManager->create('Magento\Customer\Model\Session');
        $options = json_decode($response);
        $default_points = [];
        $result = $this->_rateResultFactory->create();

        if (empty($options) || isset($options->error) || !$options->carrier_options) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier('dhl');
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            $result->append($error);
        } else {
            $methods_points = [];
            $country_freeshipping = $this->getConfigData('country_freeshipping');
            $dest_country_id = $this->getRequest()->getData('dest_country_id');
            if ($country_freeshipping) {
                $country_freeshipping = unserialize($country_freeshipping);
            }
            if (!is_array($country_freeshipping)) {
                $country_freeshipping = array();
            }
            $methods = [];
            $time_frames =[];
            foreach ($options->carrier_options as $key => $rate_data) {
                $method_points = false;
                $defaultPoints = [];
                $pickup = '';
                $method = '_' . $rate_data->carrier . '__' . $rate_data->option_code . '__' . $rate_data->option_id;
                if(isset($options->service_points->{$rate_data->carrier}) && $rate_data->pickupPoints == true){
                    $method_points = $options->service_points->{$rate_data->carrier};
                    $pickup = '__lgxspickup';
                    $method .= $pickup;
                    //$method = $this->lgxstrHelper->getMethodShortName($method, $pickup);
                }elseif(isset($options->time_frames->{$rate_data->carrier}) && !empty($rate_data->additional_parameters->attached_timeframes)) {
                    $pickup = '__logxstardelivery';
                    $method .= $pickup;
                    //$method = $this->lgxstrHelper->getMethodShortName($method, $pickup);
                    foreach ($options->time_frames->{$rate_data->carrier} as $date => $option) {
                        $exist = array_intersect_key((array) $option, array_flip($rate_data->additional_parameters->attached_timeframes));
                        if($exist){
                            $time_frames[$date][]= array(
                                'carrier_code' => $rate_data->carrier,
                                'carrier_id'   => $rate_data->option_id,
                                'carrier_short'=> $method,
                                'time_frames'  => $exist
                            );     
                        }
                    }
                } else {
                    //$method = $this->lgxstrHelper->getMethodShortName($method, $pickup);  
                }

                $rate = $this->_rateMethodFactory->create();
                $rate->setCarrier($this->_code);
                $rate->setCarrierTitle($this->getConfigData('title'));
                //$method = '_' . $rate_data->carrier . '__' . $rate_data->option_code . '__' . $rate_data->option_id . $pickup;
                $methods[] = [$method, $pickup];

                
                if ($method_points) {
                    $methods_points[$method]['points'] = $method_points;
                    $methods_points[$method]['key'] =  $rate_data->carrier;
                    $default_points[$rate_data->option_id] = $method_points[0]->id;
                }

                $rate->setMethod($method);
                $carrier_title = strtoupper($rate_data->carrier);
                if ($carrier_title == 'DPD_GERMANY') {
                    $carrier_title = 'DPD';
                }
                $method_title = $carrier_title . ' ' . $rate_data->option_title;
                $rate->setMethodTitle($method_title);

                $price = $rate_data->price;
                $free_message = '';
                $compare_with = $this->getRequest()->getPackageValue();
                if ($this->getConfigData('freeshipping_includes_tax') == '1') {
                    $all_items = $this->getRequest()->getData('all_items');
                    $compare_with = 0;
                    foreach ($all_items as $item) {
                        $compare_with += $item->getData('row_total_incl_tax');
                    }
                }
                if (isset($country_freeshipping[$dest_country_id]) && (float)$price) {
                    if (is_numeric($country_freeshipping[$dest_country_id]['free_value'])) {
                        $free_value = $country_freeshipping[$dest_country_id]['free_value'];
                        if ($compare_with >= $free_value) {
                            $price = 0;
                        }
                    }
                }
                $rate->setCost($price);
                $rate->setPrice($price);
                $result->append($rate);
            }
            ksort($time_frames);
            $key = md5($r['service_points']['post_code']);
            //$this->lgxstrHelper->saveMethodsOptions($methods);
            $session->setData('logxstar_default_points', $default_points);
            $logxstar_pickuppoints = $session->getData('logxstar_pickuppoints');
            $logxstar_pickuppoints[$key] = ['methods_points'=>$methods_points,'delivery_data'=>$time_frames];
            $session->setData('logxstar_pickuppoints', $logxstar_pickuppoints);
        }

        return $result;
    }

    public function getResult()
    {
        return $this->_result;
    }

    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        xdebug_break();
    }

    public function getTrackingInfo($number)
    {

        $track_coll = $this->_trackCollectionFactory->create()
            ->addFieldToFilter('track_number', array('eq' => $number));
        $track_coll->load();
        $track = $track_coll->getFirstItem();

        $result = $this->_trackFactory->create();

        $status = $this->_trackStatusFactory->create();
        $status->setCarrier('Logxstar');
        $status->setCarrierTitle($this->getConfigData('title'));
        $status->setTracking($number);
        $status->setPopup(1);
        $status->setUrl($track->getData('description'));
        $result->append($status);
        return $status;
    }

    public function isTrackingAvailable()
    {
        return true;
    }

    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        //Skip by item validation if there is no items in request
        if (!count($this->getAllItems($request))) {
            return $this;
        }

        $maxAllowedWeight = (double)$this->getConfigData('max_package_weight');
        $errorMsg = '';
        $configErrorMsg = $this->getConfigData('specificerrmsg');
        $defaultErrorMsg = __('The shipping module is not available.');
        $showMethod = $this->getConfigData('showmethod');

        /** @var $item \Magento\Quote\Model\Quote\Item */
        foreach ($this->getAllItems($request) as $item) {
            $product = $item->getProduct();
            if ($product && $product->getId()) {
                $weight = $product->getWeight();
                $stockItemData = $this->stockRegistry->getStockItem(
                    $product->getId(),
                    $item->getStore()->getWebsiteId()
                );
                $doValidation = true;

                if ($stockItemData->getIsQtyDecimal() && $stockItemData->getIsDecimalDivided()) {
                    if ($stockItemData->getEnableQtyIncrements() && $stockItemData->getQtyIncrements()
                    ) {
                        $weight = $weight * $stockItemData->getQtyIncrements();
                    } else {
                        $doValidation = false;
                    }
                } elseif ($stockItemData->getIsQtyDecimal() && !$stockItemData->getIsDecimalDivided()) {
                    $weight = $weight * $item->getQty();
                }

                if ($doValidation && $weight > $maxAllowedWeight) {
                    $errorMsg = $configErrorMsg ? $configErrorMsg : $defaultErrorMsg;
                    break;
                }
            }
        }

        if ($errorMsg && $showMethod) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($errorMsg);

            return $error;
        } elseif ($errorMsg) {
            return false;
        }

        return $this;
    }
}

