<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class MenuLinkHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		return $entity->type() == 'menu_link';
	}

	public function handleEntity(array &$metadata = array())
	{
		// Get the definition.
		$definition = $this->original_entity->definition;

		// Get the weights of the other menu item UUIDs associated with this link.
		$menu_links = menu_load_links($definition->menu_name);
		$sibling_links = array();
		foreach ($menu_links as $link) {

			// If the menu links are at the same depth, or are at the same depth and
			// under the same parent if they're not at the root of the menu.
			if (($definition->depth == 1 && $link['depth'] == $definition->depth) ||
				($definition->depth > 1 && $link['depth'] == $definition->depth &&
				$link['plid'] == $definition->plid)) {
				$sibling_links[$link['uuid']] = $link['weight'];
			}

		}

		$metadata['sibling_link_weights'] = $sibling_links;
	}

	public function unhandleEntity(array $metadata = array())
	{
		// Update the weight of the sibling links if the entity supports
		// it.
		if (array_key_exists('sibling_link_weights', $metadata) &&
			is_array($metadata['sibling_link_weights'])) {
			foreach ($metadata['sibling_link_weights'] as $uuid => $weight) {
				if ($uuid != $this->original_entity->uuid()) {
					$menu_link = Entity::loadByUUID($uuid, 'menu_link');
					$menu_link->definition->weight = $weight;
					$menu_link->save();
				}
			}
		}
	}

}
