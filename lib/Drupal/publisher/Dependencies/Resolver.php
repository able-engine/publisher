<?php

namespace Drupal\publisher\Dependencies;

use Drupal\publisher\Dependencies\DefinitionHandlers\DefinitionHandlerBase;
use Drupal\publisher\Dependencies\DefinitionHandlers\DefinitionHandlerRegistry;
use Drupal\publisher\Dependencies\EntityHandlers\EntityHandlerBase;
use Drupal\publisher\Dependencies\EntityHandlers\EntityHandlerRegistry;
use Drupal\publisher\Entity;

class Resolver {

	/**
	 * The loaded entity.
	 * @var Entity|null
	 */
	protected $entity = null;

	/**
	 * The entity before modifications.
	 * @var Entity|null
	 */
	protected $base_entity = null;

	/**
	 * The entity definition with resolved references.
	 * @var Entity|null
	 */
	protected $resolved_definition = null;

	/**
	 * A flat list of dependencies.
	 * @var array
	 */
	protected $dependencies = array();

	/**
	 * A running list of all relationships to other entities.
	 * @var array
	 */
	protected $relationships = array();

	/**
	 * The metadata to send with the entity (generated from the
	 * entity handlers).
	 * @var array
	 */
	protected $metadata = array();

	/**
	 * An internal array of errors that happened while resolving the current entity.
	 * @var array
	 */
	protected $errors = array();

	/**
	 * A running list of handled dependencies to prevent unnecessary recursion.
	 * @var array
	 */
	protected static $handled_dependencies = array();

	public function __construct(Entity $entity, $auto_resolve = true, array $dependencies = array())
	{
		$this->entity = $entity;
		$this->base_entity = clone $entity;
		$this->dependencies = $dependencies;

		// Automatically resolve the dependencies on construct unless otherwise specified.
		if ($auto_resolve) {
			$this->resolveDependencies();
		}
	}

