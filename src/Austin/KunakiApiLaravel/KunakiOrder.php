<?php namespace Austin\KunakiApiLaravel;

use Illuminate\Cache;

define('KUNAKI_API_HOST', "https://kunaki.com/HTTPService.ASP");
define('CACHE_KEY_PREFIX', 'KUNAKI');
define('ORDER_STATUS_CACHE_KEY_PREFIX', 'KUNAKI_ORDER_STATUS');

class KunakiOrder
{
	/**
	 * The end-result of this Kunaki API request.
	 *
	 * @var Destination
	 */
	private $destination;

	/**
	 * The customer to ship to.
	 *
	 * @var Customer
	 */
	private $customer;

	/**
	 * The array containing products, which is a pair of string productID and integer quantity
	 *
	 * @var array
	 */
	private $product_list = array();

	/**
	 * This array holds the ShippingObject options that Kunaki returned. You can only
	 * populate this list by calling the getShippingOptions method. Certain methods will
	 * set this array to null, like changing the product_list or Destination.
	 *
	 * @var array|null
	 */
	private $shipping_options = null;

	/**
	 * This integer is the index into the above array of shipping options that the user
	 * selected.
	 *
	 * @var integer
	 */
	private $shipping_option_index = -1;

	/**
	 * Holds a bool representing whether or not the order has been submitted to Kunaki's
	 * servers. This is to make sure we don't submit more than once.
	 *
	 * @var bool
	 */
	private $submitted = false;

	/**
	 * Holds the order ID received from Kunaki. Until $submitted is true, this string will
	 * be empty.
	 *
	 * @var string
	 */
	private $order_id;

	/**
	 * Constructor.
	 *
	 * @param Destination|null $destination
	 * @param Customer|null $customer
	 */
	public function __construct(Destination $destination = null, Customer $customer = null)
	{

		$this->destination = $destination;
		$this->customer    = $customer;
	}

	/**
	 * Adds a product into this order
	 *
	 * @param string $productID An alphanumberic string representing Kunaki.com product
	 * @param integer quantity   Integer amount of products to order
	 *
	 * @throws InvalidArgumentException is invalid input
	 *
	 * @return Destination this instance
	 */
	public function addProduct($productID, $quantity = 1)
	{

		$this->shipping_options = null;

		if ( ! is_integer($quantity) || $quantity < 1)
			throw new \InvalidArgumentException("Quantity should be an integer > 1.");
		if ( ! is_string($productID) || strlen($productID) < 1 || ! preg_match('/^\w+$/', $productID))
			throw new \InvalidArgumentException("ProductID should be an alphanumberic string.");

		foreach ($this->product_list as &$p)
			if ($p[0] == $productID)
			{
				$p[1] += $quantity;

				return $this;
			}

		array_push($this->product_list, array($productID, $quantity));

		return $this;
	}

	/**
	 * Removes the product from this order. If not quantity is given, removes them all
	 *
	 * @param string $productID
	 * @param integer quantity
	 *
	 * @return Destination this instance
	 */
	public function removeProduct($productID, $quantity = -1)
	{

		$this->shipping_options = null;

		for ($i = 0; $i < count($this->product_list); $i++)
			if ($this->product_list[$i][0] == $productID)
			{
				$this->product_list[$i][1] -= $quantity == -1 ? $this->product_list[$i][1] : $quantity;
				if ($this->product_list[$i][1] <= 0)
				{
					unset($this->product_list[$i]);
					$this->product_list = array_values($this->product_list);
				}

				return $this;
			}

		return $this;
	}

	/**
	 * Returns the total count of products in this order. Equal to the sum of all quantities
	 * in the product_list array.
	 *
	 * @return integer
	 */
	public function productCount()
	{
		$c = 0;
		foreach ($this->product_list as $p)
			$c += $p[1];

		return $c;
	}

	/**
	 * Gets the Destination object.
	 *
	 * @return Destination
	 */
	public function getDestination()
	{
		return $this->destination;
	}

	/**
	 * Gets the Customer object.
	 *
	 * @api
	 *
	 * @return Customer
	 */
	public function getCustomer()
	{
		return $this->customer;
	}

