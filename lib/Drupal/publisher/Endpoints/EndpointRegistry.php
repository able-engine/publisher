<?php

namespace Drupal\publisher\Endpoints;
use Drupal\publisher\Transaction;

class EndpointRegistry {

	protected static $endpoints = null;

	public static function getEndpoints()
	{
		if (self::$endpoints !== null) return self::$endpoints;
		self::$endpoints = array();

		// Register all endpoint classes here.
		self::$endpoints[] = 'Begin';
		self::$endpoints[] = 'Import';
		self::$endpoints[] = 'SyncMenuLinkUUIDs';
		self::$endpoints[] = 'Delete';

		return self::$endpoints;
	}

	public static function getEndpointHandler($endpoint)
	{
		// Check to see if the endpoint handles the current endpoint.
		$endpoint = str_replace(Transaction::ENDPOINT_PREFIX, '', $endpoint);
		foreach (self::getEndpoints() as $handler) {
			if (forward_static_call(array('Drupal\publisher\Endpoints\\' . $handler, 'handlesEndpoint'), $endpoint) == true) {
				return 'Drupal\publisher\Endpoints\\' . $handler;
			}
		}

		return false;
	}

} 
