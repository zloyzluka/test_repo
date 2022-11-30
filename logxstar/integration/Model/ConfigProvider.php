<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Logxstar\Integration\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Psr\Log\LoggerInterface;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Url Builder
     *
     * @var \Magento\Framework\Url
     */
    protected $urlBuilder;
    protected $request;
    protected $assetRepo;
    protected $logger;
    protected $scopeConfig;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Url $urlBuilder,
        RequestInterface $request,
        Repository $assetRepo,
        LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->assetRepo = $assetRepo;
        $this->logger = $logger;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
    }

    public function getAjaxPickuppointsUrl()
    {
        return $this->urlBuilder->getUrl('logxstar/pickup/index');
    }

    public function getAjaxPickuppointsSaveUrl()
    {
        return $this->urlBuilder->getUrl('logxstar/pickup/save');
    }

    public function getAjaxDeliveryDataSaveUrl()
    {
        return $this->urlBuilder->getUrl('logxstar/pickup/date');
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $freeshipping_data = $this->scopeConfig->getValue('carriers/logxstar/country_freeshipping');
        if ($freeshipping_data) {
            $freeshipping_data = unserialize($freeshipping_data);
        } else {
            $freeshipping_data = array();
        }
        $config = [
            'logxstar' => [
                'pickuppoint' => [
                    'ajaxgetpoints' => $this->getAjaxPickuppointsUrl(),
                    'css_popup' => $this->getViewFileUrl('Logxstar_Integration::css/modal.css'),
                    'week_days' => array(
                        array(
                            'name' => 'monday',
                            'label' => __('Maandag')
                        ),
                        array(
                            'name' => 'tuesday',
                            'label' => __('Dinsdag')
                        ),
                        array(
                            'name' => 'wednesday',
                            'label' => __('Woensdag')
                        ),
                        array(
                            'name' => 'thursday',
                            'label' => __('Donderdag')
                        ),
                        array(
                            'name' => 'friday',
                            'label' => __('Vrijdag')
                        ),
                        array(
                            'name' => 'saturday',
                            'label' =>__('Zaterdag')
                        ),
                        array(
                            'name' => 'sunday',
                            'label' => __('Zondag')
                        ),
                    ),
                    'dict' => $this->getDict(),
                    'ajaxgetpoints_save' => $this->getAjaxPickuppointsSaveUrl(),
                    'ajaxdeliverydate_save' => $this->getAjaxDeliveryDataSaveUrl(),
                    'image_path'      => $this->getViewFileUrl('Logxstar_Integration::images/'),
                    'header_logo_src' => $this->getViewFileUrl('Logxstar_Integration::images/logxlogo.png'),
                    'map_apikey' => $this->scopeConfig->getValue('carriers/logxstar/map_api'),
                    'freeshipping_data' => $freeshipping_data,
                    'freeshipping_tax' => $this->scopeConfig->getValue('carriers/logxstar/freeshipping_includes_tax'),
                    'select_button' => ($this->scopeConfig->getValue('carriers/logxstar/use_button'))?
                        '<button style="margin: 15px 0 0 0;" type="button" class="btn btn-primary ls_modal_submit">'.__('Selecteer een afhaalpunt of andere leverdag').'</button>':
                        '(<a href="#" onclick="return false;">'.__('Selecteer een afhaalpunt of andere leverdag').'</a>)'
                  ,
                ],
            ],
        ];
        return $config;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }
    public function getDict() {
        $dict = array(
            'Maandag' => array(
                'de' => 'Montag',
                'en' => 'Monday',
                'es' => 'Lunes',
                'fr' => 'Lundi'
            ),
            'Dinsdag' => array(
                'de' => 'Dienstag',
                'en' => 'Tuesday',
                'es' => 'Martes',
                'fr' => 'Mardi'
            ),
            'Woensdag' => array(
                'de' => 'Mittwoch',
                'en' => 'Wednesday',
                'es' => 'Miércoles',
                'fr' => 'Mercredi'
           ),
            'Donderdag' => array(
                'de' => 'Donnerstag',
                'en' => 'Thursday',
                'es' => 'Jueves',
                'fr' => 'Jeudi'
            ),
            'Vrijdag' => array(
                'de' => 'Freitag',
                'en' => 'Friday',
                'es' => 'Viernes',
                'fr' => 'Vendredi'
            ),
            'Zaterdag' => array(
                'de' => 'Samstag',
                'en' => 'Saturday',
                'es' => 'Sábado',
                'fr' => 'Samedi'
            ),
            'Zondag' => array(
                'de' => 'Sonntag',
                'en' => 'Sunday',
                'es' => 'Domingo',
                'fr' => 'Dimanche'
            ),
            'Gesloten' => array(
                'de' => 'Geschlossen',
                'en' => 'Closed',
                'es' => 'Cerrado',
                'fr' => 'Fermé'
            ),
            'Afstand' => array(
                'de' => 'Abstand',
                'en' => 'Distance',
                'es' => 'Distancio',
                'fr' => 'Distance'
            ),
            'Openingstijden' => array(
                'de' => 'Öffnungszeiten',
                'en' => 'Business hours',
                'es' => 'Horario de trabajo',
                'fr' => 'Heures d`ouverture'
            ),
            'Kies dit servicepunt' => array(
                'de' => 'Abholstation wählen',
                'en' => 'Select this location',
                'es' => 'Seleccionar este sitio',
                'fr' => 'Sélectionnez'
            ),
            'Januari' => array(
                'de' => 'Januar',
                'en' => 'January',
                'es' => 'Enero',
                'fr' => 'Janvier'
            ),
            'Februari' => array(
                'de' => 'Februar',
                'en' => 'February',
                'es' => 'Febrero',
                'fr' => 'Février'
            ),
            'Maart' => array(
                'de' => 'März',
                'en' => 'March',
                'es' => 'Marzo',
                'fr' => 'Mars'
            ),
            'April' => array(
                'de' => 'April',
                'en' => 'April',
                'es' => 'Abril',
                'fr' => 'Avril'
            ),
            'Mei' => array(
                'de' => 'Mai',
                'en' => 'May',
                'es' => 'Mayo',
                'fr' => 'Mai'
            ),
            'Juni' => array(
                'de' => 'Juni',
                'en' => 'June',
                'es' => 'Junio',
                'fr' => 'Juin'
            ),
            'Juli' => array(
                'de' => 'Juli',
                'en' => 'Julн',
                'es' => 'Julio',
                'fr' => 'Juillet'
            ),
            'Augustus' => array(
                'de' => 'Augustus',
                'en' => 'August',
                'es' => 'Agosto',
                'fr' => 'Août'
            ),
            'September' => array(
                'de' => 'September',
                'en' => 'September',
                'es' => 'Septiembre',
                'fr' => 'Septembre'
            ),
            'Oktober' => array(
                'de' => 'Oktober',
                'en' => 'October',
                'es' => 'Octubre',
                'fr' => 'Octobre'
            ),
            'November' => array(
                'de' => 'November',
                'en' => 'November',
                'es' => 'Noviembre',
                'fr' => 'Novembre'
            ),
            'December' => array(
                'de' => 'Dezember',
                'en' => 'December',
                'es' => 'Diciembre',
                'fr' => 'Décembre'
            ),
            'Morgen' => array(
                'de' => 'Morgen',
                'en' => 'Tomorrow',
                'es' => 'Мañana',
                'fr' => 'Demain'
            ),
            'Selecteer een afhaalpunt of andere leverdag' => array (
                'de' => 'Select a pick-up point or other delivery day',
                'en' => 'Select a pick-up point or other delivery day',
                'es' => 'Select a pick-up point or other delivery day',
                'fr' => 'Select a pick-up point or other delivery day'
            ),
            'BEZORGEN' => array(
                'de' => 'DELIVER',
                'en' => 'DELIVER',
                'es' => 'DELIVER',
                'fr' => 'DELIVER'
            ),
            'OPHALEN' => array(
                'de' => 'PICK UP',
                'en' => 'PICK UP',
                'es' => 'PICK UP',
                'fr' => 'PICK UP'
            ),
            'FILTER OP VERVOERDER' => array(
                'de' => 'DELIVERY COMPANY',
                'en' => 'DELIVERY COMPANY',
                'es' => 'DELIVERY COMPANY',
                'fr' => 'DELIVERY COMPANY'
            ),
            'MAAK UW BEZORGKEUZE' => array(
                'de' => 'DELIVERY DETAILS',
                'en' => 'DELIVERY DETAILS',
                'es' => 'DELIVERY DETAILS',
                'fr' => 'DELIVERY DETAILS' 
            ),
            'Avondlevering' => array(
                'de' => 'Abendlieferung',
                'en' => 'Evening delivery',
                'es' => 'Evening delivery',
                'fr' => 'Livraison en soirée'   
            ),
        );
        return json_encode($dict);
    }
}
