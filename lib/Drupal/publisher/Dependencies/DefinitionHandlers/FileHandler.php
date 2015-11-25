<?php
namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Entity;

class FileHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'uri' && $entity_type == 'file') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		$original_value = $value;
		$file_path = drupal_realpath($value);
		if (file_exists($file_path)) {
			$url = file_create_url($value);
			if ($url) {
				$value = array(
					'original_path' => $original_value,
					'url' => $url, // TODO: What happens with files in the private filesystem?
					'uuid' => $this->entity->uuid(),
					'uid' => $this->entity->definition->uid,
				);
			} else {
				throw new FileHandlerException('The specified file ' . $file_path . ' could not be read.');
			}
		} else {
			throw new FileHandlerException('The specified file ' . $file_path . ' does not exist.');
		}
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (!is_array($value)) return;
		if (!array_key_exists('url', $value)) return;
		if (!array_key_exists('original_path', $value)) return;
		if (!array_key_exists('uuid', $value)) return;
		if (!array_key_exists('uid', $value)) return;

		// Make sure a file doesn't already exist with that UUID.
		$entity = Entity::loadByUUID($value['uuid'], 'file');
		if ($entity) {
			$definition = $entity->definition;
		} else {

			// Make sure a file doesn't already exist with that URI.
			$query = db_select('file_managed', 'f');
			$query->addField('f', 'fid');
			$query->condition('f.uri', $value['original_path']);
			$query->range(0, 1);
			$result = $query->execute()->fetch();
			if ($result) {
				$entity = Entity::load($result->fid, 'file');
				if ($entity) {
					$definition = $entity->definition;
				}
			}

		}

		// If we haven't found the file yet, upload it.
		if (!isset($definition)) {

			// Decode the contents of the file.
			$contents = file_get_contents($value['url']);
			if ($contents === false) {
				throw new FileHandlerException('There was an error fetching the contents of the file.');
			}

			// Save the file.
			$file = file_save_data($contents, $value['original_path']);
			if (!$file || !$file->fid) {
				throw new FileHandlerException('There was an error saving the file to the database.');
			}
			$file->uuid = $value['uuid'];
			$file->uid = $value['uid'];
			file_save($file);
			$definition = $file;

		}

		// Don't completely reset the entity.
		foreach ((array)$definition as $key => $val) {
			$this->unresolved_definition->$key = $val;
		}

	}

}

class FileHandlerException extends \Exception {}
