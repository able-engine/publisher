<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class URLRedirectHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		if (!module_exists('redirect') || !module_exists('redirect_uuid')) return false;
		return redirect_entity_type_supports_redirects($entity->type());
	}

	public function handleEntity(array &$metadata = array())
	{
		$uri = entity_uri($this->original_entity->type(), $this->original_entity->definition);
		if (empty($uri['path'])) return;

		$redirects = redirect_load_multiple(false, array('redirect' => $uri['path']));
		foreach ($redirects as $index => $redirect) {
			$entity = new Entity($redirect, 'redirect');
			$this->addDependency($entity, false);
			$this->addDependency($this->original_entity, array($entity->uuid()));
		}
	}

}