	/**
	 * Gets an array of shipping options by querying Kunaki's server. This function will cache
	 * the results to increase performance, reduce load on their server, and to fix simple
	 * errors like using this function as the pivot of a foreach, etc.
	 *
	 * @param  bool  $pretend
	 * @throws \BadMethodCallException if there are no products or no destination yet associated with this order
	 * @throws \ErrorException          if Kunaki returns an exception in their response
	 * @return Array
	 */
	public function getShippingOptions($pretend = false)
	{
		// You need to set $destination to get shipping options, but not $customer.
		if ( ! $this->destination)
			throw new \BadMethodCallException("You have to set a Destination to ship to.");

		// We need products in this order, or Kunaki won't know what to do
		if ($this->productCount() < 1)
			throw new \BadMethodCallException("Must have one or more products to query shipping options.");

		// Build an HTTP query
		$http_query = KUNAKI_API_HOST . "?RequestType=ShippingOptions";
		$http_query .= "&State_Province=" . $this->destination->getStateProvince();
		$http_query .= "&PostalCode=" . $this->destination->getPostalCode();
		$http_query .= "&Country=" . $this->destination->getCountry();

		array_map(
			function ($p) use (&$http_query)
			{
				$http_query .= "&ProductId=" . $p[0] . "&Quantity=" . $p[1];
			},
			$this->product_list
		);

		$http_query .= "&ResponseType=xml";
		$http_query = substr($http_query, 0, 1024);
		$http_query = str_replace(' ', '+', $http_query);

		if ($pretend) return array();

		// Try to fetch shipping options from cache before pinging server
		$cache_key = CACHE_KEY_PREFIX . md5($http_query);

		if (\Cache::has($cache_key))
		{
			return $this->shipping_options = \Cache::get($cache_key);
		} else
		{
			// Fetch response via cURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 8);

			curl_setopt($ch, CURLOPT_URL, $http_query);
			$content = curl_exec($ch);
			curl_close($ch);
		}

		// Parse through retrieved document as XML
		$xdoc = simplexml_load_string($content);

		if ($xdoc->ErrorCode != '0')
			throw new \ErrorException('Kunaki API: ' . $xdoc->ErrorText);

		$this->shipping_options = array();

		// Loop over each element returned in the XML document, creating a ShippingOption model
		// for it.
		foreach ($xdoc->Option as $option)
		{
			if ( ! isset($option->Description) || ! isset($option->DeliveryTime) || ! isset($option->Price))
				throw new \ErrorException('Kunaki API: Malformed XML response.');

			// Create a ShippingOption model based off of data from the XML. Give it an index based on
			// the size of the shipping options array.
			$d           = (string)$option->Description;
			$e           = (string)$option->DeliveryTime;
			$p           = (float)$option->Price;
			$i           = count($this->shipping_options);
			$optionModel = new ShippingOption($d, $e, $p, $i);

			array_push($this->shipping_options, $optionModel);
		}

		\Cache::add($cache_key, $this->shipping_options, \Config::get('kunaki-api-laravel::timeout')['shipping_options']);

		return $this->shipping_options;
	}

	/**
	 * Gets the order id. This is meaningless until submitOrder is called.
	 *
	 * @throws BadMethodCallException if you try to query this before the order is submitted
	 *
	 * @return string
	 */
	public function getOrderId()
	{
		if ( ! $this->submitted)
		{
			throw new \BadMethodCallException('This order has not yet been submitted.');
		}

		return $this->order_id;
	}

	/**
	 * Gets the currently selected ShippingOption. If none has been selected yet, this
	 * will return null.
	 *
	 * @return ShippingOption
	 */
	public function getShippingOption()
	{
		if ($this->shipping_option_index < 0) return null;

		return $this->shipping_options[$this->shipping_option_index];
	}

	/**
	 * Sets the Destination. This will clear the shipping options if there are any,
	 * because that list is bound to the Destination and the product list. Returns $this.
	 *
	 * @return KunakiOrder
	 */
	public function setDestination(Destination $destination)
	{
		$this->destination      = $destination;
		$this->shipping_options = null;

		return $this;
	}

