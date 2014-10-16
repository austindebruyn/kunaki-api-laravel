<?php namespace Austin\KunakiApiLaravel\Protocol;

/**
 * This defines a contract that different protocols should adhere to.
 * Kunaki's API allows you to connect over several protocols. The one
 * to use is specified in this package's config array.
 */
interface ProtocolContract {

	/**
	 * This should send the order to Kunaki. Returns true if successful.
	 * 
	 * @return void
	 */
	function send();

	/**
	 * This binds the order object to this Protocol.
	 * 
	 * @param  \Austin\KunakiApiLaravel\KunakiOrder  $order
	 * @return void
	 */
	function setOrder(\Austin\KunakiApiLaravel\KunakiOrder $order);
	
}