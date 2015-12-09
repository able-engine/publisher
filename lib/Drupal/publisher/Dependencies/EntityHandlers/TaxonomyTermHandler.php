<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class TaxonomyTermHandler extends EntityHandlerBase
{
	public function handlesEntity(Entity $entity)
	{
		if ($entity->type() == 'taxonomy_term') return true;
		return false;
	}

	public function unhandleRevision(array $metadata = array())
	{
		$vocabulary = $this->entity->bundle();
		if ($loaded_vocabulary = taxonomy_vocabulary_machine_name_load($vocabulary)) {
			$this->entity->definition->vid = $loaded_vocabulary->vid;
		} else {
			throw new TaxonomyTermHandlerException('The vocabulary passed with the taxonomy term does not exist: ' . $vocabulary);
		}
	}
}

class TaxonomyTermHandlerException extends \Exception {}
