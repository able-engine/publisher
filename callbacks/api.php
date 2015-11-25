<?php

function action_handle($endpoint)
{
	$remote = publisher_authenticate_request();
	if (is_array($remote)) {
		return $remote;
	}

	$payload = publisher_get_payload();
	$transaction = publisher_prepare_transaction($remote, $payload);
	return $transaction->receive($endpoint, $payload);
}

function publisher_get_payload()
{
	return drupal_json_decode(file_get_contents('php://input'));
}

function publisher_authenticate_request()
{
	// Make sure the API key header exists.
	$headers = publisher_get_request_headers();

	if (!array_key_exists('x-publisher-apikey', $headers)) {
		return publisher_authentication_error('The header X-Publisher-APIKey does not exist.', 'NoAPIKeyHeader');
	}

	// Make sure this is a POST request.
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return publisher_authentication_error('The publisher API only supports POST requests.', 'POSTOnly');
	}

	// Check the validity of the API key.
	$valid = false;
	if ($headers['x-publisher-apikey'] == publisher_get_api_key()) {
		$valid = true;
	} elseif (defined('AC_API_MASTER_KEY') && $headers['x-publisher-apikey'] == AC_API_MASTER_KEY) {
		$valid = true;
	}
	if (!$valid) {
		return publisher_authentication_error('The provided API key is incorrect.', 'IncorrectAPIKey');
	}

	// Make sure we have a remote key.
	if (!array_key_exists('x-publisher-remote', $headers)) {
		return publisher_authentication_error('The header X-Publisher-Remote does not exist.', 'NoRemoteHeader');
	}

	// Make sure the remote header is valid.
	$remote = publisher_get_remote_by_key($headers['x-publisher-remote']);
	if ($remote === false) {
		return publisher_authentication_error('The specified remote does not exist.', 'RemoteDoesntExist');
	}

	// Make sure we have an origin header.
	if (!array_key_exists('origin', $headers)) {
		return publisher_authentication_error('The origin header does not exist.', 'NoOriginHeader');
	}

	// Check to see if the URL in the remote matches the origin.
	if (publisher_normalize_remote_url($remote->url) != publisher_normalize_remote_url($headers['origin'])) {
		return publisher_authentication_error('The remote URL (' . publisher_normalize_remote_url($remote->url) . ') does not match the origin URL (' . publisher_normalize_remote_url($headers['origin']) .').', 'OriginRemoteMismatch');
	}

	// Make sure the remote is enabled.
	if (!$remote->enabled) {
		return publisher_authentication_error('The remote is not enabled.', 'RemoteNotEnabled');
	}

	// Make sure the remote is set to receive content.
	if (!$remote->receive) {
		return publisher_authentication_error('This site cannot receive content from this remote.', 'CantReceiveContent');
	}

	return $remote;
}

function publisher_get_request_headers()
{
	// From: http://stackoverflow.com/questions/13224615/get-the-http-headers-from-current-request-in-php
	$headers = '';
	foreach ($_SERVER as $name => $value) {
		if (substr($name, 0, 5) == 'HTTP_') {
			$headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
		}
	}

	return $headers;
}

function publisher_normalize_remote_url($url)
{
	$url = trim($url, '/');
	$url = str_replace('www.', '', $url);
	$url = str_replace('https://', 'http://', $url); // Normalize HTTP vs HTTPS.
	return $url;
}

function publisher_authentication_error($message, $developer_message)
{
	return array(
		'success' => false,
		'errors' => array(
			array(
				'message' => $message,
				'developer' => $developer_message,
			),
		),
	);
}
