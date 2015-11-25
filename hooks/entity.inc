<?php
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

/**
 * Implements hook_entity_insert()
 */
function publisher_entity_insert($entity, $type)
{
	publisher_entity_update($entity, $type);
}

/**
 * Implements hook_entity_update()
 */
function publisher_entity_update($entity, $type)
{
	$loaded_entity = new Entity($entity, $type);

	// Only update the tracking status if we're not in the context of a
	// publisher operation.
	if (!publisher_get_flag('importing_entities')) {
		foreach (publisher_get_remotes() as $remote) {
			/** @var Remote $remote */
			publisher_entity_tracking_create_status($loaded_entity, $remote);
		}
	}

	// Update the entity's relationships through Publisher.
	$source_vuuid = false;
	if ($loaded_entity->supportsRevisions()) {
		$source_vuuid = $loaded_entity->vuuid();
	}
	publisher_relationships_update_entity($loaded_entity->type(), $loaded_entity->uuid(),
		$source_vuuid);
}

/**
 * Implements hook_entity_delete()
 */
function publisher_entity_delete($entity, $type)
{
	$loaded_entity = new Entity($entity, $type);

	// Remove tracking statuses for the entity (to keep the table lean).
	publisher_entity_tracking_delete_status($loaded_entity);

	// Mark the entity as deleted.
	publisher_deleted_entities_save($loaded_entity->uuid(), $loaded_entity->type(),
		entity_label($loaded_entity->type(), $loaded_entity->definition));
}
