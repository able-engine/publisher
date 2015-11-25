<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\HandlerBase;

abstract class DefinitionHandlerBase extends HandlerBase {

	/**
	 * The current unresolved definition. Only accessible when unresolving
	 * dependencies.
	 * @var \stdClass
	 */
	public $unresolved_definition = null;

	/**
	 * Handles field type.
	 *
	 * Determines if this handler handles the specified field type.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $type        The field type.
	 * @param string $subtype     The subtype.
	 *
	 * @return bool
	 */
	public abstract function handlesFieldType($entity_type, $type, $subtype);

	/**
	 * Handle field.
	 *
	 * Handles the field.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $field_type  The field type.
	 * @param string $field_name  The name of the field.
	 * @param array  $value       The value of the field (passed by reference).
	 *
	 * @return void
	 */
	public abstract function handleField($entity_type, $field_type, $field_name, &$value);

	/**
	 * Unhandle field.
	 *
	 * Unhandles the field. Converts it from UUID references back to native IDs.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $field_type  The field type.
	 * @param string $field_name  The name of the field.
	 * @param array  $value       The value of the field (passed by reference).
	 *
	 * @return void
	 */
	public abstract function unhandleField($entity_type, $field_type, $field_name, &$value);

}
