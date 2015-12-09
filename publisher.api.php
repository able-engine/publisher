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

/**
 * This hook allows other modules to alter entity needs before they are sent over to
 * the remote. The remote is passed as context.
 *
 * @param array                    $entity_need_info The entity need info, as created in
 *                                                   the execute function of the
 *                                                   BeginOperation class.
 * @param \Drupal\publisher\Remote $remote           The remote the entity is being sent to.
 */
function hook_publisher_entity_need_alter(array &$entity_need_info, \Drupal\publisher\Remote $remote)
{
	if ($remote->name == 'Test Remote') {
		$entity_need_info['revisions_payload'][0]['test'] = 'test2';
	}

	// You can also tell publisher to not send the entity by setting the $entity_need_info to
	// something that would equate to an empty value, like an empty array (or false, or an empty
	// string, etc).
	$entity_need_info = array();
}

/**
 * This hook allows other modules to alter entities before they are imported into the
 * current site. The remote is passed as context.
 *
 * @param array                    $entity The entity info. Contains the entity_type, uuid, vuuid,
 *                                         and revision history.
 * @param \Drupal\publisher\Remote $remote The remote the entity is coming from.
 */
function hook_publisher_import_entity_alter(array &$entity, \Drupal\publisher\Remote $remote)
{
	if ($remote->name == 'Test Remote') {
		$entity['revisions'] = array();
	}
}

/**
 * This hook allows other modules to alter entity relationships before they are sent
 * over to the receiving server. The remote is passed as context.
 *
 * @param array                    $relationships The list of relationships to be sent over to the
 *                                                receiving server.
 * @param \Drupal\publisher\Entity $entity        The entity being sent.
 * @param \Drupal\publisher\Remote $remote        The remote the entity is going to.
 */
function hook_publisher_relationships_alter(array &$relationships, \Drupal\publisher\Entity $entity, \Drupal\publisher\Remote $remote)
{
	// Alter the relationships.
}

/**
 * Allows other modules to specify a bundle map, grouped by entity type.
 *
 * @return array
 */
function hook_publisher_bundle_maps()
{
	/*
	 * For example, let's assume you want to map the content type 'page' to the
	 * content type 'article' on the receiving server. You would do something
	 * like this:
	 */

	return array(
		'node' => array(
			'page' => 'article',
		)
	);
}
