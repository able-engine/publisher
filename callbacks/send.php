<?php

use Drupal\publisher\TransactionSession;

function action_begin()
{
	$transaction = TransactionSession::getFromSession();
	if (!$transaction) drupal_access_denied();

	// If there are no root entities, throw an error.
	$root_entities = $transaction->getRootEntities();
	if (count($root_entities) <= 0) drupal_access_denied();

	// Launch a batch session to get all the dependencies of the root entities.
	$queue = new \Drupal\publisher\Batch\BeginOperationQueue();
	foreach ($root_entities as $root_entity) {
		$queue->addOperation(new \Drupal\publisher\Batch\BeginOperation(),
			$root_entity['entity'], $transaction->getRemote(), $root_entity['options']);
	}
	$queue->start();

	// We'll need to call batch_process because we're not in the context of a
	// form's submit handler.
	batch_process('publisher/feedback');
}

function action_feedback()
{
	$transaction = TransactionSession::getFromSession();
	if (!$transaction) return t('There was an error processing the entities.');

	// Before marking the transaction session as complete, set the title.
	action_feedback_title();

	// Set the forced flag based on if any of the root entites were forced.
	$forced = false;
	$root_entity_ids = array();
	foreach ($transaction->getRootEntities() as $uuid => $root_entity) {
		$root_entity_ids[] = $uuid;
		if (array_key_exists('force', $root_entity['options']) && $root_entity['options']['force']) {
			$forced = true;
			break;
		}
	}

	// Add each of the entities to the list for the form.
	$form_entities = array();
	foreach ($transaction->getAllEntities() as $dependency) {

		if (!array_key_exists('original_dependency', $dependency)) continue;
		$original_dependency = $dependency['original_dependency'];

		$form_entity = array();
		$entity = \Drupal\publisher\Entity::loadByUUID($dependency['entity_uuid'], $dependency['entity_type']);

		$entity_uri = entity_uri($entity->type(), $entity->definition);
		$entity_path = ltrim($entity_uri['path'], '/');
		$form_entity['label'] = l(entity_label($entity->type(), $entity->definition), $entity_path) .
			' [<strong>' . $entity->type() . '</strong> <code>' . $entity->id() . '</code>]';

		$form_entity['required'] = $original_dependency['required'];
		$form_entity['required_if'] = $original_dependency['required_if'];
		$form_entity['entity_type'] = $dependency['entity_type'];
		$form_entity['root_entity'] = in_array($dependency['entity_uuid'], $root_entity_ids);

		$form_entities[$dependency['entity_uuid']] = $form_entity;

	}

	// If there are no entities to move, complete the session.
	if (count($form_entities) <= 0) {
		$transaction->complete();
	}

	// Output each of the entities to the page with their status.
	$form = drupal_get_form('publisher_select_entities_form', $form_entities,
		'publisher_action_feedback_entities_selected', $forced, $transaction->getRemote());

	return $form;
}

function action_feedback_title()
{
	$transaction = TransactionSession::getFromSession();
	if ($transaction) {
		$entities = $transaction->getRootEntities();
		$entity = reset($entities);
		/** @var \Drupal\publisher\Entity $entity_object */
		$entity_object = $entity['entity'];
		$entity_section = entity_label($entity_object->type(), $entity_object->definition);
		if (count($entities) > 1) {
			$entity_section .= ' and ' . (count($entities) - 1) . ' others';
		}
		if ($entity_section !== false) {
			$title = t('Select dependencies of %entity to send...', array('%entity' => $entity_section));
			drupal_set_title($title, PASS_THROUGH);
			return $title;
		}
	}

	return false;
}

function action_finish()
{
	TransactionSession::complete();
}
