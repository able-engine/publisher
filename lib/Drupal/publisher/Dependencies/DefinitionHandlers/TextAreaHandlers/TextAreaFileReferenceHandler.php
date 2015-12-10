<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers\TextAreaHandlers;

use Drupal\publisher\Dependencies\InvalidReferenceDefinitionException;
use Drupal\publisher\Entity;
use Drupal\publisher\EntityPathHelper;

class TextAreaFileReferenceHandler extends HandlerBase {

	protected function handleSingleValue($entity_type, $field_type, $field_name, &$value, $index)
	{
		// Record all file URLs in an array.
		$files_found = array();
		$contents_value = $this->getContents($value);
		if (!$contents_value) return;
		$contents = $this->getDOMContents($contents_value);
		if (!$contents) return;

		$files_found += $this->parseTags($contents->xpath('//img'), 'src');
		$files_found += $this->parseTags($contents->xpath('//a'), 'href');

		if (count($files_found) > 0) {
			$this->postHandledResults($value, $files_found, 'files');
		}
	}

	protected function unhandleSingleValue($entity_type, $field_type, $field_name, &$value)
	{
		if (!is_array($value)) return;
		if (!array_key_exists('files', $value)) return;
		if (!array_key_exists('contents', $value)) return;

		$find = array();
		$replace = array();
		foreach ($value['files'] as $original_url => $file) {
			try {
				$file_entity = self::entityFromReferenceDefinition($file);
				if (!$file_entity) continue;
				$find[] = $original_url;
				$replace[] = file_create_url($file_entity->definition->uri);
			} catch (InvalidReferenceDefinitionException $ex) {
				drupal_set_message(t('Tried to import textarea-referenced file: @uuid, but it did not exist. Ignoring.', array(
					'@uuid' => $file['uuid'],
				)), 'warning');
				$find[] = $original_url;
				$replace[] = $original_url;
			}
		}

		$this->postUnhandleResults($value, $find, $replace, 'files');
	}

	protected function parseTags(array $tags, $attribute_name)
	{
		$result = array();
		foreach ($tags as $tag) {
			if (isset($tag[$attribute_name])) {
				$entity = $this->urlToFileEntity($tag[$attribute_name]);
				if ($entity !== false) {
					$this->addDependency($entity);
					$result[(string)$tag[$attribute_name]] = self::createReferenceDefinition($entity);
				}
			}
		}
		return $result;
	}

	protected function urlToFileEntity($url)
	{
		$url = urldecode(DRUPAL_ROOT . EntityPathHelper::normalizeUrl($url));
		if (file_exists($url)) {

			// Get the filename.
			$filename = pathinfo($url, PATHINFO_FILENAME) . '.' . pathinfo($url, PATHINFO_EXTENSION);
			$filesize = filesize($url);
			$files = db_select('file_managed', 'f')
				->fields('f', array('fid', 'uri', 'filesize'))
				->condition('filename', $filename)
				->condition('filesize', $filesize)
				->execute();

			$found_fid = -1;
			while ($row = $files->fetch()) {
				$result_uri = drupal_realpath($row->uri);
				if ($result_uri == drupal_realpath($url)) {
					$found_fid = $row->fid;
					break;
				}
			}

			if ($found_fid !== -1) {
				return Entity::load($found_fid, 'file');
			} else {

				// Create the file entity.
				if ($contents = file_get_contents($url)) {

					$public_files_directory = DRUPAL_ROOT . '/' . variable_get('file_public_path', conf_path() . '/files') . '/';
					$schema_url = 'public://' . str_replace($public_files_directory, '', $url);

					// This will basically re-create the same file with the same filename, so we don't
					// need to check to see if the file already exists because we don't care to replace
					// the file with itself.
					$file = file_save_data($contents, $schema_url, FILE_EXISTS_REPLACE);
					return Entity::load($file->fid, 'file');

				}

			}

		}

		return false;
	}

}

