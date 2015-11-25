<?php

namespace Drupal\publisher;

class TransactionSession {

	/**
	 * The instance of the class.
	 * @var static
	 */
	protected static $instance = null;

	/**
	 * The original destination URL.
	 * @var string
	 */
	protected $original_destination = '';

	/**
	 * The original messages to be displayed on the original page.
	 * @var array
	 */
	protected $original_messages = array();

	/**
	 * The running list of root-level entities that are being deployed.
	 * @var array
	 */
	protected $root_entities = array();

	/**
	 * The running list of entities that are being deployed.
	 * @var array
	 */
	protected $entities = array();

	/**
	 * Indicates whether or not the drupal_goto() from the node save
	 * should be overridden yet.
	 * @var bool
	 */
	protected $ready_to_override = false;

	/**
	 * The remote the entities in the session are being sent to.
	 * @var Remote
	 */
	protected $remote = null;

	/**
	 * The list of entity UUIDs to send to the remote.
	 * @var array
	 */
	protected $selected_entities = array();

	/**
	 * A place to store the relationships before sending them to
	 * the remote.
	 * @var array
	 */
	protected $relationships = array();

	/**
	 * A place to store the metadata before sending their respective
	 * entities to the remote.
	 * @var array
	 */
	protected $metadata = array();

	/**
	 * Gets the current instance of the class.
	 *
	 * @return TransactionSession
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Completes the current transaction session.
	 *
	 * @param bool $redirect Whether or not to finish the redirection.
	 */
	public static function complete($redirect = true)
	{
		$transaction = self::getFromSession();
		$original_destination = $transaction->getOriginalDestination();

		if (drupal_session_started()) {
			unset($_SESSION['publisher_transaction_session']);
		}
		self::$instance = null;

		// Add the messages back.
		foreach ($transaction->getOriginalMessages() as $type => $messages) {
			foreach ($messages as $message) {
				drupal_set_message($message, $type);
			}
		}

		// Finally, redirect to the original page.
		if ($redirect === true) {
			drupal_goto($original_destination);
		}
	}

	/**
	 * Gets the current transaction from the session, returning false if
	 * one does not exist.
	 *
	 * @return bool|TransactionSession
	 */
	public static function getFromSession()
	{
		drupal_session_start();
		if (!array_key_exists('publisher_transaction_session', $_SESSION)) return false;
		if (!($_SESSION['publisher_transaction_session'] instanceof TransactionSession))
			return false;

		self::$instance = $_SESSION['publisher_transaction_session'];
		return self::getInstance();
	}

	/**
	 * Gets whether or not the current transaction is already in progress.
	 *
	 * @return bool
	 */
	public function isInProgress()
	{
		return !empty($this->entities);
	}

	/**
	 * Gets the original destination path for the current transaction session.
	 *
	 * @return string
	 */
	public function getOriginalDestination()
	{
		return $this->original_destination;
	}

	/**
	 * Gets the original array of messages that were being sent to the
	 * original destination.
	 *
	 * @return array
	 */
	public function getOriginalMessages()
	{
		return $this->original_messages;
	}

	/**
	 * Gets the remote the entities in this session are being sent to.
	 *
	 * @return Remote
	 */
	public function getRemote()
	{
		return $this->remote;
	}

	/**
	 * Sets the remote to send the entities in this session to.
	 *
	 * @param Remote $remote The remote to send the entities in this session to.
	 *
	 * @throws \Exception
	 */
	public function setRemote(Remote $remote)
	{
		if (count($this->root_entities) > 0) {
			throw new \Exception('Cannot set the remote after adding root entities.');
		}
		$this->remote = $remote;
	}

	/**
	 * Adds an entity to the running list of root-level entities being deployed.
	 *
	 * @param Entity $entity  The entity being deployed.
	 * @param array  $options The options for the entity.
	 *
	 * @return bool Whether or not the entity was added.
	 */
	public function addRootEntity(Entity $entity, array $options = array())
	{
		// Do not allow adding more than 30 entities at a time.
		if (count($this->root_entities) >= 30) {
			return false;
		}

		$this->root_entities[$entity->uuid()] = array(
			'entity' => $entity,
			'options' => $options,
		);

		return true;
	}

	/**
	 * Adds an entity to the running list of regular entities being deployed.
	 *
	 * @param string $root_entity_uuid The identifier for the root entity.
	 * @param string $entity_uuid      The identifier for the entity.
	 * @param array  $entity           Information about the entity being deployed.
	 */
	public function addEntity($root_entity_uuid, $entity_uuid, array $entity)
	{
		if (!array_key_exists($root_entity_uuid, $this->entities)) {
			$this->entities[$root_entity_uuid] = array();
		}
		$this->entities[$root_entity_uuid][$entity_uuid] = $entity;
	}

	/**
	 * Adds multiple entities to the running list of root-level entities being deployed.
	 *
	 * @param array $entities An array of entity objects.
	 */
	public function addRootEntities(array $entities)
	{
		foreach ($entities as $entity) {
			/** @var Entity $entity */
			$this->addRootEntity($entity);
		}
	}

