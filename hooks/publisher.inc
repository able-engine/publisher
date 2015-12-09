<?php

/**
 * Implements hook_publisher_entity_need_alter().
 */
function publisher_publisher_entity_need_alter(array &$entity_need_info, \Drupal\publisher\Remote $remote)
{
	_publisher_entity_need_alter_bundle_map($entity_need_info);
}
