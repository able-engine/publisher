<?php

namespace Drupal\publisher\Preparers;

use Drupal\publisher\Entity;

class Node extends BasePreparer {

	public static function handlesEntity(Entity $entity)
	{
		if ($entity->type() == 'node') return true;
		return false;
	}

	public function beforeSave(&$definition)
	{
		// Nothing happens here...
	}

	public function afterSave(&$definition)
	{
		if (module_exists('workbench_moderation')) {

			// Update the previous state.
			if (array_key_exists('my_revision', $definition->workbench_moderation)) {
				$definition->workbench_moderation['my_revision']->state =
					$definition->workbench_moderation['my_revision']->from_state;
			}

			// Update the published VID.
			if (array_key_exists('published', $definition->workbench_moderation)) {
				$definition->workbench_moderation['published']->vid = $definition->vid;
			}

			workbench_moderation_moderate($definition, $definition->workbench_moderation_state_new);

			// Now we need to update the user manually because workbench moderation hard-codes it.
			db_update('workbench_moderation_node_history')
				->condition('nid', $definition->nid)
				->condition('vid', $definition->vid)
				->fields(array(
					'uid' => $definition->uid,
				))
				->execute();

		}
	}

	public function beforeDependencies(&$definition)
	{
		unset($definition->menu_links);
	}

}
