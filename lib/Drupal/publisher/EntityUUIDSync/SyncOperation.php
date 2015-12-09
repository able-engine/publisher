<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Batch\Operation;
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

class SyncOperation extends Operation
{
	public function execute($entity_type, $entity_id, Remote $remote, &$context)
	{
		$handler = Sync::getInstance()->getSyncHandler($entity_type);
		$entity = Entity::load($entity_id, $entity_type);
		if (!$entity) return;
		$context['results']['metadata']['entities'][$entity_id] = $handler->getEntityMetadata($remote, $entity);

		$this->updateContextMessages($entity, $context);
	}
}
