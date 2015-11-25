<?php

namespace Drupal\publisher\Dependencies;

use Drupal\publisher\Entity;

trait RelationshipHandler  {

	/**
	 * A running list of relationships.
	 * @var array
	 */
	public $relationships = array();

	/**
	 * Gets the factory-ready name of the current relationship handler.
	 *
	 * @return string
	 */
	public abstract function getRelationshipHandlerName();

	/**
	 * Given a relationship, checks to see if it can be fulfilled and fulfills it
	 * if it can.
	 *
	 * @param array $relationship The relationship to check.
	 *
	 * @return bool Whether or not the relationship was fulfilled.
	 */
	public abstract function handleRelationship(array $relationship);

	/**
	 * Adds a relationship to the running list of relationships.
	 *
	 * @param Entity $source            The source entity.
	 * @param Entity $destination       The destination entity that the source entity relates to.
	 * @param string $field_name        The field on the source entity that the relationship belongs to.
	 * @param int    $delta             The delta the relationship belongs to.
	 * @param array  $handler_arguments Any arguments to pass to the handler (like the field name).
	 * @param bool   $source_vuuid      The VUUID of the source (if false, defaults to the current
	 *                                  VUUID).
	 * @param bool   $destination_vuuid The VUUID of the destination (if false, the VUUID of the
	 *                                  destination isn't taken into account).
	 */
	protected function addRelationship(Entity $source, Entity $destination, $field_name, $delta = 0,
		array $handler_arguments = array(), $source_vuuid = false, $destination_vuuid = false)
	{
		// Get the relationship handler name.
		$handler = $this->getRelationshipHandlerName();

		// Generate the fields for the relationship.
		$record = array();
		$record['source_type'] = $source->type();
		$record['source_uuid'] = $source->uuid();
		$record['destination_type'] = $destination->type();
		$record['destination_uuid'] = $destination->uuid();
		$record['field_name'] = $field_name;
		$record['delta'] = $delta;

		// Add the source_vuuid column.
		if ($source->supportsRevisions()) {
			$record['source_vuuid'] = $source_vuuid === false ? $source->vuuid() : $source_vuuid;
		} else {
			$record['source_vuuid'] = false;
		}

		// Add the destination vuuid column.
		$record['destination_vuuid'] = $destination->supportsRevisions() ? $destination_vuuid : false;

		// Add the relationship handler and arguments.
		$record['relationship_handler'] = $handler;
		$record['relationship_arguments'] = $handler_arguments;

		// Check to see if the relationship already exists...
		foreach ($this->relationships as $relationship) {
			if ($record['source_type'] != $relationship['source_type']) continue;
			if ($record['source_uuid'] != $relationship['source_uuid']) continue;
			if ($record['source_vuuid'] != $relationship['source_vuuid']) continue;
			if ($record['destination_type'] != $relationship['destination_type']) continue;
			if ($record['destination_uuid'] != $relationship['destination_uuid']) continue;
			if ($record['destination_vuuid'] != $relationship['destination_vuuid']) continue;
			if ($record['relationship_handler'] != $relationship['relationship_handler']) continue;
			if (count(array_diff($record['relationship_arguments'], $relationship['relationship_arguments'])) !== 0) continue;
			return; // The relationship already exists...
		}

		// Add the relationship to the list.
		$this->relationships[] = $record;
	}

	/**
	 * Given a relationship, gets the stub entity for the source.
	 *
	 * @param array $relationship The relationship array.
	 *
	 * @return bool|mixed Either the stub entity or false on error.
	 */
	protected function getSourceStubEntity(array $relationship)
	{
		$field_name = $relationship['field_name'];

		$source_entity_ids = entity_get_id_by_uuid($relationship['source_type'], array($relationship['source_uuid']));
		$source_entity_id = count($source_entity_ids) > 0 ? reset($source_entity_ids) : null;

		$source_entity_vids = entity_get_id_by_uuid($relationship['source_type'], array($relationship['source_vuuid']), true);
		$source_entity_vid = count($source_entity_vids) > 0 ? reset($source_entity_vids) : false;

		if (!$source_entity_id) {
			return false;
		}

		// Get the bundle for the source entity.
		$source_entity_bundle = Entity::getBundleFromID($relationship['source_type'], $source_entity_id);
		if (!$source_entity_bundle) {
			return false;
		}

		// Get the stub entity for the source.
		$source_stub = Entity::getStub($relationship['source_type'], $source_entity_id, $source_entity_bundle,
			$source_entity_vid);

		// Load the existing field onto the entity.
		$field_instance_info = field_info_instance($relationship['source_type'], $field_name, $source_entity_bundle);
		if ($field_instance_info === false) {
			return false;
		}
		try {

			// First, load the revisions.
			field_attach_load_revision($relationship['source_type'], array($source_entity_id => $source_stub),
				array('field_id' => $field_instance_info['field_id']));

			// Second, get the original deltas for the revision.
			$deltas = db_select('field_revision_' . $field_name, 'fr')
				->fields('fr', array('language', 'delta'))
				->condition($source_entity_vid ? 'revision_id' : 'entity_id', $source_entity_vid ? $source_entity_vid : $source_entity_id)
				->condition('language', field_available_languages($relationship['source_type'], $field_instance_info), 'IN')
				->orderBy('delta')->execute();

			// Assign the deltas to their languages.
			$deltas_languages = array();
			foreach ($deltas as $delta) {
				if (!array_key_exists($delta->language, $deltas_languages)) {
					$deltas_languages[$delta->language] = array();
				}
				$deltas_languages[$delta->language][] = $delta->delta;
			}

			// Reset the deltas to their correct orders.
			$field_value = &$source_stub->$field_name;
			foreach ($deltas_languages as $language => $deltas) {
				$new_value = array();
				for ($i = 0; $i < count($field_value[$language]); $i++) {
					$new_value[$deltas[$i]] = $field_value[$language][$i];
				}
				$field_value[$language] = $new_value;
			}

		} catch (\Exception $ex) {
			// If an exception was thrown, that probably means the field doesn't
			// exist, so we should return false.
			return false;
		}

		return $source_stub;
	}

