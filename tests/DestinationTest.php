<?php

use Austin\KunakiApiLaravel\Destination;

class DestinationTest extends TestCase {

	/**
	 * Tests that an object can be instantiated and has the given parameters.
	 * 
	 * @return void
	 */
	public function testNew() {

		$country = 'United States';
		$state_province = 'TX';
		$postal_code = '78755';
		$d = new Destination($country, $state_province, $postal_code);
		$this->assertEquals($d->getCountry(), $country);
		$this->assertEquals($d->getStateProvince(), $state_province);
		$this->assertEquals($d->getPostalCode(), $postal_code);
	}

	/**
	 * Tests that an object with bogus input can't be created.
	 * 
	 * @return void
	 */
	public function testBogusInput() {

		$input = array(
			array('United States%20', 'TX', '78755'),
			array('United States', 'T`X', '78755'),
			array('United States%20', 'TX', '78755>'),
			array('', 'TX', '78755'),
			array('United States%20', '', '78755'),
			array('', '', ''),
			array('?', '?', '?'),
		);

		$exceptionsCaught = 0;

		for ($i = 0; $i < count($input); $i++)
			try {
				new Destination($input[$i][0], $input[$i][1], $input[$i][2]);
			}
			catch (InvalidArgumentException $e) { $exceptionsCaught++; }

		$this->assertEquals($exceptionsCaught, count($input));
	}

	/**
	 * Tests that resolving the Destination from just the postal code works.
	 * 
	 * @return void
	 */
	public function testFromPostalCode() {

		$d = Destination::fromPostalCode('78755');
		$e = Destination::fromPostalCode('K1B');

		if (!$d or !$e)
			throw new InvalidArgumentException();
		
		$this->assertEquals($d->getCountry(), 'United States');
		$this->assertEquals($d->getStateProvince(), 'TX');

		$this->assertEquals($e->getCountry(), 'Canada');
		$this->assertEquals($e->getStateProvince(), 'Ontario');
	}

	/**
	 * Tests that a bogus postal code doesn't crash, just returns false.
	 * 
	 * @return void
	 */
	public function testFromBadPostalCode() {

		$d = Destination::fromPostalCode('');
		$this->assertEquals($d, false);
	}


}