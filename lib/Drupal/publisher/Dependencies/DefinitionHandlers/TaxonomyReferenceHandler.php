<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\RelationshipHandler;
use Drupal\publisher\Entity;

class TaxonomyReferenceHandler extends FieldHandlerBase {

	use RelationshipHandler;

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'taxonomy_term_reference') return true;
		return false;
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		// Try to load the entity.
		if (!array_key_exists('tid', $value)) {
			throw new TaxonomyReferenceHandlerException('The term identifier did not exist on the entity.');
		}
		$entity = Entity::load($value['tid'], 'taxonomy_term');
		if (!$entity) {
			throw new TaxonomyReferenceHandlerException('The entity ' . $value['tid'] . ' does not exist.');
		}

		// Set the reference definition for the field.
		$value['tid'] = self::createReferenceDefinition($entity);
		$this->addDependency($entity, true);
		$this->addRelationship($this->original_entity, $entity, $field_name, $delta);
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value['tid'])) return;
		$entity = self::entityFromReferenceDefinition($value['tid'], $this->entity);
		if ($entity) {
			$value['tid'] = $entity->id();
		} else {
			unset($this->current_language_value[$delta]);
		}
	}

	public function getRelationshipHandlerName()
	{
		return 'TaxonomyReference';
	}

	public function handleRelationship(array $relationship)
	{
		return $this->setValueWithKey($relationship, 'tid');
	}
}

class TaxonomyReferenceHandlerException extends \Exception {}
