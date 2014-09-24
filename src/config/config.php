<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Kunaki Email
	|--------------------------------------------------------------------------
	|
	| When your application is in debug mode, detailed error messages with
	| stack traces will be shown on every error that occurs within your
	| application. If disabled, a simple generic error page is shown.
	|
	*/
	'email' => ' your email here ',

	/*
	|--------------------------------------------------------------------------
	| Kunaki Password
	|--------------------------------------------------------------------------
	|
	| Because of the nature of Kunaki's HTTP API, this password will be embedded
	| in plaintext in the query string. Be careful.
	|
	*/
	'password' => ' password ',

	/*
	|--------------------------------------------------------------------------
	| API Protocol
	|--------------------------------------------------------------------------
	|
	| Kunaki offers a number of protocols to post orders.
	| 'http' : Plain HTTP connection. All of the information is contained in
	|          the query string.
	| 'https': Same as HTTP, but secure. The URI (including password) is still
	|          visible!!!
	| 'xml'  : Constructs an xml file to send over HTTPS. This is the most
	|          secure option. The only reason you wouldn't use this is if you
	|          don't have XML support for PHP, for some reason.
	|
	*/
	'protocol' => 'http',

	/*
	|--------------------------------------------------------------------------
	| Cache timeouts
	|--------------------------------------------------------------------------
	|
	| How long values added to the cache should persist. This associative array
	| should define timeouts (in minutes) for several things. We do this to play
	| nice with Kunaki's servers.
	| 'shipping_options': These probably will not change too often. You probably
	|                     shouldn't make any lower.
	| 'order_status'    : Timeout on polling the status of an order.
	|
	*/
	'timeout' => array(

		'shipping_options'		=> 1440,

		'order_status' 			=> 30,

	),
);
