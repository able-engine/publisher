<?php

namespace Drupal\publisher\EntityUUIDSync;

use AbleCore\Batch\OperationQueue;
use AbleCore\Debug;
use Drupal\publisher\Remote;

class Sync
{
	protected static $instance;
	const NAMESPACE_PREFIX = '\\Drupal\\publisher\\EntityUUIDSync\\';

	public static function getInstance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Gets an array of supported entity types (regardless of which entity types
	 * are available on the current website).
	 *
	 * @return array
	 */
	public function supportedTypes()
	{
		return array(
			'user' => 'User',
			'taxonomy_term' => 'TaxonomyTerm',
		);
	}

	/**
	 * Gets the supported entity types, only including those available on
	 * the current website. Returns an array of entity type information
	 * arrays, keyed by the entity type name.
	 *
	 * @return array
	 */
	public function supportedLocalTypes()
	{
		$entity_types = entity_get_info();
		$supported_types = array();
		foreach (array_keys($this->supportedTypes()) as $type) {
			if (!array_key_exists($type, $entity_types)) continue;
			$supported_types[$type] = $entity_types[$type];
		}

		return $supported_types;
	}

	/**
	 * Gets the supported local entity types, but in a format acceptable to place
	 * in an #options array for a select field.
	 *
	 * @return array
	 */
	public function supportedTypesOptions()
	{
		$result = array();
		foreach ($this->supportedLocalTypes() as $type => $entity) {
			$result[$type] = $entity['label'];
		}

		return $result;
	}

	/**
	 * Gets the sync handler based on the entity type.
	 *
	 * @param string $entity_type
	 *
	 * @return SyncHandlerContract
	 * @throws SyncHandlerException
	 */
	public function getSyncHandler($entity_type)
	{
		$supported_types = $this->supportedTypes();
		if (!array_key_exists($entity_type, $supported_types)) {
			throw new SyncHandlerException('The entity type ' . $entity_type . ' does not have a supported handler.');
		}
		$class = self::NAMESPACE_PREFIX . $supported_types[$entity_type] . 'Sync';
		if (!class_exists($class)) {
			throw new SyncHandlerException('The entity type ' . $entity_type . ' has a supported handler, but the class doesn\'t exist.');
		}

		$instance = new $class();
		if (!($instance instanceof SyncHandlerContract)) {
			throw new SyncHandlerException('The entity type ' . $entity_type . ' has a supported handler, but the class is invalid.');
		}

		return $instance;
	}

	public function buildMetadata($entity_type, Remote $remote)
	{
		$metadata = array();
		$metadata['entity_type'] = $entity_type;
		$metadata['remote'] = $remote;

		$handler = $this->getSyncHandler($entity_type);
		$metadata['entity_ids'] = $handler->getEntityIDs($remote);

		return $metadata;
	}

	public function addBatchOperations($entity_type, Remote $remote, OperationQueue &$queue)
	{
		// Add the operation to build the metadata.
		$queue->addOperation(new BuildMetadataOperation(), $entity_type);

		// Add the operations for each of the results.
		$handler = $this->getSyncHandler($entity_type);
		foreach ($handler->getEntityIDs($remote) as $entity_id) {
			$queue->addOperation(new SyncOperation(), $entity_type, $entity_id, $remote);
		}

		// Add the send operation.
		$queue->addOperation(new SendOperation(), $remote);
	}
}

class SyncHandlerException extends \Exception {}
