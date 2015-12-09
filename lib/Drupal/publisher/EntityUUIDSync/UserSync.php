<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

class UserSync extends SyncHandler
{
	protected $entity_type = 'user';

	public function getEntityMetadata(Remote $remote, Entity $entity)
	{
		return array(
			'username' => $entity->definition->name,
			'uuid' => $entity->uuid(),
		);
	}

	public function handleIncomingEntity(Remote $remote, array $entity_metadata = array())
	{
		$result = db_update('users')
			->fields(array('uuid' => $entity_metadata['uuid']))
			->condition('name', $entity_metadata['username'])
			->execute();

		return $result > 0;
	}
}
