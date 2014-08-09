<?php

use Austin\KunakiApiLaravel\Customer;
use Austin\KunakiApiLaravel\Destination;

class CustomerTest extends TestCase {

	/**
	 * Tests that an object can be instantiated and has the given parameters.
	 * 
	 * @return void
	 */
	public function testNew() {

		$c = new Customer('Austin', '', '123 Road', 'Austin');
		$this->assertEquals($c->getAddress(), array('123 Road'));
	}

	/**
	 * Tests that an object with bogus input can't be created.
	 * 
	 * @return void
	 */
	public function testBogusInput() {

		$twoHundredChars = '';
		for ($i = 0; $i < 200; $i++)
			$twoHundredChars .= 'A';

		$input = array(
			array('A', '', 'A', array(array('A'))),
			array('A', '', 'A', array('A')),
			array('', '', 'A', array('A')),
			array('A', '', 'A', array()),
			array(array('A'), '', 'A', ''),
			array('A', '', 'A', array('A', 'B', 'C', 'D')),
			array($twoHundredChars, '', 'A', 'A'),
			array('A', '', 'A', $twoHundredChars),
			array('A', '', 'A', array('A', $twoHundredChars)),
			array('A', $twoHundredChars, 'A', array('A', 'A')),
			array('A', 'A', '', 'A'),
			array('A', 'A', $twoHundredChars, 'A'),
			array('A', 'A', array('A'), 'A'),
			);

		$exceptionsCaught = 0;

		for ($i = 0; $i < count($input); $i++)
			try {
				new Customer($input[$i][0], $input[$i][1], $input[$i][2], $input[$i][3]);
			}
			catch (InvalidArgumentException $e) { $exceptionsCaught++; }

		$this->assertEquals($exceptionsCaught, count($input));
	}

}