<?php

use Drupal\publisher\Remote;

/**
 * Prepares a transaction object.
 *
 * @param Remote $remote  The remote to send the payload to.
 * @param array  $payload The payload to be sent with the transaction.
 *
 * @return \Drupal\publisher\Transaction
 */
function publisher_prepare_transaction(Remote $remote, $payload = array())
{
	$transaction_id = false;
	$transaction_data = array();
	if (array_key_exists('transaction', $payload)) {
		if (array_key_exists('uuid', $payload['transaction'])) {
			$transaction_id = $payload['transaction']['uuid'];
		}
		if (array_key_exists('data', $payload['transaction'])) {
			$transaction_data = $payload['transaction']['data'];
		}
	}

	return new \Drupal\publisher\Transaction($remote, $transaction_id, $transaction_data);
}
