<?php

namespace Drupal\publisher\Dependencies;

use Drupal\publisher\Entity;

abstract class HandlerBase {

	/**
	 * A running list of dependencies.
	 * @var array
	 */
	public $dependencies = array();

	/**
	 * The current entity being processed.
	 * @var Entity
	 */
	public $entity = null;

	/**
	 * The original entity, read only. Only accessible when resolving
	 * dependencies.
	 * @var Entity
	 */
	public $original_entity = null;

	/**
	 * Add dependency.
	 *
	 * Adds the specified entity to the dependencies list if it doesn't already exist.
	 *
	 * @param Entity  $entity           The entity to add.
	 * @param mixed   $source_override  If true, the source is the parent entity. If false,
	 *                                  no source is recorded. If an array, the array is used
	 *                                  as the source list.
	 * @param boolean $requires_latest  If true, requires that the entity (the latest version)
	 *                                  is present.
	 *
	 * @return array The new dependency;
	 */
	protected function addDependency(Entity $entity, $source_override = true, $requires_latest = false)
	{
		// If we're trying to add a dependency that points to the same entity, just don't
		// do anything.
		// if ($entity->uuid() == $this->entity->uuid()) return false;

		$dependency = null;
		if (array_key_exists($entity->uuid(), $this->dependencies)) {
			$dependency = $this->dependencies[$entity->uuid()];
		}

		if ($dependency === null || ($entity->supportsRevisions() && $entity->revision() > $dependency['original_revision'])) {
			$dependency = self::createReferenceDefinition($entity);
		}

		// Generate the list of sources.
		if ($source_override === true) {
			$sources = array($this->original_entity->uuid());
		} elseif (is_array($source_override)) {
			$sources = $source_override;
		} else {
			$sources = false;
		}

		// Run through the sources and add the dependency dependencies.
		if (is_array($sources) && count($sources) > 0) {
			$dependency_dependencies = &drupal_static('publisher_dependency_dependencies', array());
			foreach ($sources as $source) {
				if (!array_key_exists($source, $dependency_dependencies)) {
					$dependency_dependencies[$source] = array();
				}
				$dependency_dependencies[$source][] = $dependency['uuid'];
			}
		}

		if ($sources !== false) {
			if (!array_key_exists('sources', $dependency) || !is_array($dependency['sources'])) {
				$dependency['sources'] = array();
			}
			$dependency['sources'] = array_merge($dependency['sources'], $sources);
		}

		$dependency['requires_latest'] = array_key_exists('requires_latest', $dependency) ? $requires_latest || $dependency['requires_latest'] : $requires_latest;
		$dependency['has_relationship'] = false;

		$this->dependencies[$entity->uuid()] = $dependency;
		return $dependency;
	}

	/**
	 * Create reference definition.
	 *
	 * Creates an entity reference definition, storing the UUID, VUUID and entity
	 * type for the provided entity.
	 *
	 * @param Entity $entity       The entity to generate the definition for.
	 *
	 * @return array The generated definition.
	 */
	public static function createReferenceDefinition(Entity $entity)
	{
		$value = array();
		$value['uuid'] = $entity->uuid();
		$value['vuuid'] = $entity->vuuid();
		$value['entity_type'] = $entity->type();

		// Handle the bundle.
		$info = entity_get_info($entity->type());
		if (is_array($info['bundles']) && array_key_exists($entity->bundle(), $info['bundles'])) {
			$value['bundle'] = publisher_map_entity_bundle($entity->type(), $entity->bundle());
		} else {
			$value['bundle'] = false;
		}

		$value['original'] = $entity->id();
		$value['original_revision'] = $entity->revision();
		$revisions = Entity::getAllRevisions($entity->id(), $entity->type());
		if (is_array($revisions)) {
			$value['revisions'] = array();
			foreach ($revisions as $revision_ids) {
				$value['revisions'][] = $revision_ids['uuid'];
			}
		} else {
			$value['revisions'] = false;
		}

		return $value;
	}

	protected function createReferenceDefinitionWithDependencies(Entity $entity)
	{
		return self::createReferenceDefinition($entity);
	}

	public static function entityFromReferenceDefinition(array $definition, Entity $current_entity = null)
	{
		// Verify the reference definition.
		if (($message = self::verifyReferenceDefinition($definition)) !== true) {
			throw new InvalidReferenceDefinitionException($message);
		}

		// Make sure the current entity ID and the reference definition ID are different.
		if ($current_entity && $definition['uuid'] == $current_entity->uuid()) {
			return null;
		}

		// Try to load the entity.
		$entity = Entity::loadByUUID($definition['uuid'], $definition['entity_type']);
		if (!$entity) {
			throw new InvalidReferenceDefinitionException(t('The @type @uuid does not exist.', array(
				'@type' => $definition['entity_type'],
				'@uuid' => $definition['uuid'],
			)));
		}

		return $entity;
	}

	public static function verifyReferenceDefinition(array $definition)
	{
		if (!array_key_exists('uuid', $definition)) return 'No UUID available.';
		if (!array_key_exists('vuuid', $definition)) return 'No VUUID available.';
		if (!array_key_exists('entity_type', $definition)) return 'No entity type available.';
		return true;
	}

}

class BaseFieldHandlerException extends \Exception {}
class InvalidReferenceDefinitionException extends \Exception {}
