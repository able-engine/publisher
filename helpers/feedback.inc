<?php

function publisher_action_feedback_entities_selected($op, array $selected_entity_uuids)
{
	if ($op == t('Cancel')) {
		\Drupal\publisher\TransactionSession::complete();
		return;
	}

	$transaction_session = \Drupal\publisher\TransactionSession::getFromSession();
	if (!$transaction_session) {
		drupal_set_message(t('The publish operation is no longer valid.'), 'error');
		drupal_goto('admin/content');
		return;
	}

	$transaction_session->setSelectedEntities($selected_entity_uuids);
	$transaction_session->storeToSession();

	// Start the operation to actually move the entities over.
	$queue = new \Drupal\publisher\Batch\SendOperationQueue();

	// Add the individual entities to send over.
	foreach ($transaction_session->getSelectedEntities() as $selected_entity) {
		$queue->addOperation(new \Drupal\publisher\Batch\SendOperation(),
			$selected_entity['entity_uuid'], $selected_entity['entity_type']);
	}

	// Start the batch.
	$queue->start();
	batch_process('publisher/finish');
}
