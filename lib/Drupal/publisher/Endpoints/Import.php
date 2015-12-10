<?php

namespace Drupal\publisher\Endpoints;
use Drupal\publisher\BounceManager;
use Drupal\publisher\Dependencies\EntityHandlers\EntityHandlerBase;
use Drupal\publisher\Dependencies\EntityHandlers\EntityHandlerRegistry;
use Drupal\publisher\Entity;
use Drupal\publisher\Dependencies\Unresolver;
use Drupal\publisher\Preparers\PreparerRegistry;

class Import extends Endpoint {

	/**
	 * The metadata received from the sending server.
	 * @var array
	 */
	protected $metadata = array();

	public function receive($endpoint, $payload = array())
	{
		if (!array_key_exists('entities', $payload) ||
			!is_array($payload['entities']) ||
			count($payload['entities']) <= 0) {
			throw new MalformedRequestException('No entities were passed with the request.');
		}

		if (!array_key_exists('metadata', $payload)) {
			throw new MalformedRequestException('No metadata was passed with the request.');
		}
		$this->metadata = $payload['metadata'];

		// Mark the remote as active so we don't create an infinite loop.
		BounceManager::getInstance()->addRemote($this->remote);

		// Note we'll never unset this flag because it's safe to say that all
		// entity updates done in this request are because of a publisher operation,
		// so we shouldn't do any entity tracking on any saves for this request.
		// Drupal's static cache (which powers publisher_set_flag) will be reset
		// after the request has been completed.
		publisher_set_flag('importing_entities');

		foreach ($payload['entities'] as $entity) {

			// Allow users to alter entity before it's imported to the system.
			drupal_alter('publisher_import_entity', $entity, $this->remote);

			// Import the entity.
			if (!$this->importEntity($entity)) {
				drupal_set_message('There was an error importing one of the entities. Because the entity that failed ' .
					'might have been a dependency of another entity, the operation has been cancelled.', 'error');
				break;
			}

		}

		// Mark the remote as finished so we can send to it later.
		BounceManager::getInstance()->completeRemote($this->remote);

		return array();
	}

	protected function importEntity($entity_payload)
	{
		// Verify the entity payload.
		if (($message = $this->verifyEntityPayload($entity_payload)) !== true) {
			throw new InvalidEntityPayloadException($message);
		}

		// Check to see if any relationships exist.
		if (array_key_exists('relationships', $entity_payload) &&
			is_array($entity_payload['relationships'])) {
			foreach ($entity_payload['relationships'] as $relationship) {
				if (!Entity::exists($relationship['destination_uuid'], $relationship['destination_type'])) {
					publisher_relationships_save($relationship);
				}
			}
		}

		// Load the entity.
		$entity = Entity::loadByUUID($entity_payload['uuid'], $entity_payload['entity_type']);
		if (!$entity) {
			$entity = $this->importRevisionAsNew($entity_payload);
			if (!$entity) {
				drupal_set_message("There was an error creating the <strong>{$entity_payload['entity_type']}</strong> <code>{$entity_payload['uuid']}</code>. Please check the recent log messages for more details.",
					'error');
				return false;
			}
		}
		$original_vuuid = $entity->vuuid();

		// Import the remaining revisions for the entity.
		foreach ($entity_payload['revisions'] as $revision_key => $revision) {

			// Verify the revision.
			if (($message = $this->verifyRevisionPayload($revision)) !== true) {
				throw new InvalidRevisionPayloadException($message);
			}

			// Apply the revision.
			$result = $this->applyRevision($entity, $revision);

			// Do some logic to check and see if we should report on this...
			$should_report = true;
			$revision_key_segments = explode('|', $revision_key);
			if (count($revision_key_segments) == 3 && $revision_key_segments[2] == '1') $should_report = false;

			if ($result && $should_report) {
				// Add a success message to the transaction data.
				drupal_set_message("Updated <strong>{$entity_payload['entity_type']}</strong> <code>{$entity_payload['uuid']}</code> to revision <code>{$revision_key}</code>");
			}

		}

		// Mark the entity as synced.
		$entity->vuuid($original_vuuid);
		publisher_entity_tracking_mark_as_synced($entity, $this->remote);

		// Run the entity handlers after the entire entity has been saved.
		foreach (EntityHandlerRegistry::getEntityHandlers($entity) as $handler) {
			if (!($handler instanceof EntityHandlerBase)) continue;
			$handler->entity = $entity;
			$handler->original_entity = $entity;
			$handler->unhandleEntity($this->getEntityFromMetadata($entity->uuid()));
		}

		// Call hook_publisher_entity_received()
		module_invoke_all('publisher_entity_received', $entity, $this->remote);

		return true;
	}

