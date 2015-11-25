<?php

function publisher_batch_operation_finished($success, array $results, array $operations)
{
	// Export the messages from the batch operation.
	\Drupal\publisher\Batch\Operation::exportContextResults($results);

	if (!$success) {
		drupal_set_message(t('There was an error sending the entities using publisher. Please see the error log or contact an administrator for more information.'), 'error');
		\Drupal\publisher\TransactionSession::complete();
	}
}
