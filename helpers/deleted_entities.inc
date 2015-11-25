<?php
use Drupal\publisher\Remote;

/**
 * Gets a base query for getting all the deleted entities.
 *
 * @return SelectQueryInterface
 */
function publisher_deleted_entities_query()
{
	return db_select('publisher_entity_deleted', 'd')
		->fields('d', array())
		->orderBy('deleted', 'DESC');
}

/**
 * Gets all deleted entity records from the database.
 *
 * @return mixed The results of the query's fetchAll() function.
 *               Either an array of result objects, containing the
 *               entity_uuid, entity_type and remote, or something equating
 *               to false.
 */
function publisher_deleted_entities()
{
	return publisher_deleted_entities_query()->execute()->fetchAll();
}

/**
 * Saves the specified entity_uuid and entity_type to the deletion
 * tracking table.
 *
 * @param string $entity_uuid  The UUID of the entity that was deleted.
 * @param string $entity_type  The type of entity that was deleted.
 * @param string $entity_title The title of the entity that was deleted.
 *
 * @throws Exception
 */
function publisher_deleted_entities_save($entity_uuid, $entity_type, $entity_title)
{
	if (publisher_get_flag('publisher_deleting')) return;

	// Make sure it's not a file.
	if ($entity_type == 'file') {
		return;
	}

	foreach (publisher_get_remotes() as $remote) {
		/** @var Remote $remote */
		db_insert('publisher_entity_deleted')->fields(array(
			'entity_uuid' => $entity_uuid,
			'entity_type' => $entity_type,
			'entity_title' => $entity_title ? $entity_title : '',
			'deleted' => REQUEST_TIME,
			'remote' => $remote->name
		))->execute();
	}
}

/**
 * Deletes an entity from the tracking table.
 *
 * @param string $entity_uuid The UUID of the entity to delete.
 * @param string $entity_type The type of entity to delete.
 * @param Remote $remote      The remote the entity was deleted from.
 */
function publisher_deleted_entities_delete($entity_uuid, $entity_type, Remote $remote)
{
	db_delete('publisher_entity_deleted')
		->condition('entity_uuid', $entity_uuid)
		->condition('entity_type', $entity_type)
		->condition('remote', $remote->name)
		->execute();
}
