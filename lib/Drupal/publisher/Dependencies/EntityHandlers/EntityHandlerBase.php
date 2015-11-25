<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Dependencies\HandlerBase;
use Drupal\publisher\Entity;

abstract class EntityHandlerBase extends HandlerBase {

	/**
	 * Given an entity, determines whether or not the current handler is
	 * capable of handling that entity.
	 *
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public abstract function handlesEntity(Entity $entity);

	/**
	 * Given an entity, handle the entire entity itself (do not handle any of the
	 * fields on the entity). This function is passed an array of metadata values
	 * that are sent with the entity when it is sent over to the recipient server.
	 *
	 * @param array  $metadata The metadata currently associated with the entity.
	 */
	public function handleEntity(array &$metadata = array()) {}

	/**
	 * Given an entity, unhandle the entire entity itself (do not unhandle any of
	 * the fields on the entity). This function is passed an array of metadata that
	 * has been passed from the sending server.
	 *
	 * @param array $metadata The metadata sent from the source server.
	 */
	public function unhandleEntity(array $metadata = array()) {}

	/**
	 * This function is exactly like unhandleEntity(), except it is run for every revision
	 * that is imported on the receiving server.
	 *
	 * @param array $metadata The metadata sent from the source server.
	 * @see unhandleEntity()
	 */
	public function unhandleRevision(array $metadata = array()) {}

}
