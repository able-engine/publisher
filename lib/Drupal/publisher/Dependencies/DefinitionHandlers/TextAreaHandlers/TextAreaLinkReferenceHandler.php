<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers\TextAreaHandlers;

use Drupal\publisher\EntityPathHelper;

class TextAreaLinkReferenceHandler extends HandlerBase {

	protected function handleSingleValue($entity_type, $field_type, $field_name, &$value, $index)
	{
		$references = array();
		$contents_value = $this->getContents($value);
		if (!$contents_value) return;
		$contents = $this->getDOMContents($contents_value);

		// Verify that we actually have contents.
		if (!$contents) return;

		$references += $this->parseTags($contents->xpath('//a'), 'href');

		if (count($references) > 0) {
			$this->postHandledResults($value, $references, 'references');
		}
	}

	protected function unhandleSingleValue($entity_type, $field_type, $field_name, &$value)
	{
		if (!is_array($value)) return;
		if (!array_key_exists('references', $value)) return;
		if (!array_key_exists('contents', $value)) return;

		$find = array();
		$replace = array();
		foreach ($value['references'] as $original_url => $reference) {
			if (is_array($reference)) {

				$entities = array();
				foreach ($reference['entities'] as $entity) {
					$loaded_entity = self::entityFromReferenceDefinition($entity);
					if ($loaded_entity) {
						$entities[] = $loaded_entity;
					}
				}
				$resulting_url = EntityPathHelper::pathFromEntities($reference['router_url'], $entities);

				// If we have an alias, try and go back.
				if ($reference['alias'] === true) {
					$attempt_at_alias = drupal_get_path_alias($resulting_url);
					if ($attempt_at_alias) {
						$resulting_url = $attempt_at_alias;
					}
				}

				$find[] = trim($original_url, '/');
				$replace[] = trim($resulting_url, '/');

			}
		}

		$this->postUnhandleResults($value, $find, $replace, 'references');
	}

	protected function parseTags(array $tags, $attribute_name)
	{
		$result = array();
		foreach ($tags as $tag) {
			if (isset($tag[$attribute_name])) {
				$original = (string)$tag[$attribute_name];

				// Lookup the system path if we're dealing with an alias.
				$source_path = drupal_lookup_path('source', $original);
				$alias = false;
				if ($source_path) {
					$original = $source_path;
					$alias = true;
				}

				$entities = $this->entitiesFromUrl($original, $alias);
				$result[(string)$tag[$attribute_name]] = $entities;
			}
		}
		return $result;
	}

	protected function entitiesFromUrl($url, $alias = false)
	{
		$entities = EntityPathHelper::entitiesFromPath($url);

		foreach ($entities['entities'] as $index => $entity) {
			$this->addDependency($entity);
			$entities['entities'][$index] = self::createReferenceDefinition($entity);
		}

		$entities['alias'] = $alias;
		return $entities;
	}

}
