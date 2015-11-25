<?php

namespace Drupal\publisher;

class Entity {

	/**
	 * The entity definition.
	 *
	 * @var object
	 */
	public $definition;

	/**
	 * The entity type.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Whether the entity is new or not.
	 *
	 * @var bool
	 */
	protected $is_new = false;

	// Getters.
	public function type()
	{
		return $this->type;
	}

	public function id($value = false)
	{
		return $this->getKey('id', $value);
	}

	public function bundle($value = false)
	{
		return $this->getKey('bundle', $value);
	}

	public function uuid($value = false)
	{
		return $this->getKey('uuid', $value);
	}

	public function revision($value = false)
	{
		return $this->getKey('revision', $value);
	}

	public function isNew($value = null)
	{
		if ($value !== null) {
			$this->is_new = $value;
		}
		return $this->is_new;
	}

	public function vuuid($value = false)
	{
		$vuuid = $this->getKey('revision uuid', $value);
		if ($vuuid !== false) {
			return $vuuid;
		} elseif (isset($this->definition->changed)) {
			return 'norevision|' . $this->uuid() . '|' . $this->definition->changed;
		} elseif (($modified = \entity_modified_last($this->type, $this->definition))) {
			return 'norevision|' . $this->uuid() . '|' . $modified;
		} else {
			return 'norevision|' . $this->uuid();
		}
	}

	public function language()
	{
		return $this->getKey('language');
	}

	public function fieldLanguage($field)
	{
		return field_language($this->type(), $this->definition, $field);
	}

	protected function getKey($key, $value = false)
	{
		$entity_info = \entity_get_info($this->type);
		if (isset($entity_info['entity keys'][$key])) {
			if (isset($this->definition->{$entity_info['entity keys'][$key]})) {
				if ($value !== false && $value !== null) {
					$this->definition->{$entity_info['entity keys'][$key]} = $value;
					return $value;
				} elseif ($value === null) {
					unset($this->definition->{$entity_info['entity keys'][$key]});
					return $value;
				}
				return $this->definition->{$entity_info['entity keys'][$key]};
			}
		}
		return false;
	}

	/**
	 * Gets the last date the entity was modified, or false when it cannot
	 * find a date.
	 *
	 * @return int The last date the entity was modified, falling back to
	 *             entity_modified_last()
	 */
	public function getModified()
	{
		$info = entity_get_info($this->type);
		if (array_key_exists('modified property name', $info) && !empty($this->definition->{$info['modified property name']})) {
			return $this->definition->{$info['modified property name']};
		} else {
			return entity_modified_last($this->type, $this->definition);
		}
	}

	public function supportsRevisions()
	{
		return self::typeSupportsRevisions($this->type);
	}

	public static function typeSupportsRevisions($entity_type)
	{
		$info = entity_get_info($entity_type);
		if (!array_key_exists('revision table', $info)) return false;
		if (!array_key_exists('revision', $info['entity keys'])) return false;
		if (!array_key_exists('revision uuid', $info['entity keys'])) return false;
		return true;
	}

	public function __construct($definition, $type)
	{
		$this->definition = $definition;
		$this->type = $type;
	}

	public function __clone()
	{
		$this->definition = clone $this->definition;
	}

	public static function load($entity_id, $type)
	{
		$latest_revision = self::getLatestRevisionID($entity_id, $type);
		if (!$latest_revision) {
			$definition = entity_load_single($type, $entity_id);
			return ($definition === false) ? false : new self($definition, $type);
		} else {
			$instance = new self(array(), $type);
			if (!$instance->setRevision($latest_revision)) return false;
			else return $instance;
		}
	}

	public static function exists($identifier, $type)
	{
		$id_type = is_numeric($identifier) ? 'id' : 'uuid';
		$info = entity_get_info($type);
		if (!$info) return false;

		$count = db_select($info['base table'], 'e')
			->condition($info['entity keys'][$id_type], $identifier)
			->countQuery()
			->execute()
			->fetchField();
		return $count > 0;
	}

	public static function convert($existing_entity)
	{
		$entity_types = \entity_get_info();
		foreach ($entity_types as $entity_type => $config) {
			if (array_key_exists('entity keys', $config) && array_key_exists('id', $config['entity keys'])) {
				if (isset($existing_entity->{$config['entity keys']['id']})) {
					$loaded_entity = self::load($existing_entity->{$config['entity keys']['id']}, $entity_type);
					if (!$loaded_entity) continue;
					$uuid_a = $existing_entity->{$config['entity keys']['uuid']};
					$uuid_b = $loaded_entity->uuid();
					if ($loaded_entity && $uuid_a == $uuid_b) {
						return $loaded_entity;
					}
				}
			}
		}
		return false;
	}

