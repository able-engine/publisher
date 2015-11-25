<?php

/**
 * Gets the current status for the specified entity.
 *
 * @param string $entity_uuid The UUID of the entity.
 * @param string $entity_type The type of entity.
 * @param string $remote      The machine name of the remote.
 *
 * @return mixed Either a result object containing the status, or null
 *               if no status exists.
 */
function publisher_entity_tracking_get_status($entity_uuid, $entity_type, $remote)
{
	$status = db_select('publisher_entity_tracking', 't')
		->fields('t')
		->orderBy('t.changed', 'desc')
		->range(0, 1)
		->condition('t.uuid', $entity_uuid)
		->condition('t.entity_type', $entity_type)
		->condition('t.remote', $remote)
		->execute()->fetch();

	return ($status) ? $status : null;
}
