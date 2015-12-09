<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

class TaxonomyTermSync extends SyncHandler
{
	protected $entity_type = 'taxonomy_term';

	public function getEntityMetadata(Remote $remote, Entity $entity)
	{
		return array(
			'name' => $entity->definition->name,
			'uuid' => $entity->uuid(),
			'bundle' => $entity->bundle(),
		);
	}

	public function handleIncomingEntity(Remote $remote, array $entity_metadata = array())
	{
		// Get the VID with the specified name.
		$vocabulary = taxonomy_vocabulary_machine_name_load($entity_metadata['bundle']);
		if (!$vocabulary) return false;

		// Does an entity with that name exist in the same vocabulary?
		$result = db_update('taxonomy_term_data')
			->fields(array('uuid' => $entity_metadata['uuid']))
			->condition('vid', $vocabulary->vid)
			->condition('name', $entity_metadata['name'])
			->execute();

		return $result > 0;
	}
}
