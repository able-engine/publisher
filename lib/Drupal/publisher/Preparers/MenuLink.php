<?php

namespace Drupal\publisher\Preparers;

use Drupal\publisher\Entity;

class MenuLink extends BasePreparer {

	public static function handlesEntity(Entity $entity)
	{
		if ($entity->type() == 'menu_link') return true;
		return false;
	}

	public function beforeSave(&$definition)
	{
		// Unset any items that are arrays.
		foreach ($this->entity->definition as $key => $value) {
			if (is_array($value)) {
				unset($this->entity->definition->$key);
			}
		}
	}

}
