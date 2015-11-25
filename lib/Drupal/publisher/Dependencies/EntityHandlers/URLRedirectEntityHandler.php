<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class URLRedirectEntityHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		return module_exists('redirect') && module_exists('redirect_uuid') && $entity->type() == 'redirect';
	}

	public function unhandleRevision(array $metadata = array())
	{
		$this->entity->definition = (object)$this->entity->definition;
		$this->entity->definition->hash = redirect_hash($this->entity->definition);

		// If something with that hash already exists and points to the same node...
		$existing_redirect = redirect_load_by_hash($this->entity->definition->hash);
		if ($existing_redirect) {
			$this->entity->definition->rid = $existing_redirect->rid;
			$this->entity->definition->is_new = false;
		}
	}

}
