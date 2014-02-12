<?php

namespace Monolog\Processor;

use \DOMDocument;
use \DOMXPath;
use \SoapClient;
/**
 * Injects formatted Soap Requests / Responses
 * @author Gareth Harcombe-Minson <garadox47@gmail.com>
 */
class SoapClientProcessor {
    const RECORD_CONTEXT = 'context';
    const RECORD_EXTRA = 'extra';
    const SANITIZE_CHARACTER = '*';
    const SOAP_CLIENT_KEY = 'SoapClient';
    const SOAP_ENDPOINT = 'endpoint';
    const SOAP_REQUEST_KEY = 'soap_request';
    const SOAP_RESPONSE_KEY = 'soap_response';

    protected $domDocument;
    protected $xPathNamespaces;
    protected $xPathRules;

    /**
     * Constructor
     */
    public function __construct() {
        $this->xPathNamespaces = array();
        $this->xPathRules = array();
        $this->initializeDOMDocument();
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record) {
        $soapClient = $this->locateSoapClient($record);
        if(is_null($soapClient)) {
            //No SoapClient provided, return 'as is'
            return $record;
        }

        $record = $this->removeSoapClientFromRecord($record);
        $extraSoapXml = $this->formatandReturnXml($soapClient);
        if(0 < count($extraSoapXml)) {
            $record[self::RECORD_EXTRA] = array_merge($record[self::RECORD_EXTRA], $extraSoapXml);
        }
        $record = $this->addSoapClientEndpoint($soapClient, $record);
        return $record;
    }
    
    /**
     * Adds the Soap Client endpoint
     * 
     * @param SoapClient $soapClient
     * @param array $record
     */
    protected function addSoapClientEndpoint(SoapClient $soapClient, array $record) {
        $extraData = array(self::SOAP_ENDPOINT => $soapClient->location);
        $record[self::RECORD_EXTRA] = array_merge($record[self::RECORD_EXTRA], $extraData);
        return $record;
    }

    /**
     * @param DOMDocument $document
     * @return DOMXPath
     */
    protected function createAndReturnXPath(DOMDocument $document) {
        $xPath = new DOMXPath($document);
        foreach($this->getXPathNamespaces() as $ns=>$url) {
            $xPath->registerNamespace($ns, $url);
        }
        return $xPath;
    }

    protected function formatAndReturnXml(SoapClient $soapClient) {
        $extraData = array();
        $lastRequest = $soapClient->__getLastRequest();
        $lastResponse = $soapClient->__getLastResponse();
        $soapRequest = $this->formatAndSanitizeXml($lastRequest);
        $soapResponse = $this->formatAndSanitizeXml($lastResponse);
        if(false !== $soapRequest) {
            $extraData[self::SOAP_REQUEST_KEY] = $soapRequest;
        }
        if(false !== $soapResponse) {
            $extraData[self::SOAP_RESPONSE_KEY] = $soapResponse;
        }
        return $extraData;
    }

    /**
     * @param $xml
     * @return bool|string
     */
    protected function formatAndSanitizeXml($xml) {
        $domDocument = $this->getDomDocument();
        @$domDocument->loadXML($xml);
        if(0 < count($this->getXPathRules())) {
            $this->sanitizeXml($domDocument);
        }
        $formattedXml = @$domDocument->saveXML();

        return $formattedXml;
    }

    /**
     * Configure the DOMDocument formatting
     */
    protected function initializeDOMDocument() {
        $domDocument = new DOMDocument();
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $this->setDomDocument($domDocument);
    }

    /**
     * Locates a SoapClient in the record context
     * @param array $record
     * @return mixed
     */
    protected function locateSoapClient(array $record) {
        $context = $record[self::RECORD_CONTEXT];
        $soapClient = null;
        if(isset($context[self::SOAP_CLIENT_KEY]) && $context[self::SOAP_CLIENT_KEY] instanceof SoapClient) {
            $soapClient = $context[self::SOAP_CLIENT_KEY];
        }
        return $soapClient;
    }

    /**
     * Removes the SoapClient from the record context
     * @param array $record
     * @return array
     */
    protected function removeSoapClientFromRecord(array $record) {
        $context = $record[self::RECORD_CONTEXT];
        if(isset($context[self::SOAP_CLIENT_KEY])) {
            unset($context[self::SOAP_CLIENT_KEY]);
            $record[self::RECORD_CONTEXT] = $context;
        }
        return $record;
    }

    /**
     * @param DOMDocument $document
     */
    protected function sanitizeXml(DOMDocument $document) {
        $xPath = $this->createAndReturnXPath($document);
        foreach($this->getXPathRules() as $rule) {
            $resultElements = $xPath->query($rule);
            foreach($resultElements as $element) {
                $sanitizedValue = $this->sanitizeValue($element->nodeValue);
                $element->nodeValue = $sanitizedValue;
            }
        }
    }

    /**
     * @param $value
     * @return string
     */
    protected function sanitizeValue($value) {
        return preg_replace(array('/\d/', '/\w/'), self::SANITIZE_CHARACTER, $value);
    }

    /**
     * @param DOMDocument $document
     */
    protected function setDomDocument(DOMDocument $document) {
        $this->domDocument = $document;
    }

    /**
     * @return DOMDocument
     */
    protected function getDomDocument() {
        return $this->domDocument;
    }

    /**
     * Sets the array of namespaces for xPath queries
     * @param array $namespaces
     * @throws \UnexpectedValueException
     */
    public function setXPathNamespaces(array $namespaces) {
        foreach($namespaces as $ns=>$url) {
            if(false === is_string($ns) || false === is_string($url)) {
                throw new \UnexpectedValueException('$namespaces must contain an array of namespace keys and url values');
            }
        }
        $this->xPathNamespaces = $namespaces;
    }

    /**
     * @return array
     */
    public function getXPathNamespaces() {
        return $this->xPathNamespaces;
    }

    /**
     * Sets the array of xPath rules for queries
     * @param array $rules
     * @throws \UnexpectedValueException
     */
    public function setXPathRules(array $rules) {
        foreach($rules as $rule) {
            if(false === is_string($rule)) {
                throw new \UnexpectedValueException('$rules must contain an array of strings');
            }
        }
        $this->xPathRules = $rules;
    }

    /**
     * @return array
     */
    public function getXPathRules() {
        return $this->xPathRules;
    }
} 
