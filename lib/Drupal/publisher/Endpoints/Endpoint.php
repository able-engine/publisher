<?php

namespace Drupal\publisher\Endpoints;

use Drupal\publisher\Transaction;
use Drupal\publisher\Remote;

// Exceptions
class MalformedRequestException extends \Exception {}

abstract class Endpoint {

	/**
	 * The remote connection.
	 * @var Remote
	 */
	protected $remote;

	/**
	 * The current transaction.
	 * @var Transaction
	 */
	protected $transaction;

	/**
	 * Constructor.
	 *
	 * Creates a new instance of the endpoint. The remote and transaction are included so they
	 * can be accessed from within the class.
	 *
	 * @param Remote      $remote      The remote server the request came from.
	 * @param Transaction $transaction The transaction information for the request.
	 */
	public function __construct(Remote $remote, Transaction $transaction)
	{
		$this->remote = $remote;
		$this->transaction = $transaction;
	}

	/**
	 * Receive
	 *
	 * The function called whenever an API call passes authentication. The payload is
	 * passed as an array (the decoded JSON string). Query string variables can be
	 * accessed using $_GET.
	 *
	 * @param string $endpoint The endpoint currently being accessed.
	 * @param array  $payload  The POST data passed in the request.
	 *
	 * @return array The data to be sent back as formatted JSON.
	 */
	public abstract function receive($endpoint, $payload = array());

} 
