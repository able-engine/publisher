<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

abstract class FieldHandlerBase extends DefinitionHandlerBase {

	/**
	 * When unhandling a field, the current language value.
	 * @var array
	 */
	protected $current_language_value = null;

	protected abstract function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta);
	protected abstract function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta);

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		foreach ($value as $language => $values) {
			foreach ($values as $delta => $individual_value) {
				$this->handleIndividualValue($entity_type,
					$field_type,
					$field_name,
					$value[$language][$delta],
					$delta);
			}
		}
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		foreach ($value as $language => $values) {
			$this->current_language_value = &$value[$language];
			foreach ($values as $delta => $individual_value) {
				$this->unhandleIndividualValue($entity_type, $field_type, $field_name, $value[$language][$delta], $delta);
			}
		}

		// Reset the language of the field if there is only one value available.
		if (count($value) == 1) {
			$value[$this->entity->language()] = reset($value);
		}
	}

}
