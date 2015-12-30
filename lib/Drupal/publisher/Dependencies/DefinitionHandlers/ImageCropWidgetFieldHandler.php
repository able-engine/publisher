<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

class ImageCropWidgetFieldHandler extends ImageCropFieldHandler
{
	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'image') return true;
		return false;
	}

	protected function shouldHandleValue($entity_type, $field_name)
	{
		// Make sure the field has the correct imagefield_crop widget.
		$instances = field_info_instances($entity_type, $this->entity->bundle());
		if (!array_key_exists($field_name, $instances)) {
			return false;
		}
		if ($instances[$field_name]['widget']['type'] != 'imagefield_crop_widget') {
			return false;
		}

		return true;
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value)) return;
		if (!$this->shouldHandleValue($entity_type, $field_name)) return;

		// Handle the rest of the image field.
		parent::handleIndividualValue($entity_type, $field_type, $field_name, $value, $delta);
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value)) return;
		if (!$this->shouldHandleValue($entity_type, $field_name)) return;

		// Handle the rest of the image field.
		parent::unhandleIndividualValue($entity_type, $field_type, $field_name, $value, $delta);
	}
}

class ImageCropWidgetFieldHandlerException extends \Exception {}