	/**
	 * Adds multiple entities to the running list of regular entities being deployed.
	 *
	 * @param string $root_entity_uuid The identifier for the root entity to add these
	 *                                 entities to.
	 * @param array  $entities         An array of entity information arrays, keyed by entity
	 *                                 UUID.
	 */
	public function addEntities($root_entity_uuid, array $entities)
	{
		foreach ($entities as $uuid => $entity) {
			$this->addEntity($root_entity_uuid, $uuid, $entity);
		}
	}

	/**
	 * Sets the list of selected entities to the passed array.
	 *
	 * @param array $selected_entities The new list of selected entities. Each
	 *                                 item in the array should be a UUID of
	 *                                 an entity.
	 */
	public function setSelectedEntities(array $selected_entities = array())
	{
		$this->selected_entities = $selected_entities;
	}

	/**
	 * Sets the relationships in the transaction session.
	 *
	 * @param array $relationships
	 */
	public function setRelationships(array $relationships = array())
	{
		$this->relationships = $relationships;
	}

	/**
	 * Sets the metadata in the transaction session.
	 *
	 * @param array $metadata
	 */
	public function setMetadata(array $metadata = array())
	{
		$this->metadata = $metadata;
	}

	/**
	 * Mark the specified entity as complete.
	 *
	 * @param Entity $entity
	 */
	public function completeRootEntity(Entity $entity)
	{
		unset($this->root_entities[$entity->uuid()]);
		unseT($this->entities[$entity->uuid()]);
		$this->completeEntity($entity);
	}

	/**
	 * Mark the specified entity as complete.
	 *
	 * @param Entity $entity
	 */
	public function completeEntity(Entity $entity)
	{
		$uuid = $entity->uuid();
		foreach ($this->entities as $root_entity_uuid => $entities) {
			unset($this->entities[$root_entity_uuid][$uuid]);
		}
	}

	/**
	 * Gets the running list of root-level entity objects being deployed.
	 *
	 * @return array
	 */
	public function getRootEntities()
	{
		return $this->root_entities;
	}

	/**
	 * Gets the running list of regular entities being deployed.
	 *
	 * @return array
	 */
	public function getEntities($root_entity_uuid = false)
	{
		if ($root_entity_uuid === false ||
			!array_key_exists($root_entity_uuid, $this->entities)) {
			return array();
		} else {
			return $this->entities[$root_entity_uuid];
		}
	}

	/**
	 * Gets all of the current entities. Root entities are only included
	 * if they can be sent.
	 *
	 * @return array
	 */
	public function getAllEntities()
	{
		$entities = array();
		foreach ($this->entities as $child_entities) {
			$entities = array_replace($entities, $child_entities);
		}

		return $entities;
	}

	/**
	 * Gets the current list of selected entities, returning their
	 * complete dependency objects instead of just the UUID.
	 *
	 * @return array The array of selected entities.
	 */
	public function getSelectedEntities()
	{
		$entities = $this->getAllEntities();
		$selected_entities = array();
		foreach ($this->selected_entities as $uuid) {
			if (array_key_exists($uuid, $entities)) {
				$selected_entities[$uuid] = $entities[$uuid];
			}
		}

		return $selected_entities;
	}

	/**
	 * Gets the list of relationships from the transaction session.
	 *
	 * @return array
	 */
	public function getRelationships()
	{
		return $this->relationships;
	}

	/**
	 * Gets the list of metadata (keyed by entity UUID) from the transaction
	 * session.
	 *
	 * @param mixed $entity_uuid The UUID of the entity to get metadata for. False
	 *                           to get all metadata.
	 *
	 * @return array
	 */
	public function getMetadata($entity_uuid = false)
	{
		if ($entity_uuid && array_key_exists($entity_uuid, $this->metadata)) {
			return array($entity_uuid => $this->metadata[$entity_uuid]);
		} elseif ($entity_uuid === false) {
			return $this->metadata;
		} else {
			return false;
		}
	}

	/**
	 * Stores the current transaction session to the Drupal session.
	 */
	public function storeToSession()
	{
		if (!drupal_session_started()) {
			drupal_session_start();
		}

		$_SESSION['publisher_transaction_session'] = $this;
	}

	/**
	 * Marks the current transaction session as ready to override
	 * the drupal_goto() on the node submit page. The next drupal_goto()
	 * will be overridden and redirected to publisher.
	 */
	public function readyToOverride()
	{
		$this->ready_to_override = true;
	}

	/**
	 * Gets whether or not the current transaction session is ready to
	 * override the drupal_goto().
	 *
	 * @return bool
	 */
	public function isReadyToOverride()
	{
		return $this->ready_to_override;
	}

	/**
	 * Overrides the Drupal goto.
	 *
	 * @param string $path The original path the goto was going to.
	 */
	public function overrideGoto(&$path)
	{
		// Set the original destination and messages.
		$this->original_destination = $path;
		$this->original_messages = publisher_get_messages();

		$this->ready_to_override = false;
		$path = 'publisher/begin';

		// Store the transaction session back to the session.
		$this->storeToSession();
	}

}
