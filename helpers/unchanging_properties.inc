<?php

function publisher_get_unchanging_properties($entity_type = false)
{
	$unchanging_properties = &drupal_static(__FUNCTION__ . '|' . $entity_type);
	if (!isset($unchanging_properties)) {

		$cache = cache_get('publisher_unchanging_properties|' . $entity_type);
		if ($cache && !empty($cache->data)) {
			$unchanging_properties = $cache->data;
		} else {
			$raw_unchanging_properties = module_invoke_all('publisher_unchanging_properties');
			$unchanging_properties = array();
			foreach (array('all', $entity_type) as $type) {
				if (array_key_exists($type, $raw_unchanging_properties) && is_array($raw_unchanging_properties[$type])) {
					$unchanging_properties = array_merge($unchanging_properties, $raw_unchanging_properties[$type]);
				}
			}
			cache_set('publisher_unchanging_properties', $unchanging_properties);
		}

	}

	return $unchanging_properties;
}
