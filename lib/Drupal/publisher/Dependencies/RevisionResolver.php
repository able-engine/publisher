<?php

namespace Drupal\publisher\Dependencies;

use Drupal\publisher\Entity;
use Drupal\publisher\EntityDiff;

class RevisionResolver extends Resolver {

	public function __construct(Entity $entity, $auto_resolve = true)
	{
		$this->entity = $entity;
		$this->base_entity = clone $entity;

		// Automatically resolve the dependencies on construct unless otherwise specified.
		if ($auto_resolve) {
			$this->resolve();
		}
	}

	public function resolve($recurse = true)
	{
		// Get all revisions for the entity.
		$diff = new EntityDiff($this->entity);
		$revisions = $diff->getRevisionHistory();
		if (is_array($revisions)) {

			// Make it so that we process the latest revision last.
			$revisions = array_reverse($revisions);

			foreach ($revisions as $revision_id) {
				$entity = Entity::load($this->base_entity->id(), $this->base_entity->type());
				$entity->setRevision($revision_id);
				$this->entity = $entity;
				$this->base_entity = clone $entity;
				$this->resolveDependencies($recurse, $entity->definition);
			}

		} else {
			$this->resolveDependencies($recurse);
		}
	}

}
