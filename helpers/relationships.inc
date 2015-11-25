<?php

function publisher_relationships_save(array $relationship)
{
	$last_checked = array_key_exists('last_checked', $relationship) ? $relationship['last_checked'] : 0;
	$arguments = $relationship['relationship_arguments'];
	unset($relationship['last_checked']);
	unset($relationship['relationship_arguments']);

	if (is_array($arguments)) {
		$arguments = serialize($arguments);
	}

	db_merge('publisher_pending_relationships')
		->fields(array(
			'last_checked' => $last_checked,
			'relationship_arguments' => $arguments,
		))
		->key($relationship)
		->execute();
}

function publisher_relationships_remove(array $relationship)
{
	$query = db_delete('publisher_pending_relationships');
	foreach ($relationship as $key => $value) {
		if (in_array($key, array('last_checked', 'relationship_arguments'))) continue;
		if (is_array($value)) {
			$value = serialize($value);
		}
		$query->condition($key, $value, '=');
	}
	$query->execute();
}

function publisher_relationships_update_entity($source_type, $source_uuid, $source_vuuid = false)
{
	$source_condition = db_and();
	$source_condition->condition('source_type', $source_type);
	$source_condition->condition('source_uuid', $source_uuid);

	$destination_condition = db_and();
	$destination_condition->condition('destination_type', $source_type);
	$destination_condition->condition('destination_uuid', $source_uuid);

	if ($source_vuuid !== false) {
		$source_condition->condition('source_vuuid', $source_vuuid);
		$destination_condition->condition(db_or()
			->condition('destination_vuuid', $source_vuuid)
			->condition('destination_vuuid', '')
		);
	}

	$query = db_select('publisher_pending_relationships', 'r')
		->fields('r')
		->condition(db_or()
			->condition($source_condition)
			->condition($destination_condition)
		);

	$relationships = $query->execute();
	while ($relationship = $relationships->fetchAssoc()) {
		publisher_relationships_check_relationship($relationship);
	}
}

function publisher_relationships_update_topmost()
{
	$relationships = db_select('publisher_pending_relationships', 'r')
		->fields('r')
		->orderBy('last_checked')
		->range(0, 50)
		->execute();

	while ($relationship = $relationships->fetchAssoc()) {
		publisher_relationships_check_relationship($relationship);
	}
}

function publisher_relationships_check_relationship(array $relationship)
{
	$source_entity = \Drupal\publisher\Entity::loadByUUID($relationship['source_uuid'],
		$relationship['source_type']);
	$destination_entity = \Drupal\publisher\Entity::loadByUUID($relationship['destination_uuid'],
		$relationship['destination_type']);

	if (!$source_entity || !$destination_entity) {
		publisher_relationships_finish_relationship($relationship, false);
	}

	if ($relationship['source_vuuid']) {
		$source_revision_ids = entity_get_id_by_uuid($relationship['source_type'],
			array($relationship['source_vuuid']), true);
		$source_revision_id = count($source_revision_ids) > 0 ? reset($source_revision_ids) : null;
		if (!$source_revision_id || !$source_entity->setRevision($source_revision_id)) {
			publisher_relationships_finish_relationship($relationship, false);
			return;
		}
	}

	if ($relationship['destination_vuuid']) {
		$destination_revision_ids = entity_get_id_by_uuid($relationship['destination_type'],
			array($relationship['destination_vuuid']), true);
		$destination_revision_id = count($destination_revision_ids) > 0 ?
			reset($destination_revision_ids) : null;
		if (!$destination_revision_id || !$destination_entity->setRevision($destination_revision_id)) {
			publisher_relationships_finish_relationship($relationship, false);
			return;
		}
	}

	$handler = \Drupal\publisher\Dependencies\DefinitionHandlers\DefinitionHandlerRegistry::getRelationshipHandler($relationship['relationship_handler']);
	publisher_relationships_finish_relationship($relationship, $handler->handleRelationship($relationship));
}

function publisher_relationships_finish_relationship(array $relationship, $success = true)
{
	if ($success) {
		publisher_relationships_remove($relationship);
	} else {
		$relationship['last_checked'] = REQUEST_TIME;
		publisher_relationships_save($relationship);
	}
}
