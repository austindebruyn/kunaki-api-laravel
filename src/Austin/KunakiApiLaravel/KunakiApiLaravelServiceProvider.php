<?php namespace Austin\KunakiApiLaravel;

use Illuminate\Support\ServiceProvider;

class KunakiApiLaravelServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Called right before a request is routed.
	 * 
	 * @return void
	 */
	public function boot()
	{
		$this->package('austin/kunaki-api-laravel');

	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [];
	}

}
