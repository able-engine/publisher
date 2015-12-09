<?php

/**
 * Gets an array of arrays, keyed by entity type (or for just one entity type
 * if the entity type is passed) of the map from old bundle names to new
 * bundle names, based on entity type.
 *
 * @param string|bool|false $entity_type The entity type or false to show all entities.
 *
 * @return array
 */
function publisher_get_bundle_map($entity_type = false)
{
	$bundle_map = &drupal_static(__FUNCTION__);
	if (!isset($bundle_map)) {
		$bundle_map = module_invoke_all('publisher_bundle_maps');
	}

	if ($entity_type) {
		if (array_key_exists($entity_type, $bundle_map)) {
			return $bundle_map[$entity_type];
		} else {
			return array();
		}
	} else {
		return $bundle_map;
	}
}

/**
 * Checks to see if a bundle map exists for the current entity bundle. If it
 * does, it returns the new name of the bundle. Otherwise, it returns the
 * current name of the bundle.
 *
 * @param string $entity_type
 * @param string $bundle
 *
 * @return string
 */
function publisher_map_entity_bundle($entity_type, $bundle)
{
	$bundle_map = publisher_get_bundle_map($entity_type);
	if (is_string($bundle) && array_key_exists($bundle, $bundle_map)) {
		return $bundle_map[$bundle];
	} else return $bundle;
}
