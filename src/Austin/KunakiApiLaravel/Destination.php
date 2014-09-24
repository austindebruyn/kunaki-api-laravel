<?php namespace Austin\KunakiApiLaravel;

use Illuminate\Support\Facades\Validator;

define('GOOGLE_API_CACHE_PREFIX', 'GOOGLE_API');
define('GOOGLE_API_CACHE_TIMEOUT', '1440');

/**
 * This is an encapsulation of the information needed for Kunaki to ship orders.
 * 
 * @author Austin deBruyn
 * 
 * @api
 */
class Destination
{

	/**
	 * URL for Google Maps geocode API, which can be used to get information based on
	 * just a component of an address.
	 * 
	 * @var string
	 */
	private static $GOOGLE_MAPS_API = 'http://maps.googleapis.com/maps/api/geocode/';

	/**
	 * The Validator rules for an instance of Laravel's Illuminate\Support\Facades\Validator.
	 * 
	 * @var array
	 */
	private static $validator_rules = array(
		'country' 			=> 'required|min:2|max:64|regex:/^[\w \-\+]+$/',
		'state_province' 	=> 'required|min:2|max:64|regex:/^[\w \-\+]+$/',
		'postal_code' 		=> 'required|min:2|max:64|regex:/^[\w \+]+$/',
		);

	/**
	 * The country, in full text, ie. not url-encoded yet
	 * 
	 * @var string
	 */
	private $country;

	/**
	 * State or province
	 * 
	 * @var string
	 */
	private $state_province;

	/**
	 * Postal code, or zip code (5 digits)
	 * 
	 * @var string
	 */
	private $postal_code;

	/**
	 * Constructor.
	 * 
	 * @param string $country
	 * @param string $state_province
	 * @param string $postal_code
	 * 
	 * @throws InvalidArgumentException if input fails validation test
	 */
	public function __construct($country = '', $state_province = '', $postal_code = '')
	{
		$this->country = $country;
		$this->state_province = $state_province;
		$this->postal_code = $postal_code;

		if (! $this->validateInput())
			throw new \InvalidArgumentException("Input did not pass validation.");
	}

	/**
	 * Getter for country
	 * 
	 * @return string
	 */
	public function getCountry()
	{
		return $this->country;
	}

	/**
	 * Getter for the state/province
	 * 
	 * @return string
	 */
	public function getStateProvince()
	{
		return $this->state_province;
	}

	/**
	 * Getter for postal code
	 * 
	 * @return string
	 */
	public function getPostalCode()
	{
		return $this->postal_code;
	}

	/**
	 * Validates that the inputs are in the format we expected.
	 * 
	 * @throws \BadMethodCallException if Laravel's Validator Facade is not available
	 * 
	 * @return bool
	 */
	private function validateInput()
	{
		if (! class_exists('Validator'))
		{
			throw new \BadMethodCallException("\Illuminate\Support\Facades\Validator can't be found.");
		}

		$input = array(
			'country' 			=> $this->country,
			'state_province' 	=> $this->state_province,
			'postal_code' 		=> $this->postal_code,
			);

		$validator = Validator::make($input, self::$validator_rules);
		return $validator->passes();
	}

	/**
	 * Resolve the state_province and country member variables by querying
	 * Google Maps API
	 * 
	 * @return KunakiAPI\Destination|false
	 */
	public static function fromPostalCode($postal_code)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);
		$http_query  = self::$GOOGLE_MAPS_API;
		$http_query .= "json?";
		$http_query .= "address=";
		$http_query .= $postal_code;
		$http_query = substr($http_query, 0, 1024);

		if (\Cache::has(GOOGLE_API_CACHE_PREFIX.md5($http_query)))
		{
			$response = \Cache::get(GOOGLE_API_CACHE_PREFIX.md5($http_query));
		}
		else
		{
			curl_setopt($ch, CURLOPT_URL, $http_query);
			$response = curl_exec($ch);
			curl_close($ch);
			\Cache::put(GOOGLE_API_CACHE_PREFIX.md5($http_query), $response, GOOGLE_API_CACHE_TIMEOUT);
		}

		if (! $response)
		{
			return false;
		}

		$response = json_decode($response, true);
		$resolved_state_province = '';

		try
		{
			foreach($response['results'][0]['address_components'] as $component)
				if(in_array('country', $component['types']))
					$resolved_country = $component['long_name'];

			foreach($response['results'][0]['address_components'] as $component) 
				if(in_array('administrative_area_level_1', $component['types']))
					$resolved_state_province = $component[$resolved_country == 'Canada' ? 'long_name' : 'short_name'];
		}
		catch (\ErrorException $e)
		{
			/**
			 * \ErrorException: Undefined offset.
			 * This means Google has changed the format of the JSON returned by this
			 * API. This should really never happen. Handle it however you like.
			 */
		}

		if (!isset($resolved_country) || !isset($resolved_state_province))
		{
			return false;
		}

		$d = new Destination($resolved_country, $resolved_state_province, $postal_code);
		return $d;
	}

}
