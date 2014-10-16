<?php

use Austin\KunakiApiLaravel\ShippingOption;

class ShippingOptionTest extends TestCase {

	/**
	 * Tests that we have an instantiated ShippingOption
	 * 
	 * @return void
	 */
	public function testNew() {

		$s = new ShippingOption('Carrier Pigeon', '4-6 weeks', 20);
		$this->assertEquals($s->getName(), 'Carrier Pigeon');
	}

	/**
	 * Test that you can't provide bad or empty input
	 * 
	 * @expectedException InvalidArgumentException
	 * 
	 * @return void
	 */
	public function testBadInput() {

		$s = new ShippingOption('', '', '', 0);
	}

}