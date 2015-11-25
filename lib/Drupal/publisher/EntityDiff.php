<?php

namespace Drupal\publisher;


use AbleCore\Debug;
use Drupal\publisher\Dependencies\Resolver;
use Drupal\publisher\Preparers\PreparerRegistry;

class EntityDiff {

	/**
	 * The entity being diff'd.
	 * @var Entity
	 */
	protected $entity;

	/**
	 * The old revision ID.
	 * @var int
	 */
	protected $old_revision_id = -1;

	/**
	 * The new revision ID.
	 * @var int
	 */
	protected $new_revision_id = -1;

	/**
	 * The table to use for entity revisions.
	 * @var string
	 */
	protected $revisions_table = '';

	/**
	 * The standard key mappings for the entity.
	 * @var array
	 */
	protected $entity_keys = array();

	public static function diffRevisionUUIDs(Entity $entity, $revision_a, $revision_b)
	{
		$revision_a = self::prepareRevisionArgument($revision_a);
		$revision_b = self::prepareRevisionArgument($revision_b);

		$old_revision_ids = entity_get_id_by_uuid($entity->type(), array($revision_a), true);
		$old_revision_id = count($old_revision_ids) > 0 ? reset($old_revision_ids) : null;

		$new_revision_ids = entity_get_id_by_uuid($entity->type(), array($revision_b), true);
		$new_revision_id = count($new_revision_ids) > 0 ? reset($new_revision_ids) : null;

		return new self($entity, $old_revision_id, $new_revision_id);
	}

	protected static function prepareRevisionArgument($argument)
	{
		if (strpos($argument, 'norevision') === 0) {
			$segments = explode('|', $argument);
			if (count($segments) >= 2) {
				return $segments[1];
			}
		}
		return $argument;
	}

	public function __construct(Entity $entity, $old_revision_id = null, $new_revision_id = null)
	{
		$this->entity = $entity;

		$entity_info = \entity_get_info($entity->type());
		if (array_key_exists('revision table', $entity_info)) {
			$this->revisions_table = $entity_info['revision table'];
		}
		if (array_key_exists('entity keys', $entity_info)) {
			$this->entity_keys = $entity_info['entity keys'];
		}

		// If we have a menu link, don't send every single revision. Because we don't need them.
		if ($this->entity->type() == 'menu_link') {
			$this->revisions_table = false;
		}

		$this->old_revision_id = $old_revision_id;
		$this->new_revision_id = $new_revision_id;
	}

	public function singleDiff($old_revision, Entity $new_revision)
	{
		// Get rid of extra indexes.
		if ($old_revision !== null) {
			$old_revision->cleanLoad();
			$new_revision->cleanLoad();
		} else {
			$new_revision->cleanNew();
		}

		// Run the entities through the resolver.
		if ($old_revision !== null) {
			$old_revision = clone $old_revision->definition;
		}
		$new_resolver = new Resolver($new_revision, false);
		$new_revision_object = clone $new_revision->definition;

		if ($old_revision !== null) {
			$old_revision_array = (array)$old_revision;
		} else {
			$old_revision_array = array();
		}
		$new_revision_array = (array)$new_revision_object;

		$additions = drupal_array_diff_assoc_recursive($new_revision_array, $old_revision_array);
		$deletions = $this->findToDelete($new_revision_array, $old_revision_array);

		// Pull in properties that don't support revisions on the node.
		$this->preserveUnchangingProperties($new_revision, $additions);

		// Resolve the additions.
		$new_resolver->resolveDependencies(false, $additions);
		$additions = $new_resolver->resolvedDefinition();

		return array(
			'additions' => $additions,
			'deletions' => $deletions,
		);
	}

	/**
	 * Copies items that are not associated with the node's revision history over
	 * to the new additions array to make sure they are saved every time.
	 *
	 * @param Entity $entity
	 * @param array  $additions
	 */
	protected function preserveUnchangingProperties(Entity $entity, array &$additions)
	{
		$properties = publisher_get_unchanging_properties($entity->bundle());

		foreach ($properties as $unchanging_property) {
			if (property_exists($entity->definition, $unchanging_property)) {
				$additions[$unchanging_property] = $entity->definition->{$unchanging_property};
			}
		}
	}

	protected function findToDelete($array1, $array2)
	{
		$deleted = array();
		foreach ($array2 as $key => $value) {
			if (!is_array($value)) continue;
			if (!array_key_exists($key, $array1)) {
				$deleted[$key] = null;
			} else {
				if (is_array($array1[$key]) && is_array($array2[$key])) {
					$to_delete = $this->findToDelete($array1[$key], $array2[$key]);
					if (count($to_delete) > 0) {
						$deleted[$key] = $this->findToDelete($array1[$key], $array2[$key]);
					}
				}
			}
		}
		return $deleted;
	}

	public function diff()
	{
		// Get the preparer registry.
		$preparer_registry = new PreparerRegistry();

		if (!$this->revisions_table) {

			// Prepare the entity.
			$preparer_registry->beforeSend($this->entity);
			$resolver = new Resolver($this->entity);
			return array(
				$this->entity->vuuid() => array(
					'additions' => $resolver->resolvedDefinition(),
					'deletions' => array()
				),
			);

		}

		// Get the revisions in between the two mentioned. If the old is blank, get the entire history.
		$revision_ids = $this->getRevisionHistory($this->old_revision_id, $this->new_revision_id);
		if ($revision_ids === false) {
			return false;
		}

		$payload = array();
		$before_id = ($this->old_revision_id) ? $this->old_revision_id : -1;
		foreach ($revision_ids as $current_id) {

			// Load the entities.
			if ($before_id === -1) {
				$old_entity = null;
			} else {
				$old_entity = clone $this->entity;
				$old_entity->setRevision($before_id);
			}
			$new_entity = clone $this->entity;
			$new_entity->setRevision($current_id);

			// Pass the entity through the preparers to add any additional field metadata.
			if ($old_entity !== null) {
				$preparer_registry->beforeSend($old_entity);
			}
			$preparer_registry->beforeSend($new_entity);

			$payload[$current_id . '|' . $new_entity->vuuid()] = $this->singleDiff($old_entity, $new_entity);

			// Update the before ID.
			$before_id = $current_id;

		}

		return $payload;
	}

	public function getRevisionHistory($start_revision = false, $end_revision = false)
	{
		// Make sure we have a revisions table.
		if (!$this->revisions_table) return false;

		// Get the keys we need.
		if (array_key_exists('id', $this->entity_keys))
			$id_key = $this->entity_keys['id'];
		else return false;
		if (array_key_exists('revision', $this->entity_keys))
			$revision_key = $this->entity_keys['revision'];
		else return false;

		// Reset the start revision if it has a false value.
		if (!$start_revision) {
			$start_revision = -1;
		}

		// Query the database between the start revision and end revision.
		$query = \db_select($this->revisions_table, 'revision');
		$query->addField('revision', $revision_key, 'revision');
		$query->orderBy('revision.' . $revision_key);
		$query->condition('revision.' . $revision_key, $start_revision, '>');
		if ($end_revision !== false) {
			$query->condition('revision.' . $revision_key, $end_revision, '<=');
		}
		$query->condition('revision.' . $id_key, $this->entity->id());
		$result = $query->execute()->fetchAll();

		$revisions = array();
		foreach ($result as $row) {
			$revisions[] = $row->revision;
		}

		return $revisions;
	}

}

class EntityDiffException extends \Exception {}
