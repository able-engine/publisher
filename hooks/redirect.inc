<?php

/**
 * Implements hook_redirect_update()
 */
function publisher_redirect_update($redirect)
{
	$destination = redirect_load_entity_from_path($redirect->redirect);
	if ($destination) {
		$destination_entity = new \Drupal\publisher\Entity($destination['entity'], $destination['entity_type']);
		foreach (publisher_get_remotes() as $remote) {
			publisher_entity_tracking_create_status($destination_entity, $remote);
		}
	}
}

/**
 * Implements hook_redirect_insert()
 */
function publisher_redirect_insert($redirect)
{
	publisher_redirect_update($redirect);
}