	public function resolveDependencies($recurse = true, $subset = false, $child = false, $subtype = false)
	{
		// Check to see if the resolved definition already exists in the cached list.
		$cached_resolutions = &drupal_static('publisher_cached_resolutions', array());
		$key = $this->base_entity->uuid() . '|' . $this->base_entity->type();
		if ($this->base_entity->supportsRevisions()) {
			$key .= '|' . $this->base_entity->vuuid();
		}
		$key .= $recurse ? '|recurse' : '';
		if (array_key_exists($key, $cached_resolutions) && $child === false && $subset === false) {
			$this->dependencies = array_replace_recursive($this->dependencies, $cached_resolutions[$key]['dependencies']);
			$this->relationships = array_merge($this->relationships, $cached_resolutions[$key]['relationships']);
			$this->resolved_definition = $cached_resolutions[$key]['resolved_definition'];
			return;
		}
		if (count($cached_resolutions) > 200) { // For memory consumption purposes.
			drupal_static_reset('publisher_cached_resolutions');
		}

		// Create a clone of the definition so we don't muck up the entity cache.
		$this->resolved_definition = ($subset !== false) ? $subset : clone $this->entity->definition;
		if (!is_object($this->resolved_definition)) {
			$this->resolved_definition = (object)$this->resolved_definition;
		}

		// Generate the list of handlers for each of the fields.
		$handlers = DefinitionHandlerRegistry::getFieldHandlers($this->entity, $this->resolved_definition, $subtype);
		foreach ($handlers as $field_name => $handler) {
			if (!isset($this->resolved_definition->{$field_name})) continue;

			foreach ($handler['handlers'] as $single_handler) {
				if (!($single_handler instanceof DefinitionHandlerBase)) continue;
				try {
					$single_handler->dependencies = &$this->dependencies;
					$single_handler->entity = &$this->entity;
					$single_handler->original_entity = $this->base_entity;
					if (property_exists($single_handler, 'relationships')) {
						$single_handler->relationships = &$this->relationships;
					}
					$single_handler->handleField($this->entity->type(),
						$handler['type'],
						$field_name,
						$this->resolved_definition->{$field_name});
				} catch (\Exception $ex) {
					$message = t('Error processing field "@fieldName" - "@message"',
						array(
							'@fieldName' => $field_name,
							'@message' => $ex->getMessage(),
						));
					\watchdog('publisher', $message, array(), WATCHDOG_WARNING);
					$this->errors[] = $ex;
				}
			}
		}

		// Now, recursion.
		if ($recurse) {
			foreach ($this->dependencies as $identifier => $dependency) {

				// If the dependency has already been handled, skip it.
				if (array_key_exists($identifier, self::$handled_dependencies)) {
					continue;
				}

				// Add the dependency to the list of handled ones.
				self::$handled_dependencies[$identifier] = $dependency;

				// Load the entity and get its dependencies.
				$entity = Entity::loadByUUID($dependency['uuid'], $dependency['entity_type']);

				// If for some reason the entity fails, we need to just skip it.
				if (!$entity) {
					continue;
				}

				// Make sure the entity is not the current entity to prevent infinite loop.
				if ($entity->uuid() == $this->entity->uuid()) {
					continue;
				}

				$resolver = new self($entity, false, $this->dependencies);
				$resolver->relationships = &$this->relationships;
				$resolver->metadata = &$this->metadata;
				$resolver->resolveDependencies(true, false, true);
				$this->dependencies = $resolver->dependencies();

			}
		}

		// Add a dependency for the entity itself if it doesn't already exist.
		if (!array_key_exists($this->base_entity->uuid(), $this->dependencies) ||
			($this->base_entity->supportsRevisions() &&
			!empty($this->dependencies[$this->base_entity->uuid()]['original_revision']) &&
			$this->base_entity->revision() > $this->dependencies[$this->base_entity->uuid()]['original_revision'])) {
			$this->dependencies[$this->base_entity->uuid()] = HandlerBase::createReferenceDefinition($this->base_entity);
		}

		// Pass the entity through the entity handlers.
		if (!array_key_exists($this->base_entity->uuid(), $this->metadata) && $recurse) {
			$this->metadata[$this->base_entity->uuid()] = array();
			foreach (EntityHandlerRegistry::getEntityHandlers($this->base_entity) as $handler) {
				if (!($handler instanceof EntityHandlerBase)) continue;
				$handler->entity = &$this->entity;
				$handler->original_entity = $this->base_entity;
				$handler->dependencies = &$this->dependencies;
				$handler->handleEntity($this->metadata[$this->base_entity->uuid()]);
			}
		}

		// Update the cache record.
		$cached_resolutions[$key] = array(
			'dependencies' => $this->dependencies,
			'relationships' => $this->relationships,
			'resolved_definition' => $this->resolved_definition,
		);

		if (!$child) {

			$dependency_dependencies = &drupal_static('publisher_dependency_dependencies', array());
			foreach ($dependency_dependencies as $source => $dependencies) {
				if (array_key_exists($source, $this->dependencies)) {
					if (!array_key_exists('dependencies', $this->dependencies[$source]) ||
						!is_array($this->dependencies[$source]['dependencies'])) {
						$this->dependencies[$source]['dependencies'] = array();
					}
					$this->dependencies[$source]['dependencies'] = array_replace_recursive(
						$this->dependencies[$source]['dependencies'],
						$dependencies);
					$this->dependencies[$source]['dependencies'] =
						array_unique($this->dependencies[$source]['dependencies']);
				}
			}

			// Update the relationships.
			$this->updateRelationships();

			// Loop through all the dependencies and perform cleanup tasks.
			foreach ($this->dependencies as $dependency_key => &$dependency) {

				// Cleanup the sources.
				if (array_key_exists('sources', $dependency) && is_array($dependency['sources'])) {
					$dependency['sources'] = array_unique($dependency['sources']);
				}

			}

			// Loop through again for the required stuff.
			foreach ($this->dependencies as $dependency_key => &$dependency) {

				// Create the 'required' key.
				if (array_key_exists('has_relationship', $dependency) && $dependency['has_relationship']) {
					$dependency['required'] = false;
					continue;
				}

				$this->dependencies[$dependency_key]['required'] = true;
				if (array_key_exists('sources', $this->dependencies[$dependency_key]) &&
					is_array($this->dependencies[$dependency_key]['sources']) &&
					count($this->dependencies[$dependency_key]['sources']) > 0) {

					// Mark dependencies as not required only if all of their parent paths
					// are marked as having relationships.
					$sources = array();
					foreach ($this->dependencies[$dependency_key]['sources'] as $source_uuid) {
						$sources[] = $this->dependencies[$source_uuid];
					}
					$paths = $this->getAllParentTrails($sources);

					$paths_with_notrequired = 0;
					foreach ($paths as $path) {
						foreach ($path as $item) {
							if (!array_key_exists('required', $this->dependencies[$item]) ||
								!$this->dependencies[$item]['required']) {
								$paths_with_notrequired++;
								break;
							}
						}
					}

					if ($paths_with_notrequired == count($paths)) {
						$this->dependencies[$dependency_key]['required'] = false;
					}

				} else {

					// If the dependency doesn't have any sources, it is not
					// required.
					$this->dependencies[$dependency_key]['required'] = false;

				}

			}

			// Now, loop through the dependencies again and fill in their
			// 'required_if' values. If a dependency is marked as not required,
			// go through its trails and find the lowest parent that is marked
			// as having a relationship and add it to the 'required_if' list
			// if it isn't already there.
			foreach ($this->dependencies as $dependency_key => &$dependency) {

				$dependency['required_if'] = array();

				// If the dependency is marked as required already, is a relationship,
				// or doesn't have any sources, skip it.
				if ($dependency['required']) continue;
				if (!array_key_exists('sources', $dependency) || !is_array($dependency['sources'])) continue;

				// Loop through its parent trails to find the non-required items.
				$required_if = array();
				$sources = array();
				foreach ($dependency['sources'] as $source_uuid) {

					// If the source UUID is the owner of the relationship, don't add it.
					$skip = false;
					foreach ($this->relationships as $relationship) {
						if ($relationship['source_uuid'] == $source_uuid &&
							$relationship['destination_uuid'] == $dependency['uuid']) {
							$skip = true;
							break;
						}
					}
					if ($skip) continue;

					$sources[] = $this->dependencies[$source_uuid];

				}
				$paths = $this->getAllParentTrails($sources);

				foreach ($paths as $path) {
					$found = false;
					foreach ($path as $index => $item) {
						if (array_key_exists('has_relationship', $this->dependencies[$item]) &&
							$this->dependencies[$item]['has_relationship']) {
							$found = true;
							$required_if[] = array_slice($path, 0, $index + 1);
							break;
						}
					}
					if (!$found) {
						if (count($path) === 1) {
							$required_if[] = $path;
						} else {
							for ($i = 1; $i < count($path); $i++) {
								$required_if[] = array_slice($path, 0, $i);
							}
						}
					}
				}

				// Add it to the dependency.
				$required_if_entities = array();
				foreach ($required_if as $path) {
					$required_if_entities[] = end($path);
				}
				$dependency['required_if'] = array_unique($required_if_entities);

			}

			// Reset the dependency dependencies cache.
			drupal_static_reset('publisher_dependency_dependencies');

			// Finally, perform the topological sort on the dependencies.
			$this->topologicalSortDependencies();

		}
	}