	protected function importRevisionAsNew(&$entity_payload)
	{
		// Verify that the first revision can be used as a new revision.
		$first_revision = array();
		$first_revision_key = '';
		foreach ($entity_payload['revisions'] as $key => $revision) {
			$first_revision = $revision;
			$first_revision_key = $key;
			break;
		}

		// Make sure the first revision exists.
		if (count($first_revision) == 0 || $first_revision_key == '') {
			throw new InvalidEntityPayloadException('The entity does not exist, and there is no first revision to use.');
		}

		// Make sure the first revision has no deletions.
		if (($message = $this->verifyRevisionPayload($first_revision)) !== true) {
			throw new InvalidRevisionPayloadException($message);
		}
		if (count($first_revision['deletions']) > 0) {
			throw new InvalidRevisionPayloadException('There are not supposed to be deletions for the first revision to use.');
		}

		// Create the entity and apply the first revision.
		$entity = new Entity(new \stdClass(), $entity_payload['entity_type']);
		$entity->isNew(true);
		$result = $this->applyRevision($entity, $first_revision);

		// Finally, remove the first revision so we don't import it again.
		unset($entity_payload['revisions'][$first_revision_key]);

		// Add a success message to the transaction data.
		if ($result) {
			drupal_set_message("Created <strong>{$entity_payload['entity_type']}</strong> <code>{$entity_payload['uuid']}</code> successfully!");
		} else {
			return false;
		}

		// Return the entity.
		return $entity;
	}

	protected function applyRevision(Entity &$entity, $revision_payload)
	{
		global $user;

		// Preserve the original entity.
		$original_entity = clone $entity;

		// Apply changes to the entity.
		$entity->definition = (object)array_replace_recursive((array)$entity->definition, (array)$revision_payload['additions']);

		// Apply deletions to the entity.
		$deletions_definition = (array)$entity->definition;
		$keys_to_delete = array();
		$this->applyRevisionDeletions($deletions_definition, $revision_payload['deletions'],
			$keys_to_delete);
		$entity->definition = (object)$deletions_definition;

		// Run the beforeDependencies preparer.
		$preparer_registry = new PreparerRegistry();
		$preparer_registry->beforeDependencies($entity);

		// Revert references.
		$unresolver = new Unresolver($entity);
		$entity->definition = $unresolver->unresolvedDefinition();

		// Check to see if there were any errors reverting references.
		if ($errors = $unresolver->errors()) {
			$this->transaction->addErrors($errors);
			return false; // Stop processing this entity if there were errors.
		}

		// Unset the node VID and the user ID.
		$entity->definition->revision = true;
		if (isset($entity->definition->revision_uid)) {
			$user->uid = $entity->definition->revision_uid;
		} elseif (isset($entity->definition->uid)) {
			$user->uid = $entity->definition->uid;
		}

		// Get the old stuff for the node.
		$old_revision_information = array(
			'uid' => $user->uid,
			'vuuid' => $entity->vuuid(),
		);

		// Set unchanging properties to the node that was sent.
		$unchanging_properties = publisher_get_unchanging_properties($entity->type());
		foreach ($unchanging_properties as $property) {
			if (!isset($revision_payload['additions'][$property])) continue;
			$entity->definition->$property = $revision_payload['additions'][$property];
		}

		// Get the revision table for the entity.
		$entity_info = entity_get_info($entity->type());
		if (array_key_exists('revision table', $entity_info)) {
			$revision_table = $entity_info['revision table'];
			$revision_key = $entity_info['entity keys']['revision'];
			$revision_uuid_key = $entity_info['entity keys']['revision uuid'];
		}

		// Update the VID to an existing revision.
		if ($entity->supportsRevisions() && isset($revision_key)) {
			$this->setEntityRevisionID($entity->vuuid(), $entity);
		}

		// Run the preparers beforeSave.
		$preparer_registry->beforeSave($entity);

		// Run the entity handlers with the metadata.
		foreach (EntityHandlerRegistry::getEntityHandlers($entity) as $handler) {
			if (!($handler instanceof EntityHandlerBase)) continue;
			$handler->entity = &$entity;
			$handler->original_entity = $original_entity;
			$handler->unhandleRevision($this->getEntityFromMetadata($entity->uuid()));
		}

		// Save the entity.
		try {
			$entity->save();
		} catch (\Exception $ex) { // ...and try to catch any exceptions thrown by this (sometimes, the exceptions still aren't caught).
			$this->transaction->addError($ex);
			return false;
		}

		if (isset($revision_table) && isset($revision_key)) {

			// Reset the UID and VUUID for the revision.
			$cloned_definition = clone $entity->definition;
			$cloned_definition->uid = $old_revision_information['uid'];
			$cloned_definition->{$revision_uuid_key} = $old_revision_information['vuuid'];
			drupal_write_record($revision_table, $cloned_definition, $revision_key);

			// Delete duplicate revisions that no longer apply.
			$this->checkForDuplicateRevisions($cloned_definition, $entity);

			// Reset the revision cache in the entity class.
			Entity::getLatestRevisionID($entity->id(), $entity->type(), true);

		}

		// Run the preparers afterSave events.
		$preparer_registry->afterSave($entity);

		return true;
	}

