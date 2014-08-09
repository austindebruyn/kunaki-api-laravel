<?php namespace Austin\KunakiApiLaravel;

use Illuminate\Cache;

define('KUNAKI_API_HOST', "https://kunaki.com/HTTPService.ASP");
define('CACHE_KEY_PREFIX', 'KUNAKI');
define('ORDER_STATUS_CACHE_KEY_PREFIX', 'KUNAKI_ORDER_STATUS');
define('CACHE_TIMEOUT', '1440');
define('ORDER_STATUS_CACHE_TIMEOUT', '30');

class KunakiOrder
{
	/**
	 * The end-result of this Kunaki API request.
	 * 
	 * @param Destination
	 */
	private $destination;

	/**
	 * The customer to ship to.
	 * 
	 * @param Customer
	 */
	private $customer;

	/**
	 * The array containing products, which is a pair of string productID and integer quantity
	 * 
	 * @param array
	 */
	private $product_list = array();

	/**
	 * This array holds the ShippingObject options that Kunaki returned. You can only
	 * populate this list by calling the getShippingOptions method. Certain methods will
	 * set this array to null, like changing the product_list or Destination.
	 * 
	 * @param array|null
	 */
	private $shipping_options = null;

	/**
	 * Holds a bool representing whether or not the order has been submitted to Kunaki's
	 * servers. This is to make sure we don't submit more than once.
	 * 
	 * @param bool
	 */
	private $submitted = false;

	/**
	 * Holds the order ID received from Kunaki. Until $submitted is true, this string will
	 * be empty.
	 * 
	 * @param string
	 */
	private $order_id;

	/**
	 * Constructor.
	 * 
	 * @param Destination|null $destination
	 * @param Customer|null    $customer
	 * 
	 * @api
	 */
	public function __construct(Destination $destination = null, Customer $customer = null) {

		$this->destination = $destination;
		$this->customer    = $customer;
	}

	/**
	 * Adds a product into this order
	 * 
	 * @param string  $productID An alphanumberic string representing Kunaki.com product
	 * @param integer quantity   Integer amount of products to order
	 * 
	 * @throws InvalidArgumentException is invalid input
	 * 
	 * @api
	 * 
	 * @return Destination this instance
	 */
	public function addProduct($productID, $quantity = 1) {

		$this->shipping_options = null;

		if (!is_integer($quantity) || $quantity < 1)
			throw new InvalidArgumentException("Quantity should be an integer > 1.");
		if (!is_string($productID) || strlen($productID) < 1 || !preg_match('/^\w+$/', $productID))
			throw new InvalidArgumentException("ProductID should be an alphanumberic string.");

		foreach ($this->product_list as &$p)
			if ($p[0] == $productID) {
				$p[1] += $quantity;
				return $this;
			}

		array_push($this->product_list, array($productID, $quantity));

		return $this;
	}

