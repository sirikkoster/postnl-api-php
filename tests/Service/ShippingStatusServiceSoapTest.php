<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2017-2018 Thirty Development, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <michael@thirtybees.com>
 * @copyright 2017-2018 Thirty Development, LLC
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace ThirtyBees\PostNL\Tests\Service;

use Cache\Adapter\Void\VoidCachePool;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use ThirtyBees\PostNL\Entity\Address;
use ThirtyBees\PostNL\Entity\Customer;
use ThirtyBees\PostNL\Entity\Message\Message;
use ThirtyBees\PostNL\Entity\Request\CompleteStatus;
use ThirtyBees\PostNL\Entity\Request\CurrentStatus;
use ThirtyBees\PostNL\Entity\Request\GetSignature;
use ThirtyBees\PostNL\Entity\Shipment;
use ThirtyBees\PostNL\Entity\SOAP\UsernameToken;
use ThirtyBees\PostNL\PostNL;
use ThirtyBees\PostNL\Service\ShippingStatusService;

/**
 * Class ShippingStatusSoapTest
 *
 * @package ThirtyBees\PostNL\Tests\Service
 *
 * @testdox The ShippingStatusService (REST)
 */
class ShippingStatusSoapTest extends \PHPUnit_Framework_TestCase
{
    /** @var PostNL $postnl */
    protected $postnl;
    /** @var ShippingStatusService $service */
    protected $service;
    /** @var $lastRequest */
    protected $lastRequest;

    /**
     * @before
     * @throws \ThirtyBees\PostNL\Exception\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function setupPostNL()
    {
        $this->postnl = new PostNL(
            Customer::create()
                ->setCollectionLocation('123456')
                ->setCustomerCode('DEVC')
                ->setCustomerNumber('11223344')
                ->setContactPerson('Test')
                ->setAddress(Address::create([
                    'AddressType' => '02',
                    'City'        => 'Hoofddorp',
                    'CompanyName' => 'PostNL',
                    'Countrycode' => 'NL',
                    'HouseNr'     => '42',
                    'Street'      => 'Siriusdreef',
                    'Zipcode'     => '2132WT',
                ]))
                ->setGlobalPackBarcodeType('AB')
                ->setGlobalPackCustomerCode('1234')
            , new UsernameToken(null, 'test'),
            true,
            PostNL::MODE_SOAP
        );

        $this->service = $this->postnl->getShippingStatusService();
        $this->service->cache = new VoidCachePool();
        $this->service->ttl = 1;
    }

    /**
     * @after
     */
    public function logPendingRequest()
    {
        if (!$this->lastRequest instanceof Request) {
            return;
        }

        global $logger;
        if ($logger instanceof LoggerInterface) {
            $logger->debug($this->getName()." Request\n".\GuzzleHttp\Psr7\str($this->lastRequest));
        }
        $this->lastRequest = null;
    }

