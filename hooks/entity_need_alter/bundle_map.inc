<?php

function _publisher_entity_need_alter_bundle_map(array &$entity_need_info)
{
	// Get the bundle key for the entity type.
	$info = entity_get_info($entity_need_info['entity_type']);
	if (empty($info['entity keys']['bundle'])) return;
	$bundle_key = $info['entity keys']['bundle'];

	// Anywhere the bundle is used for the entity, run it through the bundle mapper.
	foreach ($entity_need_info['revisions_payload'] as &$revision) {
		$current_mode = &$revision['additions']; // We only care about additions.
		foreach ($current_mode as $key => &$value) {
			if ($key != $bundle_key) continue;
			$value = publisher_map_entity_bundle($entity_need_info['entity_type'], $value);
		}
	}
}
