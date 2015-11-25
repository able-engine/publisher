<?php

namespace Drupal\publisher\Batch;

use AbleCore\Debug;
use Drupal\publisher\Remote;
use Drupal\publisher\Transaction;

class DeleteOperation extends Operation {

	public function execute(Remote $remote, $entity_uuid, $entity_type, $entity_title, &$context)
	{
		$post_data = array(
			'entities' => array(
				array(
					'entity_uuid' => $entity_uuid,
					'entity_type' => $entity_type,
					'entity_title' => $entity_title,
				)
			)
		);

		$transaction = new Transaction($remote);
		$response = $transaction->send('delete', $post_data);

		if (!$response['success']) {
			$message = t('There was an error deleting the <strong>:type</strong> @title from the remote. See recent log messages for more details.', array(
				':type' => $entity_type,
				'@title' => $entity_title,
			));
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);
			Debug::wd($response);
		}

		if (!empty($response['deleted']) && is_array($response['deleted'])) {
			foreach ($response['deleted'] as $deleted_entity) {
				publisher_deleted_entities_delete($deleted_entity['entity_uuid'],
					$deleted_entity['entity_type'], $remote);
			}
		}

		$this->updateContextMessages(null, $context);
	}

}
