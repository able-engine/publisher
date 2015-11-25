<?php
namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\RelationshipHandler;
use Drupal\publisher\Entity;

class EntityReferenceHandler extends FieldHandlerBase {

	use RelationshipHandler;

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'entityreference') {
			return true;
		}

		return false;
	}

	protected function getTargetType($field_name)
	{
		$field_info = \field_info_field($field_name);
		if (!isset($field_info['settings']['target_type'])) {
			throw new EntityReferenceHandlerException('The field ' . $field_name . ' does not have a proper target type.');
		}

		return $field_info['settings']['target_type'];
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		// Get the information for the specific field.
		$target_type = $this->getTargetType($field_name);

		if (!array_key_exists('target_id', $value)) {
			throw new EntityReferenceHandlerException('The field ' . $field_name . ' does not have a target ID.');
		}
		$entity = Entity::load($value['target_id'], $target_type);
		if (!$entity) {
			throw new EntityReferenceHandlerException('The field ' . $field_name . ' has no value.');
		}

		$value['target_id'] = self::createReferenceDefinition($entity);
		$this->addDependency($entity, true);
		$this->addRelationship($this->original_entity, $entity, $field_name, $delta);
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value['target_id'])) {
			return;
		}
		$entity = self::entityFromReferenceDefinition($value['target_id'], $this->entity);
		if ($entity) {
			$value['target_id'] = $entity->id();
		} else {
			unset($this->current_language_value[$delta]);
		}
	}

	public function getRelationshipHandlerName()
	{
		return 'EntityReference';
	}

	public function handleRelationship(array $relationship)
	{
		return $this->setValueWithKey($relationship, 'target_id');
	}
}

class EntityReferenceHandlerException extends \Exception {}
