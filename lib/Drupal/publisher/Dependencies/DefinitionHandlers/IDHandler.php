<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;


use Drupal\publisher\Dependencies\InvalidReferenceDefinitionException;
use Drupal\publisher\Entity;

class IDHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'id' && $subtype != 'xmlsitemap') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		if ($entity_type == 'user' && ($value === 0 || $value === '0')) return;

		// We should be getting a number for the value.
		if (!is_numeric($value)) {
			throw new IDHandlerException('The field of type ' . $field_type . ' does not have a numeric value.');
		}

		// Load the entity.
		$entity = Entity::load($value, $entity_type);
		if (!$entity) {
			throw new IDHandlerException('The entity ' . $value . ' does not exist.');
		}

		// Update the value.
		$value = self::createReferenceDefinition($entity);
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (is_array($value) && self::verifyReferenceDefinition($value) === true) {
			try {
				$entity = self::entityFromReferenceDefinition($value);
				$value = $entity->id();
			} catch (InvalidReferenceDefinitionException $ex) {
				unset($this->unresolved_definition->{$field_name});
			}
		}
	}

}

class IDHandlerException extends \Exception {}