	/**
	 * Sets the Customer.
	 *
	 * @return KunakiOrder
	 */
	public function setCustomer(Customer $customer)
	{
		$this->customer = $customer;

		return $this;
	}

	/**
	 * Sets the current shipping option.
	 *
	 * @throws BadMethodCallException
	 * @param  ShippingOption $opt
	 * @return void
	 */
	public function setShippingOption(ShippingOption $opt)
	{
		if ( ! $this->shipping_options)
		{
			throw new \BadMethodCallException('Query the list of shipping options before picking one.');
		}

		for ($i = 0; $i < count($this->shipping_options); $i++)
		{
			// We'll test for object equality here. Since a ShippingOption is a
			// read-only object, this should return true if you handle the same
			// object and don't do anything funny.
			if ($this->getShippingOptions()[$i] == $opt)
			{
				$this->shipping_option_index = $i;

				return;
			}
		}

		throw new \InvalidArgumentException('The shipping option provided (' . $opt->getName() . ') did not come from this order.');
	}

	/**
	 * Submits the order to Kunaki's servers. This method can only be invoked once
	 * per KunakiOrder object. You need to set a Destination, a Customer, and some products.
	 * Returns Kunaki's order Id.
	 *
	 * @param bool    $pretend
	 * @throws \OutOfRangeException if the index provided does not work
	 * @return string $order_id
	 */
	public function submitOrder($pretend = false)
	{
		if ($this->submitted)
			throw new \BadMethodCallException("This order has already been submitted.");

		if ( ! $this->destination || ! $this->customer)
			throw new \BadMethodCallException("You need a Destination and a Customer to submit an order.");

		if (is_null($this->getShippingOption()))
			throw new \BadMethodCallException("Pick a ShippingOption before submitting order.");

		$protocol = new Protocol\HttpProtocol($this, true);
		$response = $protocol->send();

		$this->submitted = true;
		$this->order_id  = $response->getId();

		return $this->order_id;
	}

	/**
	 * Gets the status of an order.
	 *
	 * @throws \BadMethodCallException if you try to query this before the order is submitted
	 * @throws \ErrorException if Kunaki's server returns an error
	 * @return array
	 */
	public function getOrderStatus()
	{
		if ( ! $this->submitted) throw new \BadMethodCallException('This order has not yet been submitted.');

		// Build an HTTP query
		$http_query = KUNAKI_API_HOST . "?RequestType=OrderStatus";
		$http_query .= "&UserId=" . \Config::get('kunaki-api-laravel::email');
		$http_query .= "&Password=" . \Config::get('kunaki-api-laravel::password');
		$http_query .= "&OrderId=" . $this->order_id;
		$http_query .= "&ResponseType=" . "xml";

		if (\Cache::has(ORDER_STATUS_CACHE_KEY_PREFIX . md5($http_query)))
		{
			return \Cache::get(ORDER_STATUS_CACHE_KEY_PREFIX . md5($http_query));
		}

		// cURL off the query
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);

		curl_setopt($ch, CURLOPT_URL, $http_query);
		$content = curl_exec($ch);
		curl_close($ch);

		// Parse the response
		$xdoc = simplexml_load_string($content);

		if ($xdoc->ErrorCode != '0') throw new \ErrorException('Kunaki API: ' . $xdoc->ErrorText);

		$orderStatus = array();

		if (isset($xdoc->OrderStatus))
			$orderStatus['order_status'] = (string)$xdoc->OrderStatus;
		if (isset($xdoc->TrackingType))
			$orderStatus['tracking_type'] = (string)$xdoc->TrackingType;
		if (isset($xdoc->TrackingId))
			$orderStatus['tracking_id'] = (string)$xdoc->TrackingId;

		\Cache::put(
			ORDER_STATUS_CACHE_KEY_PREFIX . md5($http_query), $orderStatus,
			\Config::get('kunaki-api-laravel::timeout')['order_status']
		);

		return $orderStatus;
	}


}