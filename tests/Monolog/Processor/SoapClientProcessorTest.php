<?php

namespace Monolog\Processor;

use Monolog\TestCase;

/**
 * @covers Monolog\Processor\SoapClientProcessor
 */
class SoapClientProcessorTest extends TestCase
{
	protected function mockSoapClient(array $methods)
	{
		$mock = $this->getMockBuilder('\\SoapClient')
				->disableOriginalConstructor()
				->setMethods($methods)
				->getMock();
		return $mock;
	}
	public function testProcessorNoAction()
	{
		$processor = new SoapClientProcessor();
		$record = $this->getRecord();
		$updatedRecord = $processor->__invoke($record);
		$this->assertEquals('', $updatedRecord['extra'][SoapClientProcessor::SOAP_ENDPOINT_KEY]);
		$this->assertEquals('', $updatedRecord['extra'][SoapClientProcessor::SOAP_REQUEST_KEY]);
		$this->assertEquals('', $updatedRecord['extra'][SoapClientProcessor::SOAP_RESPONSE_KEY]);
	}

	public function testProcessorWithSoapClient()
	{
		$processor = new SoapClientProcessor();
		$record = $this->getRecord();
		$mockSoapClient = $this->mockSoapClient(array('__getLastRequest', '__getLastResponse'));
		$mockSoapClient->location = 'http://location';
		$mockSoapClient->expects($this->once())
			->method('__getLastRequest')
			->will($this->returnValue('<request><test/></request>'));
		$mockSoapClient->expects($this->once())
			->method('__getLastResponse')
			->will($this->returnValue('<response><tested/></response>'));
		$record['context'][SoapClientProcessor::SOAP_CLIENT_KEY] = $mockSoapClient;

		$updatedRecord = $processor->__invoke($record);
		$this->assertFalse(isset($updatedRecord['context'][SoapClientProcessor::SOAP_CLIENT_KEY]));
		$this->assertEquals('http://location', $updatedRecord['extra'][SoapClientProcessor::SOAP_ENDPOINT_KEY]);
		$this->assertEquals("<?xml version=\"1.0\"?>
<request>
  <test/>
</request>
", $updatedRecord['extra'][SoapClientProcessor::SOAP_REQUEST_KEY]);
		$this->assertEquals("<?xml version=\"1.0\"?>
<response>
  <tested/>
</response>
", $updatedRecord['extra'][SoapClientProcessor::SOAP_RESPONSE_KEY]);
	}

	public function testProcessorWithSanitization()
	{
		$processor = new SoapClientPRocessor();
		$processor->setXPathNamespaces(array(
			'a' => 'http://aspace',
			'b' => 'http://bspace',
		));
		$processor->setXPathRules(array(
			'//a:one/a:two',
			'//b:three/b:four',
		));
		$mockSoapClient = $this->mockSoapClient(array('__getLastRequest', '__getLastResponse'));
		$mockSoapClient->location = 'http://location';
		$mockSoapClient->expects($this->once())
			->method('__getLastRequest')
			->will($this->returnValue('<request xmlns:a="http://aspace"><a:one><a:two>shoe</a:two></a:one><six>sticks</six></request>'));
		$mockSoapClient->expects($this->once())
			->method('__getLastResponse')
			->will($this->returnValue('<response xmlns:b="http://bspace"><b:three><b:four>door</b:four></b:three><six>sticks</six></response>'));
		$record['context'][SoapClientProcessor::SOAP_CLIENT_KEY] = $mockSoapClient;
		$updatedRecord = $processor->__invoke($record);
		$this->assertEquals('http://location', $updatedRecord['extra'][SoapClientProcessor::SOAP_ENDPOINT_KEY]);
		$this->assertTrue(false !== strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_REQUEST_KEY], '****'));
		$this->assertTrue(false === strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_REQUEST_KEY], 'shoe'));
		$this->assertTrue(false !== strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_REQUEST_KEY], 'sticks'));
		$this->assertTrue(false !== strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_RESPONSE_KEY], '****'));
		$this->assertTrue(false === strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_RESPONSE_KEY], 'door'));
		$this->assertTrue(false !== strpos($updatedRecord['extra'][SoapClientProcessor::SOAP_RESPONSE_KEY], 'sticks'));
	}
}
