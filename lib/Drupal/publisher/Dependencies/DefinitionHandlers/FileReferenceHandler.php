<?php
namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\RelationshipHandler;
use Drupal\publisher\Entity;

class FileReferenceHandler extends FieldHandlerBase {

	use RelationshipHandler;

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'file') return true;
		if ($type == 'image') return true;
		return false;
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		global $base_url;

		if (!is_array($value)) {
			throw new FileReferenceHandlerException('The field ' . $field_name . ' has an invalid value.');
		}

		if (!array_key_exists('fid', $value)) {
			throw new FileReferenceHandlerException('The field ' . $field_name . ' does not have a file ID.');
		}

		$entity = Entity::load($value['fid'], 'file');
		if (!$entity) {
			throw new FileReferenceHandlerException('The field ' . $field_name . ' has no value.');
		}

		if (!file_exists(str_replace($base_url, DRUPAL_ROOT, urldecode(file_create_url($entity->definition->uri))))) {
			drupal_set_message(t('The file in the field !field does not exist. Setting the value to <code>NULL</code>',
				array('!field' => $field_name)), 'warning');
			$value = null;

			return;
		}

		$value['fid'] = self::createReferenceDefinition($entity);
		$this->addDependency($entity, true);
		$this->addRelationship($this->original_entity, $entity, $field_name, $delta, array(
			'alt' => !empty($value['alt']) ? $value['alt'] : '',
			'title' => !empty($value['title']) ? $value['title'] : '',
			'display' => !empty($value['display']) ? $value['display'] : true,
			'description' => !empty($value['description']) ? $value['description'] : '',
		));
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (!is_array($value['fid'])) {
			return;
		}
		$entity = self::entityFromReferenceDefinition($value['fid'], $this->entity);
		if ($entity) {
			$value['fid'] = $entity->id();
		} else {
			unset($this->current_language_value[$delta]);
		}
	}

	public function getRelationshipHandlerName()
	{
		return 'FileReference';
	}

	public function handleRelationship(array $relationship)
	{
		$arguments = unserialize($relationship['relationship_arguments']);
		return $this->setValueWithKey($relationship, 'fid', is_array($arguments) ? $arguments : array());
	}
}

class FileReferenceHandlerException extends \Exception {}