	/**
	 * Removes the product from this order. If not quantity is given, removes them all
	 * 
	 * @param string  $productID
	 * @param integer quantity
	 * 
	 * @api
	 * 
	 * @return Destination this instance
	 */
	public function removeProduct($productID, $quantity = -1) {

		$this->shipping_options = null;

		for ($i = 0; $i < count($this->product_list); $i++)
			if ($this->product_list[$i][0] == $productID) {
				$this->product_list[$i][1] -= $quantity == -1 ? $this->product_list[$i][1] : $quantity;
				if ($this->product_list[$i][1] <= 0) {
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
	 * @api
	 * 
	 * @return integer
	 */
	public function productCount() {

		$c = 0;
		foreach ($this->product_list as $p)
			$c += $p[1];
		return $c;
	}

	/**
	 * Gets the Destination object.
	 * 
	 * @api
	 * 
	 * @return Destination
	 */
	public function getDestination() {
		return $this->destination;
	}

	/**
	 * Gets the Customer object.
	 * 
	 * @api
	 * 
	 * @return Customer
	 */
	public function getCustomer() {
		return $this->customer;
	}

	/**
	 * Gets an array of shipping options by querying Kunaki's server. This function will cache
	 * the results to increase performance, reduce load on their server, and to fix simple
	 * errors like using this function as the pivot of a foreach, etc.
	 * 
	 * @param bool $pretend
	 * 
	 * @throws BadMethodCallException if there are no products or no destination yet associated with this order
	 * @throws ErrorException 		  if Kunaki returns an exception in their response
	 * 
	 * @api
	 * 
	 * @return array of ShippingOption
	 */
	public function getShippingOptions($pretend = false) {

		// You need to set $destination to get shipping options, but not $customer.
		if (!$this->destination)
			throw new \BadMethodCallException("You have to set a Destination to ship to.");

		// We need products in this order, or Kunaki won't know what to do
		if ($this->productCount() < 1)
			throw new \BadMethodCallException("Must have one or more products to query shipping options.");

		// Build an HTTP query
		$http_query  = KUNAKI_API_HOST."?RequestType=ShippingOptions";
		$http_query .= "&State_Province=".$this->destination->getStateProvince();
		$http_query .= "&PostalCode=".$this->destination->getPostalCode();
		$http_query .= "&Country=".$this->destination->getCountry();
		foreach ($this->product_list as $product)
			$http_query .= "&ProductId=".$product[0]."&Quantity=".$product[1];
		$http_query .= "&ResponseType=xml";
		$http_query = substr($http_query, 0, 1024);
		$http_query = str_replace(' ', '+', $http_query);

		if ($pretend)
			return array();

		// Try to fetch shipping options from cache before pinging server
		$cache_key = CACHE_KEY_PREFIX.md5($http_query);

		if (\Cache::has($cache_key))
			return $this->shipping_options = \Cache::get($cache_key);
		else {
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
			throw new ErrorException('Kunaki API: '.$xdoc->ErrorText);

		$this->shipping_options = array();

		foreach($xdoc->Option as $option) {
			if (!isset($option->Description) || !isset($option->DeliveryTime) || !isset($option->Price))
				throw new ErrorException('Kunaki API: Malformed XML response.');
			array_push($this->shipping_options, new ShippingOption((string)$option->Description,
															(string)$option->DeliveryTime,
															(float)$option->Price));
		}

		\Cache::add($cache_key, $this->shipping_options, CACHE_TIMEOUT);
		return $this->shipping_options;
	}

	/**
	 * Gets the order id. This is meaningless until submitOrder is called.
	 * 
	 * @throws BadMethodCallException if you try to query this before the order is submitted
	 * 
	 * @api
	 * 
	 * @return string
	 */
	public function getOrderId() {
		if (! $this->submitted)
			throw new \BadMethodCallException('This order has not yet been submitted.');
		return $this->order_id;
	}

	/**
	 * Sets the Destination. This will clear the shipping options if there are any,
	 * because that list is bound to the Destination and the product list. Returns $this.
	 * 
	 * @api
	 * 
	 * @return this
	 */
	public function setDestination(Destination $destination) {
		$this->destination = $destination;
		$this->shipping_options = null;
		return $this;
	}

	/**
	 * Sets the Customer.
	 * 
	 * @api
	 * 
	 * @return this
	 */
	public function setCustomer(Customer $customer) {
		$this->customer = $customer;
		return $this;
	}

	/**
	 * Submits the order to Kunaki's servers. This method can only be invoked once
	 * per KunakiOrder object. You need to set a Destination, a Customer, and some products.
	 * Returns Kunaki's order Id.
	 * 
	 * @param integer $shippingOptionIndex the index into $shipping_options
	 * @param bool    $pretend             if true, the order will be faked
	 * 
	 * @throws OutOfRangeException if the index provided does not work
	 * 
	 * @api
	 * 
	 * @return string $order_id
	 */
	public function submitOrder($shippingOptionIndex, $pretend = false) {

		if ($this->submitted)
			throw new \BadMethodCallException("This order has already been submitted.");

		if (!$this->destination || !$this->customer)
			throw new \BadMethodCallException("You need a Destination and a Customer to submit an order.");

		if (!$this->shipping_options)
			{$this->getShippingOptions(); dd($this->shipping_options);}
		if (!isset($this->shipping_options[$shippingOptionIndex]))
			throw new \OutOfRangeException;

		// Build an HTTP query
		$http_query  = KUNAKI_API_HOST."?RequestType=Order";
		$http_query .= "&UserId=".\Config::get('kunaki-api-laravel::email');
		$http_query .= "&Password=".\Config::get('kunaki-api-laravel::password');
		$http_query .= "&Mode=". ($pretend? 'Test' : 'Live');
		$http_query .= "&Name=".urlencode($this->customer->getName());
		$http_query .= "&Company=".urlencode($this->customer->getCompany());
		for ($i = 0; $i < 2; $i++)
			if (isset($this->customer->getAddress()[$i]))
		$http_query .= "&Address".($i+1)."=".urlencode($this->customer->getAddress()[$i]);
		$http_query .= "&City=".urlencode($this->customer->getCity());
		$http_query .= "&State_Province=".urlencode($this->destination->getStateProvince());
		$http_query .= "&PostalCode=".urlencode($this->destination->getPostalCode());
		$http_query .= "&Country=".urlencode($this->destination->getCountry());
		$http_query .= "&ShippingDescription=".urlencode($this->shipping_options[$shippingOptionIndex]->getName());
		foreach ($this->product_list as $product)
			$http_query .= "&ProductId=".urlencode($product[0])."&Quantity=".urlencode($product[1]);
		$http_query .= "&ResponseType="."xml";

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

		if ($xdoc->ErrorCode != '0')
			throw new \ErrorException('Kunaki API: '.$xdoc->ErrorText);

		$this->submitted = true;
		$this->order_id = $xdoc->OrderId;
		return $this->order_id;
	}

	/**
	 * Gets the status of an order.
	 * 
	 * @throws BadMethodCallException if you try to query this before the order is submitted
	 * 
	 * @api
	 * 
	 * @return array
	 */
	public function getOrderStatus() {
		if (! $this->submitted)
			throw new \BadMethodCallException('This order has not yet been submitted.');

		// Build an HTTP query
		$http_query  = KUNAKI_API_HOST."?RequestType=OrderStatus";
		$http_query .= "&UserId=".\Config::get('kunaki-api-laravel::email');
		$http_query .= "&Password=".\Config::get('kunaki-api-laravel::password');
		$http_query .= "&OrderId=".$this->order_id;
		$http_query .= "&ResponseType="."xml";

		if (\Cache::has(ORDER_STATUS_CACHE_KEY_PREFIX.md5($http_query)))
			return \Cache::get(ORDER_STATUS_CACHE_KEY_PREFIX.md5($http_query));

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

		if ($xdoc->ErrorCode != '0')
			throw new \ErrorException('Kunaki API: '.$xdoc->ErrorText);

		$orderStatus = array();

		if (isset($xdoc->OrderStatus))
			$orderStatus['order_status']	= (string)$xdoc->OrderStatus;
		if (isset($xdoc->TrackingType))
			$orderStatus['tracking_type'] 	= (string)$xdoc->TrackingType;
		if (isset($xdoc->TrackingId))
			$orderStatus['tracking_id'] 	= (string)$xdoc->TrackingId;

		\Cache::put(ORDER_STATUS_CACHE_KEY_PREFIX.md5($http_query), $orderStatus, ORDER_STATUS_CACHE_TIMEOUT);

		return $orderStatus;
	}


}