    /**
     * @testdox creates a valid CurrentStatus request
     */
    public function testGetCurrentStatusRequestSoap()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusSOAPRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CurrentStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:Barcode>{$barcode}</domain:Barcode>
   </domain:Shipment>
  </services:CurrentStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CurrentStatusByReference request
     */
    public function testGetCurrentStatusByReferenceRequestSoap()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusSOAPRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CurrentStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:Reference>{$reference}</domain:Reference>
   </domain:Shipment>
  </services:CurrentStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CurrentStatusByStatus request
     */
    public function testGetCurrentStatusByStatusRequestSoap()
    {
        $status = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusSOAPRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setStatusCode($status)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CurrentStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:StatusCode>{$status}</domain:StatusCode>
   </domain:Shipment>
  </services:CurrentStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CurrentStatusByPhase request
     */
    public function testGetCurrentStatusByPhaseRequestSoap()
    {
        $phase = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusSOAPRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setPhaseCode($phase)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CurrentStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:PhaseCode>{$phase}</domain:PhaseCode>
   </domain:Shipment>
  </services:CurrentStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CompleteStatus request
     */
    public function testGetCompleteStatusRequestSoap()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusSOAPRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CompleteStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:Barcode>{$barcode}</domain:Barcode>
   </domain:Shipment>
  </services:CompleteStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CompleteStatusByReference request
     */
    public function testGetCompleteStatusByReferenceRequestSoap()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusSOAPRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CompleteStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:Reference>{$reference}</domain:Reference>
   </domain:Shipment>
  </services:CompleteStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CompleteStatusByStatus request
     */
    public function testGetCompleteStatusByStatusRequestSoap()
    {
        $status = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusSOAPRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setStatusCode($status)
                        ->setDateFrom('29-06-2016')
                        ->setDateTo('20-07-2016')
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CompleteStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:DateFrom>29-06-2016</domain:DateFrom>
    <domain:DateTo>20-07-2016</domain:DateTo>
    <domain:StatusCode>{$status}</domain:StatusCode>
   </domain:Shipment>
  </services:CompleteStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid CompleteStatusByPhase request
     */
    public function testGetCompleteStatusByPhaseRequestSoap()
    {
        $phase = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusSOAPRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setPhaseCode($phase)
                        ->setDateFrom('29-06-2016')
                        ->setDateTo('20-07-2016')
                )
                ->setMessage($message)
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:CompleteStatus>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:DateFrom>29-06-2016</domain:DateFrom>
    <domain:DateTo>20-07-2016</domain:DateTo>
    <domain:PhaseCode>{$phase}</domain:PhaseCode>
   </domain:Shipment>
  </services:CompleteStatus>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

    /**
     * @testdox creates a valid GetSignature request
     */
    public function testGetSignatureRequestSoap()
    {
        $barcode = '3S9283920398234';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildGetSignatureSOAPRequest(
            (new GetSignature())
                ->setMessage($message)
                ->setShipment((new Shipment())
                    ->setBarcode($barcode)
                )
        );

        $this->assertEquals("<?xml version=\"1.0\"?>
<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:env=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:services=\"http://postnl.nl/cif/services/ShippingStatusWebService/\" xmlns:domain=\"http://postnl.nl/cif/domain/ShippingStatusWebService/\" xmlns:wsse=\"http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd\" xmlns:schema=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:common=\"http://postnl.nl/cif/services/common/\">
 <soap:Header>
  <wsse:Security>
   <wsse:UsernameToken>
    <wsse:Password>test</wsse:Password>
   </wsse:UsernameToken>
  </wsse:Security>
 </soap:Header>
 <soap:Body>
  <services:GetSignature>
   <domain:Message>
    <domain:MessageID>{$message->getMessageID()}</domain:MessageID>
    <domain:MessageTimeStamp>{$message->getMessageTimeStamp()}</domain:MessageTimeStamp>
   </domain:Message>
   <domain:Customer>
    <domain:CustomerCode>{$this->postnl->getCustomer()->getCustomerCode()}</domain:CustomerCode>
    <domain:CustomerNumber>{$this->postnl->getCustomer()->getCustomerNumber()}</domain:CustomerNumber>
   </domain:Customer>
   <domain:Shipment>
    <domain:Barcode>{$barcode}</domain:Barcode>
   </domain:Shipment>
  </services:GetSignature>
 </soap:Body>
</soap:Envelope>
", (string) $request->getBody());
    }

//    /**
//     * @testdox can generate a single label
//     *
//     * @throws \ReflectionException
//     */
//    public function testGetCurrentStatusRest()
//    {
//        [
//            'Shipments' => [
//                [
//                    'Addresses' => [
//                        [
//                            'AddressType'
//                        ]
//                    ]
//                ]
//            ]
//        ]
//        /*
//        <a:Shipments>
//         <a:CurrentStatusResponseShipment>
//           <a:Addresses>
//             <a:ResponseAddress>
//               <a:AddressType>01</a:AddressType>
//               <a:City>Hoofddorp</a:City>
//               <a:CountryCode>NL</a:CountryCode>
//               <a:LastName>de Ruiter</a:LastName>
//               <a:RegistrationDate>01-01-0001 00:00:00</a:RegistrationDate>
//               <a:Street>Siriusdreef</a:Street>
//               <a:Zipcode>2132WT</a:Zipcode>
//             </a:ResponseAddress>
//             <a:ResponseAddress>
//               <a:AddressType>02</a:AddressType>
//               <a:City>Vianen</a:City>
//               <a:CompanyName>PostNL</a:CompanyName>
//               <a:CountryCode>NL</a:CountryCode>
//               <a:HouseNumber>1</a:HouseNumber>
//               <a:HouseNumberSuffix>A</a:HouseNumberSuffix>
//               <a:RegistrationDate>01-01-0001 00:00:00</a:RegistrationDate>
//               <a:Street>Lage Biezenweg</a:Street>
//               <a:Zipcode>4131LV</a:Zipcode>
//             </a:ResponseAddress>
//           </a:Addresses>
//           <a:Barcode>3SABCD6659149</a:Barcode>
//           <a:Groups>
//             <a:ResponseGroup>
//               <a:GroupType>4</a:GroupType>
//               <a:MainBarcode>3SABCD6659149</a:MainBarcode>
//               <a:ShipmentAmount>1</a:ShipmentAmount>
//               <a:ShipmentCounter>1</a:ShipmentCounter>
//             </a:ResponseGroup>
//           </a:Groups>
//           <a:ProductCode>003052</a:ProductCode>
//           <a:Reference>2016014567</a:Reference>
//           <a:Status>
//             <a:CurrentPhaseCode>4</a:CurrentPhaseCode>
//             <a:CurrentPhaseDescription>Afgeleverd</a:CurrentPhaseDescription>
//             <a:CurrentStatusCode>11</a:CurrentStatusCode>
//             <a:CurrentStatusDescription>Zending afgeleverd</a:CurrentStatusDescription>
//             <a:CurrentStatusTimeStamp>06-06-2016 18:00:41</a:CurrentStatusTimeStamp>
//           </a:Status>
//         </a:CurrentStatusResponseShipment>
//       </a:Shipments>
//        */
//
//
//        $mock = new MockHandler([
//            new Response(200, ['Content-Type' => 'application/json;charset=UTF-8'], json_encode([
//                'MergedLabels' => [],
//                'ResponseShipments' => [
//                    [
//                        'Barcode' => '3SDEVC201611210',
//                        'DownPartnerLocation' => [],
//                        'ProductCodeDelivery' => '3085',
//                        'Labels' => [
//                            [
//                                'Labeltype' => 'Label',
//                            ]
//                        ]
//                    ]
//                ]
//            ])),
//        ]);
//        $handler = HandlerStack::create($mock);
//        $mockClient = new MockClient();
//        $mockClient->setHandler($handler);
//        $this->postnl->setHttpClient($mockClient);
//
//        $label = $this->postnl->generateLabel(
//            (new Shipment())
//                ->setAddresses([
//                    Address::create([
//                        'AddressType' => '01',
//                        'City'        => 'Utrecht',
//                        'Countrycode' => 'NL',
//                        'FirstName'   => 'Peter',
//                        'HouseNr'     => '9',
//                        'HouseNrExt'  => 'a bis',
//                        'Name'        => 'de Ruijter',
//                        'Street'      => 'Bilderdijkstraat',
//                        'Zipcode'     => '3521VA',
//                    ]),
//                    Address::create([
//                        'AddressType' => '02',
//                        'City'        => 'Hoofddorp',
//                        'CompanyName' => 'PostNL',
//                        'Countrycode' => 'NL',
//                        'HouseNr'     => '42',
//                        'Street'      => 'Siriusdreef',
//                        'Zipcode'     => '2132WT',
//                    ]),
//                ])
//                ->setBarcode('3S1234567890123')
//                ->setDeliveryAddress('01')
//                ->setDimension(new Dimension('2000'))
//                ->setProductCodeDelivery('3085')
//        );
//
//        $this->assertInstanceOf('\\ThirtyBees\\PostNL\\Entity\\Response\\GenerateLabelResponse', $label);
//    }
}