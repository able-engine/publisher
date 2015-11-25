<?php

use Drupal\publisher\Batch\Operation;
use Drupal\publisher\Batch\OperationQueue;
use Drupal\publisher\Dependencies\ResolverException;
use Drupal\publisher\Dependencies\RevisionResolver;
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;
use Drupal\publisher\Transaction;
use Drupal\publisher\TransactionSession;

function publisher_send_entity(Entity $entity, Remote $remote, array $options = array())
{
	return publisher_send_entities(array($entity), $remote, $options);
}

function publisher_send_entities(array $entities, Remote $remote, array $options = array())
{
	// Get the transaction session.
	$session = TransactionSession::getFromSession();
	if (!$session) {
		$session = TransactionSession::getInstance();
		$session->setRemote($remote);
	}

	// Add each of the entities.
	foreach ($entities as $entity) {
		/** @var Entity $entity */

		// Make sure the remote is the same as the transaction session's remote.
		if ($remote->rid != $session->getRemote()->rid) {
			$message = t('Cannot send entity <strong>:type</strong> <code>:id</code>. You may only send entities to one remote per session. Please send the current list of entities before continuing.',
				array(
					':type' => $entity->type(),
					':id' => $entity->id(),
				));
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);

			return false;
		}

		// Add the entity to the transaction session.
		if (!$session->addRootEntity($entity, $options)) {
			$message = t('Cannot send entity :label [<strong>:type</strong> <code>:id</code>]. Publisher currently only supports sending a maximum of 30 entities at a time.',
				array(
					':label' => entity_label($entity->type(), $entity->definition),
					':type' => $entity->type(),
					':id' => $entity->id(),
				));
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);

			return false;
		}

	}

	// Mark the session as ready to override.
	$session->readyToOverride();

	// Store the transaction session.
	$session->storeToSession();

	return true;
}

function publisher_send_nodes($nodes, Remote $remote)
{
	$entities = array();
	foreach ($nodes as $node) {
		$entities[] = Entity::load($node->nid, 'node');
	}
	return publisher_send_entities($entities, $remote);
}
