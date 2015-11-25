<?php

use Drupal\publisher\Remote;

function action_status($node)
{
	drupal_set_title(t('Publisher Status'));

	// Get the entity.
	$entity = new \Drupal\publisher\Entity($node, 'node');

	return publisher_entity_tracking_remote_statuses_table('node/' . $node->nid . '/publisher/',
		$entity);
}

function action_remote_status($node, Remote $remote)
{
	$entity = new \Drupal\publisher\Entity($node, 'node');
	drupal_set_title(t('Publisher Status for @remote', array('@remote' => $remote->label)));
	return drupal_get_form('publisher_entity_tracking_status', $remote, $entity);
}
