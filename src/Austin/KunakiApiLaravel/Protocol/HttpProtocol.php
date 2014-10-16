<?php namespace Austin\KunakiApiLaravel\Protocol;

class HttpProtocol implements ProtocolInterface {

	/**
	 * The order to encode.
	 * 
	 * @var KunakiOrder
	 */
	private $order;

    /**
     * Whether or not to build the http query with pretend or test mode on.
     *
     * @var bool
     */
    private $pretend;

	/**
	 * Constructor.
	 * 
	 * @param  KunakiOrder|null
	 */
	public function __construct(\Austin\KunakiApiLaravel\KunakiOrder $order = null, $pretend = false)
	{
		if ( ! is_null($order))	$this->setOrder($order);

        $this->pretend = $pretend;
	}

	/**
	 * This should send the order to Kunaki. Returns true if successful.
	 * 
	 * @return void
	 */
	public function send()
	{
		$query = $this->buildQuery();
		$response = $this->sendQuery($query);
		$xdoc = simplexml_load_string($response);

		if ($xdoc->ErrorCode != '0') throw new \ErrorException('Kunaki API: '.$xdoc->ErrorText);

		return true;
	}

	/**
	 * Sets the order.
	 * 
	 * @param  KunakiOrder  $order
	 * @return void
	 */
	public function setOrder(\Austin\KunakiApiLaravel\KunakiOrder $order)
	{
		$this->order = $order;
	}

	/**
	 * Builds an HTTP query string from the Order's properties.
	 *
     * @param  string  $email
     * @param  string  $password
	 * @return string
	 */
	private function buildQuery($email = '', $password = '')
	{
        // Assign an email and password from the config file if they aren't specified.
        $email ?: \Config::get('kunaki-api-laravel::email');
        $password ?: \Config::get('kunaki-api-laravel::password');

		// Build an HTTP query
		$http_query  = KUNAKI_API_HOST."?RequestType=Order";
		$http_query .= "&UserId=".urlencode($email);
		$http_query .= "&Password=".urlencode($password);
		$http_query .= "&Mode=". ($this->pretend ? 'Test' : 'Live');
		$http_query .= "&Name=".urlencode($this->order->customer->getName());
		$http_query .= "&Company=".urlencode($this->order->customer->getCompany());

        // Build the address into the query string. In some cases, non-US addresses can have three lines,
        // for instance in some provinces in Mexico. I'm not sure how to handle these, since Kunaki only
        // allows for two-line addresses.
		for ($i = 0; $i < 2; $i++)
		{
			if (isset($this->order->customer->getAddress()[$i]))
			{
				$http_query .= "&Address".($i+1)."=".urlencode($this->order->customer->getAddress()[$i]);
			}
		}

		$http_query .= "&City=".urlencode($this->order->customer->getCity());
		$http_query .= "&State_Province=".urlencode($this->order->destination->getStateProvince());
		$http_query .= "&PostalCode=".urlencode($this->order->destination->getPostalCode());
		$http_query .= "&Country=".urlencode($this->order->destination->getCountry());
		$http_query .= "&ShippingDescription=".urlencode($this->order->shipping_options[$this->order->shipping_option_index]->getName());
		foreach ($this->order->product_list as $product)
			$http_query .= "&ProductId=".urlencode($product[0])."&Quantity=".urlencode($product[1]);
		$http_query .= "&ResponseType="."xml";

		return $http_query;
	}
	
	/**
	 * Sends the HTTP query using cURL. You can optional pass a timeout parameter which will
     * limit the time the Kunaki server has to respond in.
	 * 
	 * @param  string  $query
     * @param  integer $timeout
	 * @return string
	 */
	private function sendQuery($query, $timeout = -1)
	{
        if ($timeout < 0) $timeout = \Config::get('kunaki-api-laravel::http-timeout')

		// cURL off the query
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);

		curl_setopt($ch, CURLOPT_URL, $http_query);
		$content = curl_exec($ch);
		curl_close($ch);

		return $content;
	}

}