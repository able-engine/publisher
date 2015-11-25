<?php

namespace Drupal\publisher\Batch;

use AbleCore\Debug;
use Drupal\publisher\Remote;
use Drupal\publisher\Transaction;

class SendRelationshipsOperation extends Operation {

	public function execute(array $relationships, Remote $remote, &$context)
	{
		$transaction = new Transaction($remote);
		$response = $transaction->send('relationships', array(
			'relationships' => $relationships,
		));

		if (!$response['success']) {
			$message = "There was an error sending the relationships over to the target server. Look at the error log for more details.";
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);
			Debug::wd($response);
		}

		$this->updateContextMessages(null, $context);
	}

}
