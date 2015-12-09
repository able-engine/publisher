<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

interface SyncHandlerContract
{
	/**
	 * Given a remote, gets the entity metadata for the current entity.
	 *
	 * @param Remote $remote
	 * @param Entity $entity
	 *
	 * @return array
	 */
	public function getEntityMetadata(Remote $remote, Entity $entity);

	/**
	 * Given a remote and entity metadata, updates the entity UUID.
	 *
	 * @param Remote $remote
	 * @param array  $entity_metadata
	 *
	 * @return mixed
	 */
	public function handleIncomingEntity(Remote $remote, array $entity_metadata = array());

	/**
	 * Given a remote, gets the entity IDs representing the current
	 * entity type.
	 *
	 * @param Remote $remote
	 *
	 * @return array
	 */
	public function getEntityIDs(Remote $remote);
}