	protected function getAllParentTrails(array $sources, array $trail = array())
	{
		$paths = array();
		foreach ($sources as $next) {
			if (in_array($next['uuid'], $trail)) {
				$paths[] = $trail;
				continue; // Add the trail to the paths and move on.
			}
			$new_trail = array_merge($trail, array($next['uuid']));
			$source_parents = $this->getSourceParents($next);
			if (count($source_parents) > 0) {
				$new_trails = $this->getAllParentTrails($source_parents, $new_trail);
			} else {
				$new_trails = array($new_trail);
			}
			$paths = array_merge($paths, $new_trails);
		}

		return $paths;
	}

	protected function getSourceParents(array $source)
	{
		if (!array_key_exists('sources', $source)) return array();
		$parents = array();
		foreach ($source['sources'] as $parent) {
			if (array_key_exists($parent, $this->dependencies)) {
				$parents[] = $this->dependencies[$parent];
			}
		}

		return $parents;
	}

	protected function topologicalSortDependencies()
	{
		// Get the list of required dependencies.
		$required_dependencies = array();
		foreach ($this->dependencies as $key => $dependency) {
			if (!array_key_exists('has_relationship', $dependency) ||
				$dependency['has_relationship'] === false) {
				$required_dependencies[$key] = $dependency;
			}
		}

		// Generate the node and edges arrays.
		$nodes = array();
		$edges = array();

		foreach ($required_dependencies as $dependency_key => $dependency) {
			if (!array_key_exists('sources', $dependency)) continue;
			foreach ($dependency['sources'] as $source) {
				if ($dependency_key == $source) continue; // Don't add dependencies that point to each other.
				$edges[] = array($dependency_key, $source);
				if (!in_array($source, $nodes)) $nodes[] = $source;
			}
			if (!in_array($dependency_key, $nodes)) $nodes[] = $dependency_key;
		}

		$sorted = null;
		try {
			$sorted = publisher_topological_sort($nodes, $edges);
		} catch (\TopologicalSortException $e) {
			drupal_set_message('Circular reference detected. Check the recent log messages for details about where the reference could be coming from. Please contact an administrator if you need support.', 'error');
			watchdog('publisher', 'Circular Reference Data - Node: <pre>' . var_export($e->getNode(), true) . '</pre><br />Nodes: <pre>' . var_export($e->getNodes(), true) . '</pre><br />Edges: <pre>' . var_export($e->getEdges(), true) . '</pre>', array(),
				WATCHDOG_ERROR);
			throw new ResolverException('Circular reference detected.');
		}
		if ($sorted === null) { // One final catch in case something else happens...
			drupal_set_message('Circular reference detected.', 'error');
			throw new ResolverException('Circular reference detected.');
		}

		// Build the new list of dependencies.
		$dependencies = array();
		foreach ($sorted as $dependency_key) {
			$dependencies[$dependency_key] = $this->dependencies[$dependency_key];
			unset($this->dependencies[$dependency_key]);
		}
		foreach ($this->dependencies as $dependency_key => $dependency) {
			$dependencies[$dependency_key] = $dependency;
		}
		$this->dependencies = $dependencies;
	}

	protected function updateRelationships()
	{
		foreach ($this->relationships as $relationship) {
			if (array_key_exists($relationship['destination_uuid'], $this->dependencies)) {
				$this->dependencies[$relationship['destination_uuid']]['has_relationship'] = true;
			}
		}
	}

	public function dependencies()
	{
		return $this->dependencies;
	}

	public function relationships()
	{
		return $this->relationships;
	}

	public function resolvedDefinition()
	{
		return $this->resolved_definition;
	}

	public function metadata()
	{
		return $this->metadata;
	}

	public function errors()
	{
		return count($this->errors) > 0 ? $this->errors : false;
	}

}

class ResolverException extends \Exception {}
