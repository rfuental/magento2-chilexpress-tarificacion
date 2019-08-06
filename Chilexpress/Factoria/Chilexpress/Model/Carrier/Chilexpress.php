<?php


namespace Factoria\Chilexpress\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;


class Chilexpress extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{

    protected $_code = 'chilexpress';

    protected $_isFixed = true;

    protected $_rateResultFactory;

    protected $_rateMethodFactory;
    
    private $stringRequestReqiones;
    
    private $objectManager;
    
    private $variables;
    
    private $url_chilexpress;
    
    private $user_chilexpress;
    
    private $pass_chilexpress;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    private $logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->stringRequestReqiones = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cor="http://www.chilexpress.cl/CorpGR/" xmlns:head="http://www.chilexpress.cl/common/HeaderRequest">
                                         <soapenv:Header>
                                            <cor:headerRequest>
                                               <head:transaccion>
                                                  <head:fechaHora>?</head:fechaHora>
                                                  <head:idTransaccionNegocio>?</head:idTransaccionNegocio>
                                                  <head:idTransaccionOSB>?</head:idTransaccionOSB>
                                                  <head:sistema>?</head:sistema>
                                                  <head:usuario>?</head:usuario>
                                                  <head:oficinaCaja>?</head:oficinaCaja>
                                                  <head:nodoHeader>e gero</head:nodoHeader>
                                               </head:transaccion>
                                            </cor:headerRequest>
                                         </soapenv:Header>
                                         <soapenv:Body>
                                            <cor:ConsultarRegiones>
                                               <reqObtenerRegion/>
                                            </cor:ConsultarRegiones>
                                         </soapenv:Body></soapenv:Envelope>';
        
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->variables = $this->objectManager->create('Magento\Variable\Model\Variable');
        $url_chilexpress = $this->variables->loadByCode('url_chilexpress')->getPlainValue();
        $user_chilexpress = $this->variables->loadByCode('user_chilexpress')->getPlainValue();
        $pass_chilexpress = $this->variables->loadByCode('pass_chilexpress')->getPlainValue();
    }
    
    

    private function callSoap($xml, $action, $soapUrl){
        
        
        
        $auth = base64_encode($this->user_chilexpress.":".$this->pass_chilexpress);
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: $action",
            "Content-length: " . strlen($xml),
            "Authorization: Basic $auth"
        ); //SOAPAction: your op URL

        $url = $soapUrl;

        // PHP cURL  for https connection with auth
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_URL, $soapUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_USERPWD, $soapUser.":".$soapPassword); // username and password - declared at the top of the doc
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml); // the SOAP request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // converting
        $response = curl_exec($ch); //print_r($response); die();
       
        $xmlString = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
        $xml = simplexml_load_string($xmlString);
        $xml = new \SimpleXMLElement($xml->asXML());
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);
        if(isset($array['sBody'])){
            return $array['sBody'];
        }else{
            return $array;
        }
        //return $parser->getOutput();
         
       
    }
        
    public function getRegiones() {
        $soapUrl = "http://testservices.wschilexpress.com/GeoReferencia?wsdl"; // asmx URL of WSDL
        $soapAction = "http://www.chilexpress.cl/CorpGR/ConsultarRegiones";
        // xml post structure

        $xml_post_string = $this->stringRequestReqiones;
        return $this->callSoap($xml_post_string, $soapAction, $soapUrl);
    }
    
    
    public function getCodigoRegion($nombreRegion) {
        $soapUrl = "http://testservices.wschilexpress.com/GeoReferencia?wsdl"; // asmx URL of WSDL
        $soapAction = "http://www.chilexpress.cl/CorpGR/ConsultarRegiones";
        
        $xml_post_string = $this->stringRequestReqiones;
        $response =  $this->callSoap($xml_post_string, $soapAction, $soapUrl);
        unset($response['ConsultarRegionesResponse']['respObtenerRegion']['CodEstado']);
        unset($response['ConsultarRegionesResponse']['respObtenerRegion']['GlsEstado']);
        
        foreach($response['ConsultarRegionesResponse']['respObtenerRegion'] as $row){
            if($row['GlsRegion'] == $nombreRegion){
                return $row['idRegion'];
            }
        }
    }
    
    public function getComunas($codigoRegion){
        $request = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:cor=\"http://www.chilexpress.cl/CorpGR/\" xmlns:head=\"http://www.chilexpress.cl/common/HeaderRequest\" xmlns:cor1=\"http://www.chilexpress.cl/PX000014/CorpGR_ConsultarCoberturaRequest\">
                        <soapenv:Header>
                           <cor:headerRequest>
                              <!--Optional:-->
                              <head:transaccion>
                                 <!--Optional:-->
                                 <head:fechaHora>?</head:fechaHora>
                                 <!--Optional:-->
                                 <head:idTransaccionNegocio>?</head:idTransaccionNegocio>
                                 <!--Optional:-->
                                 <head:idTransaccionOSB>?</head:idTransaccionOSB>
                                 <!--Optional:-->
                                 <head:sistema>?</head:sistema>
                                 <!--Optional:-->
                                 <head:usuario>?</head:usuario>
                                 <!--Optional:-->
                                 <head:oficinaCaja>?</head:oficinaCaja>
                                 <!--Optional:-->
                                 <head:nodoHeader>e gero</head:nodoHeader>
                              </head:transaccion>
                           </cor:headerRequest>
                        </soapenv:Header>
                        <soapenv:Body>
                           <cor:ConsultarCoberturas>
                              <!--Optional:-->
                              <reqObtenerCobertura>
                                 <!--Optional:-->
                                 <cor1:CodTipoCobertura>*</cor1:CodTipoCobertura>
                                 <cor1:CodRegion>$codigoRegion</cor1:CodRegion>
                              </reqObtenerCobertura>
                           </cor:ConsultarCoberturas>
                        </soapenv:Body>
                     </soapenv:Envelope>";
        
        $soapUrl = "http://testservices.wschilexpress.com/GeoReferencia?wsdl"; // asmx URL of WSDL
        $soapAction = "http://www.chilexpress.cl/CorpGR/ConsultarCoberturas";
        $soapUser = "UsrTester";  //  username
        $soapPassword = "&8vhk8790|"; // password
        // xml post structure
        
        return $this->callSoap($request, $soapAction, $soapUrl);
    }
    
    public function valorarEnvio($alto, $ancho, $largo, $peso, $comunaDestino , $idTransaccion = 0, $comunaOrinen = false){
        
        $variables = $this->objectManager->create('Magento\Variable\Model\Variable');
        $user_chilexpress = $variables->loadByCode('user_chilexpress')->getPlainValue();
        $pass_chilexpress = $variables->loadByCode('pass_chilexpress')->getPlainValue();
        $url_chilexpress = $variables->loadByCode('url_chilexpress')->getPlainValue();
        
        $soapUrl = "http://testservices.wschilexpress.com/TarificarCourier?wsdl"; // asmx URL of WSDL
        $soapAction = "http://www.chilexpress.cl/TarificaCourier/TarificarCourier";
        $soapUser = "UsrTester";  //  username
        $soapPassword = "&8vhk8790|"; // password

        $request = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tar=\"http://www.chilexpress.cl/TarificaCourier/\" xmlns:head=\"http://www.chilexpress.cl/common/HeaderRequest\" xmlns:opv=\"http://www.chilexpress.cl/ESB/TarificaCourier/OpValorizarCourierRequest\">  
            <soapenv:Header>       
            <tar:headerRequest>    
            <head:transaccion>         
            <head:fechaHora>2012-07-09T09:47:51</head:fechaHora>   
            <head:idTransaccionNegocio>$idTransaccion</head:idTransaccionNegocio>     
            <head:sistema>ED</head:sistema>      
            </head:transaccion>    
              </tar:headerRequest>  
              </soapenv:Header>  
              <soapenv:Body>    
              <tar:TarificarCourier>    
              <reqValorizarCourier>     
              <opv:CodCoberturaOrigen>PUDA</opv:CodCoberturaOrigen>       
              <opv:CodCoberturaDestino>$comunaDestino</opv:CodCoberturaDestino>      
              <opv:PesoPza>$peso</opv:PesoPza>          
              <opv:DimAltoPza>$alto</opv:DimAltoPza>        
              <opv:DimAnchoPza>$ancho</opv:DimAnchoPza>         
              <opv:DimLargoPza>$largo</opv:DimLargoPza>         
              </reqValorizarCourier>    
              </tar:TarificarCourier> 
              </soapenv:Body>
              </soapenv:Envelope>";
        //print_r($request); die();
        /*$this->logger->critical('**********************************************');
        $this->logger->critical(print_r($request, true));
        $this->logger->critical('**********************************************');*/
        return $this->callSoap($request, $soapAction, $soapUrl);
        //$response =  $this->callSoap($xml_post_string, $soapAction, $soapUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function collectRates(RateRequest $request)
    {
        
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        //print_r($request);
            $shippingPrice = $this->getConfigData('price');

            $result = $this->_rateResultFactory->create();

            $this->logger->critical('**********************START SHIPPING REGION CHANGE************************');
            
            //print_r($region->getData());
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $region = $objectManager->create('Magento\Directory\Model\Region')
                                ->load($request->getDestRegionId());
            //$this->logger->critical(print_r($request, true));
            $regioncode = $region['code'];
            //$this->logger->critical($regioncode);
            //$this->logger->critical('***********************END SHIPPING REGION CHANGE***********************');
            //die();
            $comunas = $this->getComunas($regioncode)['ConsultarCoberturasResponse']['respObtenerCobertura']['Coberturas'];
            $this->logger->critical(print_r($comunas, true));
            
            $codComuna = false;

            foreach( $comunas as $comuna){
                if(isset($comuna['CodComuna'])){
                    $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
                    $tmpComuna = strtr( $request->getDestCity(), $unwanted_array );
                    
                    if(strtolower($comuna['GlsComuna']) == strtolower($tmpComuna)){
                        $codComuna = $comuna['CodComuna'];
                    }
                }
            }
            //$codComuna = "VALP";
            /*if(!$codComuna){
                return false;
            }*/
            //$this->logger->critical("COMUNA");
            //$this->logger->critical(print_r($codComuna, true));
                    
            $quoteArr = array();
            $alto = 0;
            $ancho = 0;
            $largo = 0;
            $peso = 0;
            $volumen = 0;
            foreach ($request->getAllItems() as $item) {
                /*$alto += $item->getheight();
                $ancho += $item->getweight();
                $largo +=  $item->getlength();*/
                $volumen += $item->getheight()*$item->getwidth()*$item->getlength();
                $peso += $item->getweight();
            }
            $dimensionProporcional = pow($volumen, 1/3);
            $resultValor = $this->valorarEnvio($dimensionProporcional, $dimensionProporcional, $dimensionProporcional, $peso, $codComuna);
            $price = false;
            //$this->logger->critical(print_r($resultValor, true)); 
            

            if(isset($resultValor['TarificarCourierResponse']['respValorizarCourier']['Servicios'])){
                if(is_array($resultValor['TarificarCourierResponse']['respValorizarCourier']['Servicios'])){
                    foreach($resultValor['TarificarCourierResponse']['respValorizarCourier']['Servicios'] as $methodObj){
                        if(isset($methodObj['ValorServicio'])){
                            if( trim($methodObj['CodServicio']) == "3" ){
                                $price = $methodObj['ValorServicio'];
                            }

                        }
                    }
                }

            }
  
            
            if($price){
                $shippingPrice = $this->getConfigData('price');

                    $result = $this->_rateResultFactory->create();

                    if ($shippingPrice !== false) {
                        $method = $this->_rateMethodFactory->create();

                        $method->setCarrier($this->_code);
                        $method->setCarrierTitle("Envío Normal");

                        $method->setMethod($this->_code);
                        $method->setMethodTitle($this->getConfigData('name'));

                        if ($request->getFreeShipping() === true || $request->getPackageQty() == $this->getFreeBoxes()) {
                            $shippingPrice = '0.00';
                        }

                        $method->setPrice($price);
                        $method->setCost($price);

                        $result->append($method);
                    }
            }
            return $result;
    }

    /**
     * getAllowedMethods
     *
     * @param array
     */
    public function getAllowedMethods()
    {
        return ['flatrate' => $this->getConfigData('name')];
    }

}
