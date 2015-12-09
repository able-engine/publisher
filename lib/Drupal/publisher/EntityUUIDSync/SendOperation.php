<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Batch\Operation;
use Drupal\publisher\Remote;

class SendOperation extends Operation
{
	public function execute(Remote $remote, &$context)
	{
		$transaction = new \Drupal\publisher\Transaction($remote);
		$response = $transaction->send('sync-entity-type', array('metadata' => $context['results']['metadata']));

		if (!$response['success']) {
			$message = 'There was an error updating the entity type UUIDs. See the error log for more details.';
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);
			\AbleCore\Debug::wd($response);
		} else {
			drupal_set_message(t('Entities updated successfully. There were @errors errors, @success successfully updated and @unchanged either unmodified or non-existant.', array(
				'@errors' => $response['counts']['errors'],
				'@success' => $response['counts']['success'],
				'@unchanged' => $response['counts']['unchanged'],
			)));
		}

		$this->updateContextMessages(null, $context);
	}
}
