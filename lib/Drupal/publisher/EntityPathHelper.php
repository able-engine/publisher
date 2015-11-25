<?php

namespace Drupal\publisher;

class EntityPathHelper {

	/**
	 * Entities from Path
	 *
	 * Given a system path with entity references (node IDs, etc), returns the router url
	 * and an array of entities represented in that path.
	 *
	 * @param string $path The path to break apart.
	 *
	 * @return array The router_url and an array of entities represented in the path.
	 */
	public static function entitiesFromPath($path)
	{
		if ($source = drupal_lookup_path('source', $path)) {
			$path = $source;
		}

		$entities = array(
			'router_url' => '',
			'entities' => array(),
		);

		if ($router_item = self::getRouterItem($path)) {
			if (is_array($router_item) && array_key_exists('page_arguments', $router_item) && is_array($router_item['page_arguments'])) {
				$entities['router_url'] = $router_item['path'];
				foreach ($router_item['page_arguments'] as $drupal_entity) {
					$entity = Entity::convert($drupal_entity);
					if ($entity) {
						$entities['entities'][] = $entity;
					}
				}
			}
		}

		// If nothing came of that, we'll leave the URL be.
		if (!$entities['router_url'])
			$entities['router_url'] = $path;

		return $entities;
	}

	/**
	 * Get Router Item
	 *
	 * Given a path, returns the associated router item.
	 *
	 * @param string $path The path to use when finding the router item.
	 *
	 * @return mixed The results of menu_get_item.
	 */
	public static function getRouterItem($path)
	{
		$path = self::normalizeUrl($path, false);
		return menu_get_item($path);
	}

	/**
	 * Normalize URL
	 *
	 * Normalizes a given URL, making it absolute (optionally) and relative to the
	 * site root.
	 *
	 * @param string $url      The URL to work on.
	 * @param bool   $absolute Whether or not to return an absolute result (with a / at the beginning).
	 *
	 * @return string The resulting URL.
	 */
	public static function normalizeUrl($url, $absolute = true)
	{
		global $base_url;

		// First, convert to an absolute URL.
		if (strpos($url, $base_url) === 0) {
			$url = str_replace($base_url, '', $url);
		}

		// Second, make sure we actually have an absolute URL.
		if (strpos($url, '/') !== 0) {
			$url = '/' . $url;
		}

		// If absolute is false, strip the leading slash.
		if ($absolute === false) {
			$url = ltrim($url, '/');
		}

		return $url;
	}

	/**
	 * Path from Entities
	 *
	 * Gets a system path given a router url and the entities that go in place of that URL.
	 * This function is to be used with entitiesFromPath()
	 *
	 * @param string $router_url The router URL.
	 * @param array  $entities   An array of Entity objects to be substituted into the URL.
	 *
	 * @return string
	 */
	public static function pathFromEntities($router_url, array $entities)
	{
		// Split the path into segments and process the arguments, generating the new path.
		$segments = explode('/', $router_url);
		$argument_keys = array();
		foreach ($segments as $index => $segment) {
			if ($segment == '%')
				$argument_keys[] = $index;
		}
		foreach ($entities as $index => $entity) {
			if (!($entity instanceof Entity)) continue;
			$segments[$argument_keys[$index]] = $entity->id();
		}

		return implode('/', $segments);
	}

}
