<?php

/**
 * This hook fires once an entity has been successfully sent to
 * a remote. The hook does not fire if there was an error
 * sending the entity to the remote.
 *
 * This hook gives modules an opportunity to act on an entity
 * being sent to the specified remote successfully.
 *
 * @param \Drupal\publisher\Entity $entity The entity that was sent to
 *                                         the remote.
 * @param \Drupal\publisher\Remote $remote The remote the entity was
 *                                         sent to.
 */
function hook_publisher_entity_sent(\Drupal\publisher\Entity $entity, \Drupal\publisher\Remote $remote)
{
	// Act on the $entity being sent to the $remote.
}

/**
 * This hook fires once an entity has been successfully imported
 * to the current site. The hook does not fire if there was an
 * error receiving or importing the entity.
 *
 * This hook gives modules an opportunity to act on an entity
 * being received by the current server successfully.
 *
 * @param \Drupal\publisher\Entity $entity The entity that was imported.
 * @param \Drupal\publisher\Remote $remote The remote the entity came from.
 */
function hook_publisher_entity_received(\Drupal\publisher\Entity $entity, \Drupal\publisher\Remote $remote)
{
	// Act on the $entity being received from the $remote.
}

/**
 * This hook fires when Publisher is building the list of handlers. This
 * is how third party modules are able to add individual definition handlers
 * into Publisher.
 *
 * These handlers are strictly for modifying and converting properties on
 * the entity itself into something that can be moved from one server to
 * the other.
 *
 * To add a new handler, simply append an instance of it to the $handlers
 * array passed to this function.
 *
 * @param array $handlers The current list of handlers supported by Publisher.
 */
function hook_publisher_definition_handlers_alter(&$handlers)
{
	$handlers[] = new \Drupal\publisher\Dependencies\WorkbenchModerationHandler();
}

/**
 * This hook is very similar to hook_publisher_handlers(), except it is fired
 * for the entity handlers.
 *
 * Entity handlers are like regular handlers, except they handle the entire
 * entity and support the metadata feature. If you want to tie specific metadata
 * to an entity when it is being sent, use entity handlers instead of definition
 * handlers (hook_publisher_definition_handlers()).
 *
 * To add a new handler, simply append an instance of it to the $handlers
 * array passed to this function.
 *
 * @param array $handlers The current list of handlers supported by Publisher.
 */
function hook_publisher_entity_handlers_alter(&$handlers)
{
	// $handlers[] = new MyCustomEntityHandler();
}

/**
 * When comparing revisions of certain entity types (nodes, primarily), there are some
 * properties on the entity that do not change across revisions and are therefore left
 * out in standard entity comparisons. Modules that implement custom properties on the
 * node that do not support revisions need to specify that using this hook.
 *
 * The properties mentioned in this hook will always be sent to the remote server (assuming
 * they exist on the entity being moved).
 *
 * @return array An array, keyed by entity type, of arrays that contain the properties
 *               to send to the remote each time. A key of 'all' means that the property
 *               applies to all entity types.
 */
function hook_publisher_unchanging_properties()
{
	return array(
		'node' => array('path', 'machine_name'),
		'taxonomy_term' => array('path'),
	);
}
