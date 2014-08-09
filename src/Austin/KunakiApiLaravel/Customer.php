<?php namespace Austin\KunakiApiLaravel;

use \InvalidArgumentException;
use Illuminate\Support\Facades\Validator;

class Customer
{

	/**
	 * The Validator rules for an instance of Laravel's Illuminate\Support\Facades\Validator.
	 * 
	 * @var array
	 */
	private static $validator_rules = array(
		'name' 				=> 'required|min:2|max:128',
		'address' 			=> 'required|array',
		'company' 			=> 'min:1|max:128',
		'city' 				=> 'required|min:1|max:128',
		);

	/**
	 * Customer's full name for shipping.
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * Company name. This can be null, but not empty.
	 * 
	 * @var string|null
	 */
	private $company;

	/**
	 * Shipping address. This translates into an array of lines, whether it's a single,
	 * double, or triple line address.
	 * 
	 * @var array of string
	 */
	private $address;

	/**
	 * City name.
	 * 
	 * @var string
	 */
	private $city;

	/**
	 * Constructor.
	 * 
	 * @param string       $name
	 * @param string|null  $company
	 * @param string|array $address
	 * @param string 	   $city
	 * 
	 * @api
	 */
	public function __construct($name, $company, $address, $city) {

		$this->name        = $name;
		$this->company     = $company ? strlen($company) < 1 ? null : $company : null;
		$this->address     = is_string($address) ? explode("\n", $address) : $address;
		$this->city        = $city;

		if (! $this->validateInput())
			throw new InvalidArgumentException("Input did not pass validation.");
	}

	/**
	 * Validates that the inputs are in the format we expected.
	 * 
	 * @throws BadMethodCallException if Laravel's Validator Facade is not available
	 * 
	 * @return bool
	 */
	private function validateInput() {

		if (!is_string($this->city) || !is_string($this->name))
				return false;
		if (count($this->address) == 0 || count($this->address) > 3)
			return false;
		foreach ($this->address as $line)
			if (!is_string($line) || strlen($line) > 127)
				return false;

		if (! class_exists('Validator'))
			throw new BadMethodCallException("Illuminate\Support\Facades\Validator can't be found.");

		$input = array(
			'name' 			=> $this->name,
			'company' 		=> $this->company,
			'address' 		=> $this->address,
			'city' 			=> $this->city,
			);

		$validator = Validator::make($input, self::$validator_rules);
		return $validator->passes();
	}

	/**
	 * Gets the Customer's name.
	 * 
	 * @api
	 * 
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Gets the Customer's company.
	 * 
	 * @api
	 * 
	 * @return string|null
	 */
	public function getCompany() {
		return $this->company;
	}

	/**
	 * Gets the Customer's address.
	 * 
	 * @api
	 * 
	 * @return array
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * Gets the Customer's city.
	 * 
	 * @api
	 * 
	 * @return array
	 */
	public function getCity() {
		return $this->city;
	}

}
