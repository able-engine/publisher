<?php

namespace Drupal\publisher\Batch;

use Drupal\publisher\Entity;
use Drupal\publisher\Remote;
use Drupal\publisher\TransactionSession;

abstract class Operation extends \AbleCore\Batch\Operation {

	/**
	 * execute()
	 *
	 * Perform the operation. See
	 * https://api.drupal.org/api/drupal/modules%21system%21form.api.php/function/callback_batch_operation/7
	 * for more information.
	 *
	 * @param Entity $entity  The entity to send.
	 * @param Remote $remote  The remote to send the entity to.
	 * @param array  $options Options to padd to publisher_send_entity.
	 * @param array  $context See https://api.drupal.org/api/drupal/modules%21system%21form.api.php/function/callback_batch_operation/7
	 */
//	public function execute(Entity $entity, Remote $remote, array $options, &$context)
//	{
//		// Get the transaction session from Drupal's session.
//		TransactionSession::getFromSession();
//
//		publisher_send_entity($entity, $remote, $options);
//		$this->updateContextMessages($entity, $context);
//
//		// Store the updated transaction to Drupal's session.
//		TransactionSession::getInstance()->storeToSession();
//	}

	protected function updateContextMessages($entity, &$context)
	{
		$messages = publisher_get_messages();
		foreach ($messages as $type => $type_messages) {
			foreach ($type_messages as $message) {
				if ($entity instanceof Entity) {
					$context['results'][] = array(
						'type' => $type,
						'message' => $message,
						'entity_type' => $entity->type(),
						'entity_id' => $entity->id(),
					);
				} else {
					$context['results'][] = array(
						'type' => $type,
						'message' => $message,
						'entity_type' => '',
						'entity_id' => '',
					);
				}
			}
		}
	}

	/**
	 * Sends the context messages back to Drupal's messaging system using
	 * drupal_set_message.
	 *
	 * Please note that this is meant to be called before using drupal_goto()
	 * to redirect the user to a result page.
	 *
	 * @param array $results The context from the batch operation.
	 */
	public static function exportContextResults(array $results)
	{
		foreach ($results as $result) {

			if ($result['entity_id'] && $result['entity_type']) {
				$message = ':label [<strong>:type</strong> <code>:id</code>] !message';
				$arguments = array(
					':label' => entity_label($result['entity_type'],
						entity_load_single($result['entity_type'], $result['entity_id'])),
					':type' => $result['entity_type'],
					':id' => $result['entity_id'],
					'!message' => $result['message'],
				);
			} else {
				$message = '!message';
				$arguments = array('!message' => $result['message']);
			}

			drupal_set_message(t($message, $arguments), $result['type']);

		}
	}

}
