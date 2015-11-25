<?php

namespace Drupal\publisher\Dependencies;
use Drupal\publisher\Dependencies\DefinitionHandlers\DefinitionHandlerBase;
use Drupal\publisher\Dependencies\DefinitionHandlers\DefinitionHandlerRegistry;
use Drupal\publisher\Entity;

class Unresolver {

	/**
	 * The loaded entity.
	 * @var Entity|null
	 */
	protected $entity = null;

	/**
	 * An internal array representing the errors that occurred while unresolving the
	 * dependency.
	 * @var array
	 */
	protected $errors = array();

	/**
	 * The entity definition with unresolved references.
	 * @var Entity|null
	 */
	protected $unresolved_definition = null;

	public function __construct(Entity $entity, $auto_resolve = true)
	{
		$this->entity = $entity;

		// Automatically resolve the dependencies on construct unless otherwise specified.
		if ($auto_resolve) {
			$this->unresolveDependencies();
		}
	}

	public function unresolveDependencies($subset = false, $subtype = false)
	{
		// Create a clone of the definition so we don't muck up the entity cache.
		$this->unresolved_definition = $subset === false ? $this->entity->definition : $subset;
		if (!is_object($this->unresolved_definition)) {
			$this->unresolved_definition = (object)$this->unresolved_definition;
		}

		// Generate the list of handlers for each of the fields.
		$handlers = DefinitionHandlerRegistry::getFieldHandlers($this->entity, $this->unresolved_definition, $subtype);

		foreach ($handlers as $field_name => $handler) {
			if (!isset($this->unresolved_definition->{$field_name})) continue;

			foreach ($handler['handlers'] as $single_handler) {
				if (!($single_handler instanceof DefinitionHandlerBase)) continue;
				try {
					$single_handler->entity = &$this->entity;
					$single_handler->unresolved_definition = &$this->unresolved_definition;
					$single_handler->unhandleField($this->entity->type(),
						$handler['type'],
						$field_name,
						$this->unresolved_definition->{$field_name});
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
	}

	public function unresolvedDefinition()
	{
		return $this->unresolved_definition;
	}

	public function errors()
	{
		return count($this->errors) > 0 ? $this->errors : false;
	}

} 
