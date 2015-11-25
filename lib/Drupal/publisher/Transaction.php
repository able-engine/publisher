<?php

namespace Drupal\publisher;

use Drupal\publisher\Endpoints\EndpointRegistry;

class Transaction {

	const ENDPOINT_PREFIX = 'api/publisher/';
	const API_VERSION = '1.x-dev';

	/**
	 * The active transaction. This variable is only used on receive requests.
	 * @var Transaction
	 */
	protected static $active_transaction;

	/**
	 * The remote the transaction will connect to.
	 * @var Remote
	 */
	protected $remote;

	/**
	 * The current payload.
	 * @var array
	 */
	protected $payload = array();

	/**
	 * The UUID for the current transaction.
	 * @var string
	 */
	protected $id = '';

	/**
	 * An internal list of errors that occurred during the transaction.
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Any additional data to be sent back and forth with the transaction.
	 * @var array
	 */
	protected $session_data = array();

	public function __construct(Remote $remote, $id = false, $data = array())
	{
		$this->remote = $remote;
		$this->data = $data;

		if ($id === false) {
			// Generate a UUID for the transaction.
			\module_load_include('inc', 'uuid');
			$this->id = \uuid_generate();
		} else {
			$this->id = $id;
		}

		// Set the timeout to a higher number.
		drupal_set_time_limit(0);
	}

	public function send($endpoint, $payload = array())
	{
		$this->debug('Sending data to ' . $endpoint);

		// Attach the transaction to the payload.
		$payload['transaction'] = $this->generateTransactionObject();

		// Generate the full endpoint URL.
		$request_url = trim($this->remote->url, '/') . '/' . self::ENDPOINT_PREFIX . $endpoint;

		// Generate the headers.
		$headers = $this->generateHeaders();

		// Generate the payload.
		$this->debug($payload);
		$payload = \drupal_json_encode($payload);

		// Set the payload.
		$this->payload = $payload;

		// Start the curl request.
		$handle = curl_init($request_url);
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);

		// Execute the request and debug the output.
		$response = curl_exec($handle);
		$json = \drupal_json_decode($response);
		if ($json === null) {
			$this->debug($response);
			throw new TransactionException('Response from server was malformed. Check the recent log messages for more details. Response: ' . $response);
		} else {
			$this->debug($json);

			// Update the data for the current transaction.
			if (isset($json['transaction']['data']) && is_array($json['transaction']['data'])) {
				$this->data = $json['transaction']['data'];
			}

			// If there were messages sent back from the server, forward them to drupal_set_message.
			if (array_key_exists('messages', $json) && is_array($json['messages']) && count($json['messages']) > 0) {
				foreach ($json['messages'] as $message) {
					drupal_set_message('[' . $this->remote->label . '] ' . $message['message'], $message['type']);
				}
			}

			return $json;
		}
	}

	public function receive($endpoint, $payload = array())
	{
		$this->debug('Receiving data through ' . $endpoint);

		// Set the payload.
		$this->payload = $payload;

		// Set the current transaction as active.
		$this->setActive();

		// Find the appropriate endpoint class.
		$handler = EndpointRegistry::getEndpointHandler($endpoint);
		if ($handler == false) return;
		if (!class_exists($handler)) {
			throw new TransactionException('The handler ' . $handler . ' does not exist.');
		}

		// Get the response.
		$endpoint_class = new $handler($this->remote, $this);
		try {
			$response = $endpoint_class->receive($endpoint, $payload);
		} catch (\Exception $ex) {
			$this->errors[] = $ex;
			$response = array();
		}

		// Finalize the response.
		$response['transaction'] = $this->generateTransactionObject();
		if (!array_key_exists('success', $response)) {
			$response['success'] = true;
		}

		// Process the errors in the response.
		if (count($this->errors) > 0) {
			$response['success'] = false;
			$response['errors'] = array();
			foreach ($this->errors as $error) {
				$response['errors'][] = array(
					'message' => $error->getMessage(),
					'developer' => get_class($error),
					'trace' => $error->getTraceAsString(),
				);
			}
		}

		// Send any messages along that occurred.
		if ($messages = drupal_get_messages()) {
			foreach ($messages as $type => $type_messages) {
				if (!is_array($type_messages)) {
					$type_messages = array($type_messages);
				}
				foreach ($type_messages as $message) {
					$response['messages'][] = array(
						'type' => $type,
						'message' => $message,
					);
				}
			}
		}

		return $response;
	}

	public function debug($data)
	{
		if (array_key_exists('debug', $_GET) || strpos($_SERVER['SERVER_NAME'],
				'.dev') == strlen($_SERVER['SERVER_NAME']) - 4 || variable_get('publisher_debug_mode', false)
		) {
			if (is_array($data) || is_object($data)) {
				watchdog('publisher', '<pre>' . var_export($data, true) . '</pre>', array(), WATCHDOG_DEBUG);
			} else {
				watchdog('publisher', $data, array(), WATCHDOG_DEBUG);
			}
		}
	}

	protected function setActive(Transaction $transaction = null)
	{
		if ($transaction === null) {
			$transaction = $this;
		}
		self::$active_transaction = $transaction;
	}

	public function getPayload()
	{
		return $this->payload;
	}

	public static function getActive()
	{
		return self::$active_transaction;
	}

	protected function generateTransactionObject()
	{
		$transaction = array();
		$transaction['uuid'] = $this->id;
		$transaction['data'] = $this->data;
		return $transaction;
	}

	protected function generateHeaders()
	{
		global $base_url;
		$headers = array();
		$headers['x-publisher-apikey'] = $this->remote->api_key;
		$headers['x-publisher-remote'] = \publisher_get_api_key();
		$headers['origin'] = $base_url;
		$headers['Content-Type'] = 'application/json';

		$curl_headers = array();
		foreach ($headers as $key => $value) {
			$curl_headers[] = $key . ': ' . $value;
		}
		return $curl_headers;
	}

	public function addError(\Exception $error)
	{
		$this->errors[] = $error;
	}

	public function addErrors(array $errors)
	{
		foreach ($errors as $error) {
			$this->addError($error);
		}
	}

}

class TransactionException extends \Exception {}
