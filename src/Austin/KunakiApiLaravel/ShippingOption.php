<?php namespace Austin\KunakiApiLaravel;

/**
 * This class is just a wrapper around an element of the XML response Kunaki.com can
 * return. We take care to validate input for such a simple object because we are receiving
 * the input from a third-party website. If something goes wrong on their end it would
 * be much harder to debug.
 * 
 */
class ShippingOption
{

	/**
	 * Name
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * Delivery time as a string, ie. "2-5 business days"
	 * 
	 * @var string
	 */
	private $delivery_time;

	/**
	 * Price in USD as a float value.
	 * 
	 * @var float
	 */
	private $price;

	/**
	 * Constructor.
	 * 
	 * @param string $name
	 * @param string $delivery_time
	 * @param float  $price
	 * 
	 * @throws InvalidArgumentException if invalid data
	 * 
	 * @api
	 */
	public function __construct($name, $delivery_time, $price)
	{

		if (!is_string($name) || strlen($name) < 1)
			throw new \InvalidArgumentException("ShippingOption->\$name ($name) is required and must be a string.");
		
		if (!is_string($delivery_time) || strlen($delivery_time) < 1)
			throw new \InvalidArgumentException("ShippingOption->\$delivery_time ($delivery_time) is required and must be a string.");
		
		$price = floatval($price);

		if (!is_float($price) || $price < 0)
			throw new \InvalidArgumentException("ShippingOption->\$price ($price) must be positive float.");

		$this->name = $name;
		$this->delivery_time = $delivery_time;
		$this->price = $price;
	}

	/**
	 * Gets the name.
	 * 
	 * @api
	 * 
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Gets the delivery time.
	 * 
	 * @api
	 * 
	 * @return string
	 */
	public function getDeliveryTime()
	{
		return $this->delivery_time;
	}

	/**
	 * Gets the price.
	 * 
	 * @api
	 * 
	 * @return float
	 */
	public function getPrice()
	{
		return $this->price;
	}

}