	/**
	 * Given a relationship, gets the destination entity ID.
	 *
	 * @param array $relationship The relationship.
	 *
	 * @return mixed|null Either the ID or null if it couldn't be found.
	 */
	protected function getDestinationEntityID(array $relationship)
	{
		$destination_entity_ids = entity_get_id_by_uuid($relationship['destination_type'],
			array($relationship['destination_uuid']));
		return count($destination_entity_ids) > 0 ? reset($destination_entity_ids) : null;
	}

	/**
	 * Given information about an entity, sets a field value.
	 *
	 * @param string $entity_type The entity type.
	 * @param object $stub_entity The stub entity.
	 * @param string $field_name  The name of the field to set.
	 * @param int    $delta       The delta (index) of the value to set.
	 * @param mixed  $value       The value itself.
	 */
	protected function setFieldValue($entity_type, $stub_entity, $field_name, $delta, $value)
	{
		$language = field_language($entity_type, $stub_entity, $field_name);
		$stub_entity->{$field_name}[$language][$delta] = $value;
		field_attach_update($entity_type, $stub_entity);
	}

	/**
	 * Sets the value of the source entity in the relationship
	 * to contain $key pointing to the destination entity ID.
	 *
	 * @param array  $relationship    The relationship.
	 * @param string $key             The key to set (target_id, tid, fid, etc.)
	 * @param array  $value_overrides An array of values to override on the field value.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function setValueWithKey(array $relationship, $key, array $value_overrides = array())
	{
		$destination_entity_id = $this->getDestinationEntityID($relationship);
		$stub_entity = $this->getSourceStubEntity($relationship);
		if (!$destination_entity_id || !$stub_entity) {
			return false;
		}

		// Get all revisions on the stub entity.
		list($source_entity_id, , ) = entity_extract_ids($relationship['source_type'], $stub_entity);
		if (Entity::typeSupportsRevisions($relationship['source_type'])) {
			$source_entity_revisions = Entity::getAllRevisions($source_entity_id, $relationship['source_type']);
			if (!is_array($source_entity_revisions)) {
				throw new \Exception('No revisions were found for the entity that should support revisions.');
			}
			$index = array_search($relationship['source_vuuid'], $source_entity_revisions);
			foreach (array_slice($source_entity_revisions, $index) as $revision_uuid) {
				$mock_relationship = $relationship;
				$mock_relationship['source_vuuid'] = $revision_uuid;
				$mock_stub_entity = $this->getSourceStubEntity($mock_relationship);
				$field_value = array($key => $destination_entity_id);
				$field_value = array_replace_recursive($field_value, $value_overrides);
				$this->setFieldValue($relationship['source_type'], $mock_stub_entity,
					$relationship['field_name'], $relationship['delta'],
					$field_value);
			}
		}

		// Finally, set the general value without revisions.
		$field_value = array($key => $destination_entity_id);
		$field_value = array_replace_recursive($field_value, $value_overrides);
		$this->setFieldValue($relationship['source_type'], $stub_entity,
			$relationship['field_name'], $relationship['delta'],
			$field_value);

		return true;
	}

	/**
	 * Given an entity reference definition, gets the original entity ID.
	 *
	 * @param array  $definition     The entity reference definition.
	 * @param Entity $current_entity The current entity (optional).
	 *
	 * @return bool|Entity|null
	 * @throws InvalidReferenceDefinitionException
	 */
	public static function entityFromReferenceDefinition(array $definition, Entity $current_entity = null)
	{
		// Verify the reference definition.
		if (($message = HandlerBase::verifyReferenceDefinition($definition)) !== true) {
			throw new InvalidReferenceDefinitionException($message);
		}

		// Make sure the current entity ID and the reference definition ID are different.
		if ($current_entity && $definition['uuid'] == $current_entity->uuid()) {
			return null;
		}

		// Try to load the entity.
		$entity = Entity::loadByUUID($definition['uuid'], $definition['entity_type']);
		if (!$entity) {

			// Check to see if there is a relationship for the entity.
			if ($current_entity) {
				$count = db_select('publisher_pending_relationships', 'r')
					->condition('source_type', $current_entity->type())
					->condition('source_uuid', $current_entity->uuid())
					->condition('destination_type', $definition['entity_type'])
					->condition('destination_uuid', $definition['uuid'])
					->countQuery()->execute()->fetchField();
				if ($count > 0) return false;
			}

			throw new InvalidReferenceDefinitionException(t('The @type @uuid does not exist.', array(
				'@type' => $definition['entity_type'],
				'@uuid' => $definition['uuid'],
			)));
		}

		return $entity;
	}

}