	protected function setEntityRevisionID($revision_uuid, Entity $entity)
	{
		if ($entity->revision() !== false) return false;
		if (!$entity->supportsRevisions()) return false;
		$info = entity_get_info($entity->type());

		// Query for revisions with the same UUID and entity ID.
		$query = db_select($info['revision table'], 'rev');
		$query->addField('rev', $info['entity keys']['revision'], 'revision');
		$query->condition($info['entity keys']['revision uuid'], $revision_uuid);
		$query->orderBy($info['entity keys']['revision']);
		$results = $query->execute();

		if ($results->rowCount() >= 1) {
			$entity->definition->vid = $results->fetch()->revision;
			return true;
		} else {
			return false;
		}
	}

	protected function checkForDuplicateRevisions($definition, Entity $entity)
	{
		$info = entity_get_info($entity->type());
		if (!$entity->supportsRevisions()) return;

		// Query for revisions with the same UUID.
		$query = db_select($info['revision table'], 'rev');
		$query->addField('rev', $info['entity keys']['revision'], 'revision');
		$query->condition($info['entity keys']['revision uuid'], $definition->{$info['entity keys']['revision uuid']});
		$query->orderBy($info['entity keys']['revision']);
		$results = $query->execute();

		if ($results->rowCount() > 1) {
			// Grab the first row (oldest) and delete it.
			$this->deleteRevision($entity, $results->fetch()->revision);
		}
	}

	protected function deleteRevision(Entity $entity, $revision_id)
	{
		// First, delete the revision using the entity API.
		entity_revision_delete($entity->type(), $revision_id);

		// Now, if workbench moderation is installed, remove the node history for the specific revision.
		if (module_exists('workbench_moderation') && $entity->type() == 'node') {
			db_delete('workbench_moderation_node_history')
				->condition('nid', $entity->id())
				->condition('vid', $revision_id)
				->execute();
		}
	}

	protected function applyRevisionDeletions(array &$definition, array $deletions = array())
	{
		foreach ($deletions as $key => $value) {
			if ($deletions[$key] === null) {
				unset($definition[$key]);
				continue;
			}
			if (array_key_exists($key, $definition) && is_array($deletions[$key]) && is_array($definition[$key])) {
				$this->applyRevisionDeletions($definition[$key], $deletions[$key]);
			}
		}
	}

	protected function verifyRevisionPayload(&$revision_payload)
	{
		if (!array_key_exists('additions', $revision_payload) ||
			!is_array($revision_payload['additions'])) return 'No additions.';
		if (!array_key_exists('deletions', $revision_payload) ||
			!is_array($revision_payload['deletions'])) return 'No deletions.';
		return true;
	}

	protected function verifyEntityPayload($entity_payload)
	{
		if (!array_key_exists('entity_type', $entity_payload)) return 'Entity type not provided.';
		if (!array_key_exists('uuid', $entity_payload)) return 'Entity UUID not provided.';
		if (!array_key_exists('revisions', $entity_payload) ||
			!is_array($entity_payload['revisions'])) return 'Entity revision history not provided.';
		return true;
	}

	protected function getEntityFromMetadata($entity_uuid)
	{
		if (!is_array($this->metadata)) return array();
		if (array_key_exists($entity_uuid, $this->metadata)) {
			return $this->metadata[$entity_uuid];
		} else return array();
	}

	public static function handlesEndpoint($endpoint)
	{
		if ($endpoint == 'import') return true;
		return false;
	}

}

class InvalidEntityPayloadException extends \Exception {}
class InvalidRevisionPayloadException extends \Exception {}
