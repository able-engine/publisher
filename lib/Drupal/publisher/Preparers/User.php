<?php

namespace Drupal\publisher\Preparers;

use Drupal\publisher\Entity;

class User extends BasePreparer {

	public static function handlesEntity(Entity $entity)
	{
		if ($entity->type() == 'user') return true;
		return false;
	}

	public function beforeSave(&$definition)
	{
		// If the user already exists, don't change the status.
		if (isset($definition->uid)) {
			if ($user = user_load($definition->uid)) {
				$definition->status = $user->status;
			}
		}
	}

	public function afterSave(&$definition)
	{
		// Intentionally left blank for now...
	}

	public function beforeDependencies(&$definition)
	{
		// Intentionally left blank for now...
	}

}
