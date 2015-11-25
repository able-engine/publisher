<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;
use Drupal\publisher\Entity;

class UserHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'uid' && $entity_type != 'user') return true;
		if ($type == 'revision_uid') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		if ($value === 0 || $value === '0') return; // Ignore anonymous user.

		// We should be getting a number for the value.
		if (!is_numeric($value)) {
			throw new UserHandlerException('The field of type ' . $field_type . ' does not have a numeric value.');
		}

		// Load the user entity.
		$entity = Entity::load($value, 'user');
		if (!$entity) {
			throw new UserHandlerException('The user ' . $value . ' does not exist.');
		}

		// Update the value.
		$value = self::createReferenceDefinition($entity);
		$this->addDependency($entity);
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (is_array($value) && self::verifyReferenceDefinition($value) === true) {
			$entity = self::entityFromReferenceDefinition($value);
			$value = $entity->id();
		}
	}
}

class UserHandlerException extends \Exception {}
