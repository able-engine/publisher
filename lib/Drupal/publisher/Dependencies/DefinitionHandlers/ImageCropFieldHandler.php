<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\InvalidReferenceDefinitionException;
use Drupal\publisher\Entity;

class ImageCropFieldHandler extends FieldHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'imagefield_crop') return true;
		return false;
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value)) return;

		if (!array_key_exists('fid', $value)) {
			throw new ImageCropFieldHandlerException('The field ' . $field_name . ' does not have a file ID.');
		}

		$fid = isset($value['fid']['original'])? $value['fid']['original'] : $value['fid'];
		$entity = Entity::load($fid, 'file');
		if (!$entity) {
			throw new FileReferenceHandlerException('The field ' . $field_name . ' has no value.');
		}

		$value['fid'] = self::createReferenceDefinition($entity);
		$this->addDependency($entity);

		// Get the original image and store it.
		$source_file = _imagefield_crop_file_to_crop($value['fid']);
		if (!$source_file) {
			throw new ImageCropFieldHandlerException('The field ' . $field_name . ' does not have a valid source file ID.');
		}

		$source_file_entity = Entity::load($source_file->fid, 'file');
		if (!$source_file_entity) {
			throw new ImageCropFieldHandlerException('The field ' . $field_name . ' has no source image.');
		}

		$value['source_fid'] = self::createReferenceDefinition($source_file_entity);
		$this->addDependency($source_file_entity);

		// Handle the UID now.
		if (array_key_exists('uid', $value)) {
			$entity = Entity::load($value['uid'], 'user');
			if (!$entity) {
				throw new FileReferenceHandlerException('The field ' . $field_name . ' is associated with a user that doesn\'t exist.');
			}
			$value['uid'] = self::createReferenceDefinition($entity);
			$this->addDependency($entity);
		}

		// Make sure the dimensions of the cropbox are always set.
		if (isset($this->entity->definition->$field_name)) {
			$original_entity_definition_language = reset($this->entity->definition->$field_name); // First language.
			if (array_key_exists($delta, $original_entity_definition_language)) {
				$original_entity_definition = $original_entity_definition_language[$delta];
				if (!array_key_exists('cropbox_x', $value))
					$value['cropbox_x'] = array_key_exists('cropbox_x', $original_entity_definition) ? $original_entity_definition['cropbox_x'] : 0;
				if (!array_key_exists('cropbox_y', $value))
					$value['cropbox_y'] = array_key_exists('cropbox_y', $original_entity_definition) ? $original_entity_definition['cropbox_y'] : 0;
				if (!array_key_exists('cropbox_width', $value))
					$value['cropbox_width'] = array_key_exists('cropbox_width', $original_entity_definition) ? $original_entity_definition['cropbox_width'] : 0;
				if (!array_key_exists('cropbox_height', $value))
					$value['cropbox_height'] = array_key_exists('cropbox_height', $original_entity_definition) ? $original_entity_definition['cropbox_height'] : 0;
			}
		}
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value['fid'])) return;
		if (!is_array($value['source_fid'])) return;

		$key = 'fid';
		if ($this->entity->isNew()) {
			$key = 'source_fid';
		}

		try {
			$file = self::entityFromReferenceDefinition($value[$key]);
			$value['fid'] = $file->id();

			// Now handle the fields off the file object. This will take care of updating
			// things like uri, filename, filesize, etc.
			foreach ($file->definition as $key => $file_value) {
				if ($key == 'alt' || $key == 'title') continue; // Don't override the title and alt.
				$value[$key] = $file_value;
			}
		} catch (InvalidReferenceDefinitionException $ex) {
			drupal_set_message(t('Tried to import cropped image file: @uuid, but it did not exist. Ignoring.', array(
				'@uuid' => $value[$key]['uuid'],
			)), 'warning');

			$value = null;
		}

		// Now handle the user.
		if (array_key_exists('uid', $value) && is_array($value['uid'])) {
			$entity = self::entityFromReferenceDefinition($value['uid']);
			$value['uid'] = $entity->id();
		}
	}

}

class ImageCropFieldHandlerException extends \Exception {}