	public static function getLatestRevisionID($entity_id, $type, $reset = false)
	{
		$ids = &\drupal_static(__FUNCTION__, null, $reset);
		if (!isset($ids[$type][$entity_id])) {

			$entity_info = \entity_get_info($type);
			if (!array_key_exists('revision table', $entity_info)) return false;

			$query = \db_select($entity_info['revision table'], 'revision')
				->condition($entity_info['entity keys']['id'], $entity_id)
				->orderBy($entity_info['entity keys']['revision'], 'DESC')
				->range(0, 1);
			$revision_field = $query->addField('revision', $entity_info['entity keys']['revision']);
			$results = $query->execute()->fetchAll();
			if (count($results) <= 0) {
				return $ids[$type][$entity_id] = false;
			} else {
				return $ids[$type][$entity_id] = $results[0]->$revision_field;
			}

		}
		return $ids[$type][$entity_id];
	}

	public static function getAllRevisions($entity_id, $type, $reset = false)
	{
		$ids = &drupal_static(__FUNCTION__, array(), $reset);
		if (!isset($ids[$type][$entity_id])) {

			$entity_info = entity_get_info($type);
			if (!array_key_exists('revision table', $entity_info)) return false;

			$query = db_select($entity_info['revision table'], 'revision')
				->condition($entity_info['entity keys']['id'], $entity_id)
				->orderBy($entity_info['entity keys']['revision'], 'DESC');
			$revision_field = $query->addField('revision', $entity_info['entity keys']['revision']);
			$revision_uuid_field = $query->addField('revision', $entity_info['entity keys']['revision uuid']);
			$results = $query->execute();

			$ids[$type][$entity_id] = array();
			foreach ($results as $revision) {
				$ids[$type][$entity_id][] = array(
					'id' => $revision->$revision_field,
					'uuid' => $revision->$revision_uuid_field,
				);
			}

		}
		return $ids[$type][$entity_id];
	}

	public static function loadByUUID($entity_uuid, $type)
	{
		$entities = entity_get_id_by_uuid($type, array($entity_uuid));
		foreach ($entities as $entity_id) {
			return self::load($entity_id, $type);
		}

		return false;
	}

	/**
	 * Gets an entity stub with the ID, bundle and revision ID already
	 * filled in.
	 *
	 * @param string $type   The type of entity.
	 * @param int    $id     The ID of the entity.
	 * @param mixed  $bundle The name of the entity's bundle.
	 * @param mixed  $vid    The ID of the entity's revision.
	 *
	 * @return mixed Either the entity object on success, or null if the type could
	 *               not be found.
	 */
	public static function getStub($type, $id, $bundle = false, $vid = false)
	{
		$info = entity_get_info($type);
		if (!$info) return null;

		$entity_array = array();
		$entity_array[$info['entity keys']['id']] = $id;

		if (!empty($info['entity keys']['revision']) && $vid !== false) {
			$entity_array[$info['entity keys']['revision']] = $vid;
		}

		if (!empty($info['entity keys']['bundle']) && $bundle !== false) {
			$entity_array[$info['entity keys']['bundle']] = $bundle;
		}

		return (object)$entity_array;
	}

	/**
	 * Given an entity type and ID, gets the bundle.
	 *
	 * @param string $entity_type The type of entity.
	 * @param int    $entity_id   The ID of the entity.
	 *
	 * @return mixed|null
	 */
	public static function getBundleFromID($entity_type, $entity_id)
	{
		$info = entity_get_info($entity_type);
		if (!$info) return null;

		// If the entity type doesn't support bundles, return null.
		if (empty($info['entity keys']['bundle'])) return null;

		$query = db_select($info['base table'], 'e')
			->condition($info['entity keys']['id'], $entity_id);
		$query->addField('e', $info['entity keys']['bundle'], 'bundle');
		$query->range(0, 1);
		$bundles = $query->execute()->fetchCol();

		return count($bundles) > 0 ? reset($bundles) : null;
	}

	public function setRevision($revision_id)
	{
		$revision = \entity_revision_load($this->type(), $revision_id);
		if (!$revision) {
			return false;
		}
		$this->definition = $revision;

		return true;
	}

	/**
	 * Path
	 *
	 * Gets the path to the current entity.
	 *
	 * @return bool|string False if there was an error, else the path to the entity.
	 */
	public function path()
	{
		$url_params = entity_uri($this->type(), $this->definition);
		if (is_array($url_params) && array_key_exists('path', $url_params) && array_key_exists('options', $url_params))
			return url($url_params['path'], $url_params['options']);
		else return false;
	}

	public function cleanNew()
	{
		$this->id(null);
		$this->revision(null);

		// Find the path alias and unset the ID and source if they exist.
		if (isset($this->definition->path['pid']))
			unset($this->definition->path['pid']);
		if (isset($this->definition->path['source']))
			unset($this->definition->path['source']);
	}

	public function cleanLoad()
	{
		$this->revision(null);
	}

	public function save()
	{
		$this->is_new = false;
		return \entity_save($this->type, $this->definition);
	}

	public function delete()
	{
		return \entity_delete($this->type, $this->definition->id);
	}

}
