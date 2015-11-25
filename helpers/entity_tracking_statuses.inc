<?php

use Drupal\publisher\Dependencies\Resolver;
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

/**
 * Creates an entity tracking status for the specified entity with the
 * specified syncing options.
 *
 * @param Entity $entity          The entity to create the tracking information for.
 * @param Remote $remote          The remote to add the tracking information for.
 * @param array  $override_fields An array of fields to override in the
 *                                query.
 *
 * @throws Exception
 */
function publisher_entity_tracking_create_status(Entity $entity, Remote $remote, array $override_fields = array())
{
	global $user;

	// If the entity doesn't have a UUID, it's a special entity and we should return.
	// For example, rules_config entities don't have UUIDs.
	if (!$entity->uuid()) {
		return;
	}

	$fields = array(
		'uuid' => $entity->uuid(),
		'entity_type' => $entity->type(),
		'vuuid' => $entity->vuuid(),
		'changed' => REQUEST_TIME,
		'remote' => $remote->name,
		'uid' => !empty($user->uid) ? $user->uid : 0,
	);

	// Add the override fields if they're available.
	$fields = array_replace_recursive($fields, $override_fields);

	db_insert('publisher_entity_tracking')->fields($fields)->execute();
}

/**
 * Updates the tracking table to mark the specified entity as synced with
 * the specified remote.
 *
 * @param Entity $entity The entity.
 * @param Remote $remote The remote.
 */
function publisher_entity_tracking_mark_as_synced(Entity $entity, Remote $remote)
{
	// Make sure we have a valid entity.
	if (!$entity->uuid()) {
		return;
	}

	// Remove any old statuses related to this entity (including all revisions).
	db_delete('publisher_entity_tracking')
		->condition('uuid', $entity->uuid())
		->condition('entity_type', $entity->type())
		->condition('remote', $remote->name)
		->execute();

	// Create a new status representing the latest revision.
	publisher_entity_tracking_create_status($entity, $remote, array(
		'date_synced' => REQUEST_TIME,
	));
}

/**
 * Deletes all statuses related to the specified entity.
 *
 * @param Entity $entity The entity.
 */
function publisher_entity_tracking_delete_status(Entity $entity)
{
	// Make sure we have a valid entity.
	if (!$entity->uuid()) {
		return;
	}

	db_delete('publisher_entity_tracking')
		->condition('uuid', $entity->uuid())
		->condition('entity_type', $entity->type())
		->execute();
}

function publisher_entity_tracking_get_statuses_query(Remote $remote, Entity $entity = null, SelectQueryInterface $base_query = null)
{
	if (!$base_query) {
		$base_query = db_select('publisher_entity_tracking', 't');
	}

	$base_query->fields('t');
	$base_query->condition('t.remote', $remote->name);
	$base_query->addJoin('left', 'node', 'n', "n.uuid = t.uuid and t.entity_type = 'node'");
	$base_query->addField('n', 'title');
	$base_query->addField('n', 'nid');
	$base_query->addExpression("concat(t.uuid, '|', t.entity_type)", 'id');
	$base_query->condition('date_synced', 0);

	// If we have an entity, get the dependencies of that entity and find
	// all the related statuses.
	if ($entity !== null && $entity instanceof Entity) {

		// Get the dependencies for the entity.
		$resolver = new Resolver($entity);
		$in_array = array();
		foreach ($resolver->dependencies() as $dependency) {
			$in_array[] = $dependency['uuid'] . '|' . $dependency['entity_type'];
		}

		// Add them to the query.
		$base_query->havingCondition('id', $in_array, 'IN');

	} else {
		$base_query->condition('t.entity_type', 'node');
	}

	return $base_query;
}

function publisher_entity_tracking_remote_statuses_table($path_base = 'admin/reports/publisher/', Entity $entity = null)
{
	$header = array(
		t('Remote'),
		t('At a Glance'),
		t('Operations'),
	);

	// Generate the rows.
	$rows = array();
	foreach (publisher_get_remotes() as $remote) {
		$query = publisher_entity_tracking_get_statuses_query($remote, $entity);
		$num_results = $query->countQuery()->execute()->fetchField();
		$status_class = 'ok';
		$status_label = 'No Pending Changes';
		if ($num_results) {
			$status_class = 'warning';
			$status_label = $num_results . ' Pending Changes';
		}

		$row = array(
			'data' => array(
				check_plain($remote->label),
				check_plain($status_label),
				array(
					'data' => array(
						'#type' => 'link',
						'#title' => t('View'),
						'#href' => $path_base . $remote->name,
					),
				),
			),
			'class' => array($status_class),
		);

		$rows[] = $row;
	}

	return theme('table', array(
		'header' => $header,
		'rows' => $rows,
		'empty' => t('There are currently no remotes available.'),
	));
}
