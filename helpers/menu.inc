<?php

/**
 * Given a path, returns the entity that path represents.
 *
 * @param string $path A path.
 *
 * @return \Drupal\publisher\Entity
 */
function publisher_entity_from_path($path)
{
	$menu_item = menu_get_item($path);
	if (!empty($menu_item['load_functions']) && is_array($menu_item['load_functions']) &&
		count($menu_item['load_functions']) > 0) {

		// Get the ID.
		$id = _publisher_entities_from_path_get_id($menu_item['path'], $path);
		if ($id === false) return false;

		// Get the first load function, load the entity and get the entity type.
		$load_function = reset($menu_item['load_functions']);
		if ($load_function) {
			$loaded = call_user_func($load_function, $id);
			if ($entity = \Drupal\publisher\Entity::convert($loaded)) {
				return $entity;
			}
		}

	}

	return null;
}

/**
 * Given a router path and a path that derives from that router path,
 * gets the ID of that entity from the path.
 *
 * @param string $router_path
 * @param string $path
 *
 * @return bool
 */
function _publisher_entities_from_path_get_id($router_path, $path)
{
	$router_path_segments = explode('/', $router_path);
	$path_segments = explode('/', $path);
	$router_path_argument_index = -1;
	foreach ($router_path_segments as $index => $segment) {
		if ($segment == '%') {
			$router_path_argument_index = $index;
			break;
		}
	}
	if (array_key_exists($router_path_argument_index, $path_segments)) {
		return $path_segments[$router_path_argument_index];
	}

	return false;
}
