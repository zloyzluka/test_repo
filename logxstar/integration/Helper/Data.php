<?php
namespace Logxstar\Integration\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $customer_session;
    public $scopeConfig;
    protected $trackFactory;
    protected $convert;
    protected $_resource;
    protected $collectionFactory;
    protected $configWriter;

    public function __construct(
        Context $context,
        \Magento\Customer\Model\Session $customer_session,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Sales\Model\Convert\Order $convert,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        WriterInterface $configWriter
    )
    {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->customer_session = $customer_session;
        $this->trackFactory = $trackFactory;
        $this->convert = $convert;
        $this->_resource = $resource;
        $this->collectionFactory = $collectionFactory;
    }

    public function apiSendOrderToLogxstar($order)
    {
        $shippingAddressData = $order->getShippingAddress()->getData();
        
        $data = array();
        $logxstarApiKey = $this->scopeConfig->getValue('carriers/logxstar/logxstar_key');
        $logxstarUrl = $this->scopeConfig->getValue('carriers/logxstar/logxstar_host');
        $logxstarUrl = rtrim($logxstarUrl, '/') . '/api/v1/shipping';
        $shipping_method = $order->getData('shipping_method');
       //$shipping_method = $this->getMethodOption($shipping_method);
        $shipping_method_parts = explode('__', $shipping_method);

        if (!$logxstarApiKey || !$logxstarUrl) {
            return;
        }
        
        $address = $this->parseStreetLine($shippingAddressData);

        // mandatory address fields
        $data['country'] = $shippingAddressData['country_id'];
        $data['full_name'] = $shippingAddressData['firstname'] . ' ' . $shippingAddressData['lastname'];
        $data['street'] = $address['street'];
        $data['street_number'] = $address['houseNr'];
        $data['suffix'] = $address['suffix'];
        $data['post_code'] = str_replace(' ', '', $shippingAddressData['postcode']);
        $data['city'] = $shippingAddressData['city'];
        // optional address fields
        $data['email'] = $shippingAddressData['email'];
        $data['phone'] = $shippingAddressData['telephone'];
        $data['lastname'] = $shippingAddressData['lastname'];
        $data['name'] = $shippingAddressData['firstname'];
        $data['company'] = $shippingAddressData['company'];
        $data['resize_to_a6'] = true;
//lgxspickup
        $session = $this->customer_session;

        $data['shipping_carrier_option'] = null;

        if (isset($shipping_method_parts[1]) && $shipping_method_parts[1] != 'lgxspickup') {
            $data['shipping_carrier_id'] = $shipping_method_parts[1];
        }
        if (isset($shipping_method_parts[3])) {
            $data['shipping_carrier_option'] = $shipping_method_parts[3];
        }

        $default_points = $session->getData('logxstar_default_points');
        
        if(isset($default_points[$data['shipping_carrier_option']])) {
            $order->setData('logxstar_pickuppoint', $default_points[$data['shipping_carrier_option']]);
            $data['pickup_point'] = $order->getData('logxstar_pickuppoint');    
        }

        if ($session->hasData('logxstar_pickuppoint_selected')) {
            $order->setData('logxstar_pickuppoint', $session->getData('logxstar_pickuppoint_selected'));
            $data['pickup_point'] = $order->getData('logxstar_pickuppoint');
        }

        

        if($session->hasData('logxstar_selected_delivery_date')) {
            $delivery_details = $session->getData('logxstar_selected_delivery_date');
            if($delivery_details['carrier_id'] == $data['shipping_carrier_option']) {
                $data['delivery_date'] = $delivery_details['date'];
                $data['delivery_time'] = $delivery_details['time'];
                $order->setData('logxstar_selected_date', $data['delivery_date']);
            } 
        }

        $weight_settings = $this->scopeConfig->getValue('carriers/logxstar/weight_settings');

        switch ($weight_settings) {
            case 'use_count':

            case 'use_weight':
                $totalWeight = $order->getWeight();
                break;

            case 'use_hardcoded':
                
            default:
                $totalWeight = 1;
                break;
        }
        if (empty($totalWeight)) {
            $totalWeight = 1;   
        }

        $shipmentProducts = [];
        $products = [];
        
        $items = $order->getAllItems();
        foreach ($items as $item) {
            
            $product = $item->getProduct();
            
            if($product->getPrice() == 0) {
                continue; //skip basic item
            }
            $shipmentProducts[] = [
                "productName" => $product->getName(),
                "productUrl" => $product->getProductUrl(false),
                "basicPrice" => $product->getPrice()*100,
                "productImageUrl"  => 'https://'.$_SERVER['HTTP_HOST'].'/pub/media/catalog/product/'.$product->getSmallImage(),
                "salesPrice" => $product->getFinalPrice()*100,
                "itemSku" => $product->getSku(),
                "itemEan" => null,
                "quantityOrdered" => (int) $item->getQtyOrdered()
            ];
        }
        

        if(!empty($shipmentProducts)) {
            $data['shipmentProducts'] = $shipmentProducts;
        }
        
        

        // required shipment fields
        $data['total_weight'] = $totalWeight;
        $data['parcel_reference'] = $order->getIncrementId();
        // optional shipment fields
        //$data['parcel_number_of_labels'] = '';
        //$data['instruction_on_label'] = '';
        $data['email_address'] = $shippingAddressData['email'];
        $data['phone_number'] = $shippingAddressData['telephone'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $logxstarUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                "api-key: " . $logxstarApiKey
            )
        );
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
       
        $status = false;
        $logxstarInternalReference = null;
        $errorCode = null;
        $errorMessage = null;
        if ($response || !curl_errno($ch)) {
            if ($info['http_code'] == 201) { // everything is ok, shipment cretead
                $status = true;
                $response = json_decode($response, true);
                $logxstarInternalReference = $response['internal_reference'];
                $order->setData('internal_reference', $logxstarInternalReference);
            } else { // errors in shipment data or on logxstar side
                $errorCode = $info['http_code'];
                file_put_contents('/tmp/tmp.log',json_decode($response, true));
                $errorMessage = json_decode($response, true)['error'];
                //Mage::log($errorCode . $errorMessage, null, 'logxstar.log', true);
            }
        } else {
            // error in transport
            $errorCode = curl_errno($ch);
            $errorMessage = curl_error($ch);
            //Mage::log($errorCode . $errorMessage, null, 'logxstar.log', true);
        }
        curl_close($ch);

        return $response;
    }

    public function parseStreetLine($shippingAddressData) {

        $address = trim($shippingAddressData["street"]);
        while(strpos($address, '  ') !== false) {
            $address = str_replace('  ', ' ', $address);
        }

//        $street = '';
        $number = '';
        $suffix = '';

        $address = preg_replace('/^(.*)[,\.]([0-9]+)/', '$1 $2', $address);
        $addressSplit = explode(' ', $address);
        foreach($addressSplit as &$word) {
            $word = trim($word, ',');
        }
        $lastKey     = count($addressSplit) - 1;

        if(
            isset( $addressSplit[$lastKey] )
            && isset( $addressSplit[$lastKey-1] )
            && preg_match('/^[0-9a-zA-Z\W]{1,6}$/', $addressSplit[$lastKey])
            && preg_match('/^[0-9]+$/', $addressSplit[$lastKey-1])
        ) {
            $number = $addressSplit[$lastKey-1];
            $suffix = $addressSplit[$lastKey];
            unset( $addressSplit[$lastKey], $addressSplit[$lastKey-1] );
        } elseif(
            isset( $addressSplit[$lastKey-1], $addressSplit[$lastKey-2] )
            && preg_match('/^[0-9]+$/', $addressSplit[$lastKey-1])
            && preg_match('/^[0-9]+$/', $addressSplit[$lastKey-2])
        ) {
            $number = $addressSplit[$lastKey-2];
            $suffix = $addressSplit[$lastKey-1];
            unset( $addressSplit[$lastKey-1], $addressSplit[$lastKey-2] );
        } elseif(
            isset($addressSplit[$lastKey])
            && preg_match('/^([0-9]+)(.*)$/', $addressSplit[$lastKey], $numberMatches)
        ) {
            $number = $numberMatches[1];
            $suffix = $numberMatches[2];
            unset( $addressSplit[$lastKey] );
        } elseif(
            isset($addressSplit[$lastKey-1])
            && preg_match('/^([0-9]+)(.*)$/', $addressSplit[$lastKey-1], $numberMatches)
        ) {
            $number = $numberMatches[1];
            $suffix = $numberMatches[2];
            unset( $addressSplit[$lastKey] );
        } elseif(
            isset($addressSplit[$lastKey-1])
            && preg_match('/^([0-9]+)(.*)$/', $addressSplit[0], $numberMatches)
        ) {
            $number = $numberMatches[1];
            $suffix = $numberMatches[2];
            unset( $addressSplit[0] );
        } else {
            //TODO LOG return false;
        }
        $street = implode(' ', $addressSplit);

        return [
            'street'  => $street,
            'houseNr' => $number,
            'suffix'  => $suffix,
        ];
    }

    public function extractHouseNumber($shippingAddressData)
    {
        if (preg_match('/([^\d]+)\s?([\d]+)\s?([a-zA-Z]+)?/i', $shippingAddressData["street"], $houseNumber)) {
            return isset($houseNumber[2])?$houseNumber[2]:'';
        }
    }

    public function extractExtraHouseNumber($shippingAddressData)
    {
        if (preg_match('/([^\d]+)\s?([\d]+)\s?([a-zA-Z]+)?/i', $shippingAddressData["street"], $houseNumber)) {
            return isset($houseNumber[3])?$houseNumber[3]:'';
        }
    }

    public function extractStreet($shippingAddressData)
    {
        if (preg_match('/([^\d]+)\s?([\d]+)\s?([a-zA-Z]+)?/i', $shippingAddressData["street"], $houseNumber)) {
            return isset($houseNumber[1])?$houseNumber[1]:'';
        }
    }

    public function updateLogxstarStatus()
    {

        $orders_coll = $this->collectionFactory->create();
        $orders_coll->addFieldToFilter('logxstar_status', array('null' => true))
            ->addFieldToFilter('internal_reference', array('notnull' => true));
        $orders_coll->load();
        $connection  = $this->_resource->getConnection();
        foreach ($orders_coll as $order) {
            $int_ref = $order->getData('internal_reference');
            $new_status = $this->requestStatus($int_ref);
            if ($new_status) {
                $order->setData('logxstar_status', $new_status);
                $order->getResource()->saveAttribute($order, 'logxstar_status');
                $connection->query('UPDATE ' . $this->_resource->getTableName('sales_order_grid') . " SET logxstar_status='" . $new_status . "' WHERE entity_id=" . $order->getEntityId() . "; ");
            }
            $connection->query('UPDATE ' . $this->_resource->getTableName('sales_order_grid') . " SET  logxstar_selected_date = '".$order->getData('logxstar_selected_date')."'  WHERE entity_id=" . $order->getEntityId() . "; ");
        }
    }

    public function requestStatus($ref)
    {
        $ch = curl_init();
        $logxstarApiKey = $this->scopeConfig->getValue('carriers/logxstar/logxstar_key');
        $logxstarUrl = $this->scopeConfig->getValue('carriers/logxstar/logxstar_host');
        $logxstarUrl = rtrim($logxstarUrl, '/') . '/api/v1/shipping/' . $ref . '/status';
        curl_setopt($ch, CURLOPT_URL, $logxstarUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                "api-key: " . $logxstarApiKey
            )
        );

        $response = curl_exec($ch);
        
        $info = curl_getinfo($ch);
        $status = null;
        $logxstarInternalReference = null;
        $errorCode = null;
        $errorMessage = null;
        if ($response || !curl_errno($ch)) {
            if ($info['http_code'] == 200) { // everything is ok, shipment cretead
                $response = json_decode($response, true);
                $status = $response[1]['status'];

            } else { // errors in shipment data or on logxstar side
                $errorCode = $info['http_code'];
                $errorMessage = json_decode($response, true)['error'];
                //]Mage::log($errorCode . $errorMessage, null, 'logxstar.log', true);
            }
        } else {
            // error in transport
            $errorCode = curl_errno($ch);
            $errorMessage = curl_error($ch);
            //Mage::log($errorCode . $errorMessage, null, 'logxstar.log', true);
        }
        curl_close($ch);
        return $status;
    }

    public function requestLabel($order)
    {
        $shipping_reference_query = $order->getData('internal_reference');
        if (!$shipping_reference_query) {
            $this->apiSendOrderToLogxstar($order);
            if ($order->hasDataChanges()) {
                $order->save();
                $shipping_reference_query = $order->getData('internal_reference');
            }
        }
        if ($shipping_reference_query) {
            $refs = array();
            $refs[] = $shipping_reference_query;

            $shipment = $order->getShipmentsCollection()->getFirstItem();
            $str = $shipment->getData('logxstar_label');
            //$file_name = $this->getFileName($refs);
            //$file = $this->getPackageslipStorageDir() . "/" . $file_name;
            if (!$str) {
                $request_url = rtrim($this->scopeConfig->getValue('carriers/logxstar/logxstar_host'), '/') . '/api/v1/shipping/mass-print-labels/' . implode(',', $refs);
                $logxstarApiKey = $this->scopeConfig->getValue('carriers/logxstar/logxstar_multi_key'); 
                
                if (empty($logxstarApiKey)) {
                    $logxstarApiKey = $this->scopeConfig->getValue('carriers/logxstar/logxstar_key');
                }

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $request_url);
                curl_setopt($curl, CURLOPT_POST, 0);
                curl_setopt($curl, CURLOPT_HTTPHEADER,
                    array(
                        "api-key: " . $logxstarApiKey
                    )
                );
                curl_setopt($curl, CURLOPT_HEADER, FALSE);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);

                $response = curl_exec($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ($http_code == 200) {
                    $result = json_decode($response);
                    if (!empty($result)) {
                        try {
                            $str = $result->{$shipping_reference_query}[0]->label;
                            $barcode = $result->{$shipping_reference_query}[0]->barcode;
                            $tracking_url = $result->{$shipping_reference_query}[0]->tracking_url;

                            $tracks = $shipment->getAllTracks();
                            if (!$tracks) {

                                $track = $this->trackFactory->create();
                                $track->setData('description', $tracking_url);
                                $track->setData('track_number', $barcode);
                                $track->setData('title', 'barcode');
                                $track->setData('carrier_code', 'logxstar');
                                $shipment->addTrack($track);
                            }
                            $order->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE)->save();
                            $shipment->setData('logxstar_label', $str);
                            $shipment->save();
                        } catch (\Exception $e) {

                        }
                    } else {
                        //$return['error'] = $this->language->get('logxstarshipping_connection_empty_response');
                    }
                } else {
                    $errorCode = curl_errno($curl);
                    $errorMessage = curl_error($curl);
                }
                curl_close($curl);
            }
            if ($str) {
                $pdf = new \Zend_Pdf(base64_decode($str));
                return $pdf;
            }
        }
    }

    public function getPickupPoints($method)
    {
        $session = $this->customer_session;
        $postcode = $_GET['postcode'];
        $key = md5(str_replace(' ', '', $postcode));
        $logxstar_pickuppoints = $session->getData('logxstar_pickuppoints');
        if (isset($logxstar_pickuppoints[$key])) {
            $logxstar_pickuppoints = $logxstar_pickuppoints[$key];
        }
        return $logxstar_pickuppoints;
    }

    public function getSaveDeliveryDate($delivery_data)
    {
        $session = $this->customer_session;
        $logxstar_pickuppoints = $session->setData('logxstar_selected_delivery_date', $delivery_data);
        return 'OK';
    }

    public function getSavePickupPoint($point)
    {
        $session = $this->customer_session;
        $logxstar_pickuppoints = $session->setData('logxstar_pickuppoint_selected', $point);
        return 'OK';
    }

    public function createShipment($order)
    {
        $shipment = $this->convert->toShipment($order);
        foreach ($order->getAllItems() AS $orderItem) {
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            $qtyShipped = $orderItem->getQtyToShip();
            $shipmentItem = $this->convert->itemToShipmentItem($orderItem)->setQty($qtyShipped);
            $shipment->addItem($shipmentItem);
        }
        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);
        try {
            $shipment->save();
            $shipment->getOrder()->save();
            $shipment->save();
        } catch (\Exception $e) {

        }

        return $shipment;
    }

    public function getMethodsOptions()
    {
        $logxstar_options = $this->scopeConfig->getValue('carriers/logxstar/logxstar_options');
        if ($logxstar_options) {
            $logxstar_options = unserialize($logxstar_options);
        } else {
            $logxstar_options = [];
        }
        return $logxstar_options;
    }

    public function saveMethodsOptions($methods)
    {
        $methodsoptions = $this->getMethodsOptions();
        foreach ($methods as $method) {
            $method_shortcode = $this->getMethodShortName($method[0], $method[1]);
            if (!isset($methodsoptions[$method_shortcode])) {
                $methodsoptions[$method_shortcode] = $method[0];           
            }
        }
        $data = serialize($methodsoptions);
        $this->configWriter->save('carriers/logxstar/logxstar_options', $data, 'default', 0);
        /*$method_shortcode = substr(md5($method), 0, 10) . $pickup;
        $methodsoptions = $this->getMethodsOptions();
        if (!isset($methodsoptions[$method_shortcode])) {
            $methodsoptions[$method_shortcode] = $method;
            $data = serialize($methodsoptions);
            $this->configWriter->save('carriers/logxstar/logxstar_options', $data, 'default', 0);
        }
        return $method_shortcode;*/
    }

    public function getMethodShortName($method, $pickup)
    {
        return substr(md5($method), 0, 10) . $pickup;    
    }

    public function getMethodOption($method)
    {
        if (substr($method, 0, 9) === 'logxstar_') {
            $options = $this->getMethodsOptions();
            $method = substr($method, 9);
            if (isset($options[$method])) {
                $method = $options[$method];
            }
            $method = 'logxstar_' . $method;
        }

        return $method;
    }
}
