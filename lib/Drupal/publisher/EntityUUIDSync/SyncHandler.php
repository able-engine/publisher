<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Remote;

abstract class SyncHandler implements SyncHandlerContract
{
	protected $entity_type = false;

	public function getEntityIDs(Remote $remote)
	{
		if ($this->entity_type) {

			// Get the entity type info.
			$info = entity_get_info($this->entity_type);
			$base_table = $info['base table'];
			$id_key = $info['entity keys']['id'];

			// If we don't have a UUID, throw an exception.
			if (empty($info['entity keys']['uuid'])) {
				throw new \Exception('The entity type ' . $this->entity_type . ' doesn\'t support UUIDs.');
			}

			return db_select($base_table, 'entity')
				->fields('entity', array($id_key))
				->execute()->fetchCol();

		}

		return array();
	}
}
