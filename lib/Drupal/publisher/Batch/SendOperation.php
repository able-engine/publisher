<?php

namespace Drupal\publisher\Batch;

use AbleCore\Debug;
use Drupal\publisher\Entity;
use Drupal\publisher\Preparers\PreparerRegistry;
use Drupal\publisher\Transaction;
use Drupal\publisher\TransactionSession;

class SendOperation extends Operation {

	public function execute($selected_entity_uuid, $selected_entity_type, &$context)
	{
		// Load the entity.
		$entity = Entity::loadByUUID($selected_entity_uuid, $selected_entity_type);
		if (!$entity) {
			drupal_set_message(t('An invalid entity was found.'), 'error');
			$this->updateContextMessages(null, $context);
			return;
		}

		// Get the transaction session, and fail if we don't have it.
		$transaction_session = TransactionSession::getFromSession();
		if (!$transaction_session) {
			$current_set = &_batch_current_set();
			$current_set['success'] = false;
			drupal_set_message(t('We lost the transaction session.'), 'error');
			$this->updateContextMessages($entity, $context);
			return;
		}

		// Prepare to send the entity over to the receiving server.
		$entities = array();
		$transaction_entities = $transaction_session->getAllEntities();

		// Make sure the transaction entity exists.
		if (array_key_exists($entity->uuid(), $transaction_entities)) {
			$transaction_entity = $transaction_entities[$entity->uuid()];
		} else {
			drupal_set_message(t('An invalid entity was found.'), 'error');
			$this->updateContextMessages($entity, $context);
			return;
		}

		// Get the relationships for the entity.
		$relationships = array();
		foreach ($transaction_session->getRelationships() as $relationship) {
			if ($relationship['source_uuid'] == $entity->uuid() && (!$entity->supportsRevisions() || $relationship['source_vuuid'] == $entity->vuuid())) {
				$relationships[] = $relationship;
			}
		}

		// Send the entity revisions.
		$entities[] = array(
			'entity_type' => $entity->type(),
			'uuid' => $entity->uuid(),
			'vuuid' => $entity->vuuid(),
			'revisions' => $transaction_entity['revisions_payload'],
			'relationships' => $relationships,
		);

		$payload = array(
			'entities' => $entities,
			'metadata' => $transaction_session->getMetadata($entity->uuid()),
		);

		// Send the request.
		$transaction = new Transaction($transaction_session->getRemote());
		$response = $transaction->send('import', $payload);

		if (!$response['success']) {
			$message = "There was an error sending the <strong>{$entity->type()}</strong> <code>{$entity->id()}</code> to the remote. Look at the recent log messages for more details.";
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);
			Debug::wd($response);
		} elseif (empty($response['messages'])) {
			drupal_set_message('The operation was successful, but nothing was done on the remote. This probably means there weren\'t any updates to move.');
		}

		// Mark the entity as synced if the response was successful.
		if ($response['success']) {
			publisher_entity_tracking_mark_as_synced($entity, $transaction_session->getRemote());
		}

		$this->updateContextMessages($entity, $context);
	}

}
