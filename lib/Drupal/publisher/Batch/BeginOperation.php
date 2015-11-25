<?php

namespace Drupal\publisher\Batch;

use AbleCore\Debug;
use Drupal\publisher\Dependencies\Resolver;
use Drupal\publisher\Dependencies\ResolverException;
use Drupal\publisher\Dependencies\RevisionResolver;
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;
use Drupal\publisher\Transaction;
use Drupal\publisher\TransactionSession;

class BeginOperation extends Operation {

	public function execute(Entity $entity, Remote $remote, array $options, &$context)
	{
		// Get the transaction from the session.
		$transaction_session = TransactionSession::getFromSession();
		if (!$transaction_session) return;

		// Prepare the list of entities.
		$entities = array();

		// Get the dependencies from the entity.
		try {

			if ($entity->supportsRevisions()) {
				$revision = $entity->revision();
				$resolver = new RevisionResolver($entity);
			} else {
				$resolver = new Resolver($entity);
			}

			$dependencies = $resolver->dependencies();

			if (!empty($revision)) {
				$entity->setRevision($revision);
			}

		} catch (ResolverException $ex) {

			$message = t('There was an error processing the dependencies for the <strong>:type</strong> <code>:id</code> to the remote. Skipping.', array(
				':type' => $entity->type(),
				':id' => $entity->id(),
			));
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);

			$this->updateContextMessages($entity, $context);
			return;

		}

		// Optionally mark the item as forced based on the entity type.
		$force = array_key_exists('force', $options) ? $options['force'] : false;
		drupal_alter('publisher_force_entity', $force, $entity);

		// Loop through each of the dependencies and mark them as forced if the options specify.
		if ($force) {
			foreach ($dependencies as $key => $dependency) {
				$dependencies[$key]['force'] = true;
				$dependencies[$key]['required'] = true;
			}
		}

		// Mark the root dependency as required if it has a status update.
		if (array_key_exists($entity->uuid(), $dependencies)) {
			$dependencies[$entity->uuid()]['source_required'] = false;
			$status = publisher_entity_tracking_get_status($entity->uuid(),
				$entity->type(), $remote->name);
			if (!$status->date_synced) {
				$dependencies[$entity->uuid()]['source_required'] = true;
			}
		}

		// Add the relationships to the transaction session.
		if ($resolver->relationships()) {
			$relationships = $transaction_session->getRelationships();
			foreach ($resolver->relationships() as $relationship) {
				$relationships[] = $relationship;
			}
			$transaction_session->setRelationships($relationships);
		}

		// Add the metadata to the transaction session.
		if ($metadata = $resolver->metadata()) {
			$transaction_session->setMetadata($metadata);
		}

		// Prepare the post data.
		$post_data = array(
			'dependencies' => $dependencies,
		);

		// Start the transaction.
		$transaction = new Transaction($remote);
		$response = $transaction->send('begin', $post_data);

		if (!$response['success']) {
			$message = t('There was an error sending the <strong>:type</strong> <code>:id</code> to the remote. See recent log messages for more details.', array(
				':type' => $entity->type(),
				':id' => $entity->id(),
			));
			drupal_set_message($message, 'error');
			watchdog('publisher', $message, array(), WATCHDOG_WARNING);
			Debug::wd($response);
		}

		$needs = array();
		if (array_key_exists('dependencies', $response)) {
			$needs = $response['dependencies'];
		}

		// Check to see if the receiving server didn't need anything.
		if (count($needs) <= 0) {

			$message = t('The remote <strong>:remote</strong> already has the latest version of the <strong>:type</strong> <code>:id</code>', array(
				':remote' => $remote->label,
				':type' => $entity->type(),
				':id' => $entity->id(),
			));
			drupal_set_message($message);
			watchdog('publisher', $message);

			$this->updateContextMessages($entity, $context);
			return;

		}

		// Make sure each of the needs is valid before adding them to the transaction
		// session.
		foreach ($needs as $need) {

			if (!array_key_exists('uuid', $need) ||
				!array_key_exists('entity_type', $need) ||
				!array_key_exists('need revision', $need) ||
				!array_key_exists('have revision', $need)) {
				continue;
			}

			// Check to see if the entity exists and prepare its payload.
			$entity_need = Entity::loadByUUID($need['uuid'], $need['entity_type']);
			if (!$entity_need) {
				$message = t('One of the entities the destination server needs could not be found. Please check the development log for more details.');
				drupal_set_message($message, 'error');
				watchdog('publisher', $message, array(), WATCHDOG_WARNING);
				Debug::wd($need);
				continue;
			}

			// Generate the payload for the entity.
			$entity_need_payload = publisher_compare_revision_uuid($entity_need, $need['have your revision'], $need['need revision']);
			if ($entity_need_payload === false) {
				$message = t('One or more revisions did not exist for the <strong>:type</strong> <code>:id</code>', array(
					':type' => $entity_need->type(),
					':id' => $entity_need->id(),
				));
				drupal_set_message($message, 'error');
				watchdog('publisher', $message, array(), WATCHDOG_WARNING);
				continue;
			}

			// Add the entity to the entities list in the transaction session.
			$entities[$entity_need->uuid()] = array(
				'entity_type' => $entity_need->type(),
				'entity_uuid' => $entity_need->uuid(),
				'entity_id' => $entity_need->id(),
				'entity_vuuid' => $entity_need->vuuid(),
				'have_revision' => $need['have revision'],
				'have_your_revision' => $need['have your revision'],
				'need_revision' => $need['need revision'],
				'required_from_remote' => $need['required_from_remote'],
				'original_dependency' => $dependencies[$entity_need->uuid()],
				'revisions_payload' => $entity_need_payload,
			);

		}

		// Add the entities to the transaction session.
		$transaction_session->addEntities($entity->uuid(), $entities);
		$transaction_session->storeToSession();

		// Finally, record the context messages.
		$this->updateContextMessages($entity, $context);
	}

}
