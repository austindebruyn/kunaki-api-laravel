<?php

use Austin\KunakiApiLaravel\Customer;
use Austin\KunakiApiLaravel\Destination;
use Austin\KunakiApiLaravel\KunakiOrder;

define('EXAMPLE_PRODUCT', 'PX00ZOV6J0');

class KunakiOrderTest extends TestCase
{

	/**
	 * Our one true order.
	 *
	 * @var KunakiOrder
	 */
	protected $o;

	/**
	 * Array to hold shipping options - we only want to query the server once.
	 *
	 * @var array
	 */
	protected $shippingOptions;

	/**
	 * Creates a single KunakiOrder for us to test over and over
	 */
	public function setUp()
	{
		$destination = new Destination('United States', 'TX', '78755');
		$this->o     = new KunakiOrder($destination);
	}

	/**
	 * Tests that we have an instantiated KunakiOrder
	 *
	 * @return void
	 */
	public function testNew()
	{

		$this->assertEquals($this->o->getDestination()->getCountry(), 'United States');
	}

	/**
	 * Test that you can add a product successfully
	 *
	 * @return void
	 */
	public function testAddProduct()
	{

		$this->o->addProduct('P1');
		$this->o->addProduct('P1', 1)->addProduct('P1', 2);

		$this->assertEquals($this->o->productCount(), 4);
	}

	/**
	 * Test that you can remove a product successfully
	 *
	 * @return void
	 */
	public function testRemoveProduct()
	{

		$this->o->removeProduct('P1');
		$this->assertEquals($this->o->productCount(), 0);

		$this->o->addProduct('P1')->addProduct('P2', 2)->removeProduct('P1');
		$this->assertEquals($this->o->productCount(), 2);
	}

	/**
	 * Test that we can get an array back by querying ShippingOptions. We'll pass the pretend
	 * parameter in so that this doesn't actually hit Kunaki servers.
	 *
	 * @return void
	 */
	public function testGetShippingOptionsPretend()
	{
		$this->o->addProduct('X');
		$this->assertTrue(is_array($this->o->getShippingOptions(true)));
	}

	/**
	 * Test that we can get an array back by querying ShippingOptions. We'll pass the pretend
	 * parameter in so that this doesn't actually hit Kunaki servers.
	 *
	 * @expectedException BadMethodCallException
	 *
	 * @return void
	 */
	public function testGetShippingOptionNoproducts()
	{
		$destination = new Destination('United States', 'TX', '78755');
		$o           = new KunakiOrder($destination);
		$o->getShippingOptions(true);
	}

	/**
	 * Ping the Kunaki API get back shipping options and cache them properly. Make
	 * sure we can retrieve them from the cache.
	 *
	 * @return void
	 */
	public function testCacheShippingOptions()
	{
		$destination = new Destination('United States', 'TX', '78755');
		$o           = new KunakiOrder($destination);
		$o->addProduct(EXAMPLE_PRODUCT);
		$o->getShippingOptions();
	}

	/**
	 * Tests that we can't order without setting a Customer
	 *
	 * @expectedException BadMethodCallException
	 *
	 * @return void
	 */
	public function testOrderNoCustomer()
	{
		$destination = new Destination('United States', 'TX', '78755');
		$o           = new KunakiOrder($destination);
		$o->submitOrder(true);
	}

	/**
	 * Tests that we can't order without setting a Destination
	 *
	 * @expectedException BadMethodCallException
	 *
	 * @return void
	 */
	public function testOrderNoDestination()
	{
		$c = new Customer('Austin', null, '123 Road', 'Austin');
		$o = (new KunakiOrder)->setCustomer($c);
		$o->submitOrder(true);
	}

	/**
	 * Tests that we can't order without choosing any Products
	 *
	 * @expectedException BadMethodCallException
	 *
	 * @return void
	 */
	public function testOrderNoProducts()
	{
		$d = new Destination('United States', 'TX', '78755');
		$c = new Customer('Austin', null, '123 Road', 'Austin');
		$o = (new KunakiOrder($d))->addProduct(EXAMPLE_PRODUCT, 1);
		$o->setShippingOption($o->getShippingOptions()[0]);
		$o->setCustomer($c)->removeProduct(EXAMPLE_PRODUCT);
		$o->submitOrder(true);
	}

	/**
	 * Tests that we can submit an order
	 *
	 * @return void
	 */
	public function testSubmitOrder()
	{
		$d = new Destination('United States', 'TX', '78755');
		$c = new Customer('Austin', null, '123 Road', 'Austin');
		$o = (new KunakiOrder($d, $c))->addProduct(EXAMPLE_PRODUCT, 1);
		$o->setShippingOption($o->getShippingOptions()[0]);
		$o->submitOrder(true);
		$this->assertEquals($o->getOrderId(), '00000');
	}

}