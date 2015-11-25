<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\RelationshipHandler;
use Drupal\publisher\Dependencies\DefinitionHandlers\TextAreaHandlers\TextAreaFileReferenceHandler;
use Drupal\publisher\Dependencies\DefinitionHandlers\TextAreaHandlers\TextAreaLinkReferenceHandler;
use Drupal\publisher\Entity;

class DefinitionHandlerRegistry {

	protected static $handlers = null;

	public static function getHandlers()
	{
		if (self::$handlers !== null) return self::$handlers;
		self::$handlers = array();

		// Register handlers.
		self::$handlers[] = new TaxonomyReferenceHandler();
		self::$handlers[] = new UserHandler();
		self::$handlers[] = new EntityReferenceHandler();
		self::$handlers[] = new FileReferenceHandler();
		self::$handlers[] = new ChangedHandler();
		self::$handlers[] = new MenuLinkHandler();
		self::$handlers[] = new MenuLinkPathHandler();
		self::$handlers[] = new FileHandler();
		self::$handlers[] = new TextAreaFileReferenceHandler();
		self::$handlers[] = new TextAreaLinkReferenceHandler();
		self::$handlers[] = new IDHandler();

		if (module_exists('imagefield_crop'))
			self::$handlers[] = new ImageCropFieldHandler();

		if (module_exists('workbench_moderation'))
			self::$handlers[] = new WorkbenchModerationHandler();

		// Allow modules to modify this.
		drupal_alter('publisher_definition_handlers', self::$handlers);

		return self::$handlers;
	}

	public static function getFieldHandlers(Entity $entity, $definition = null, $subtype = false)
	{
		$instances = \field_info_instances($entity->type(), $entity->bundle());
		$handlers = array();

		$handled = array();

		foreach ($instances as $field_name => $instance) {
			$handled[] = $field_name;
			if ($handler = self::getFieldHandler($entity->type(), $field_name, $subtype)) {
				$handlers[$field_name] = $handler;
			}
		}

		// Check special fields on the entity.
		$keys = \entity_get_info($entity->type());
		if (array_key_exists('entity keys', $keys)) {
			foreach ($keys['entity keys'] as $standard => $key) {
				$handled[] = $key;
				if ($handler = self::getTypeHandler($entity->type(), $standard, $subtype)) {
					$handlers[$key] = $handler;
				}
			}
		}

		// Various other fields.
		foreach ($entity->definition as $key => $value) {
			if (array_search($key, $handled) !== false) continue;
			if ($handler = self::getTypeHandler($entity->type(), $key, $subtype)) {
				$handlers[$key] = $handler;
			}
		}

		// Get other fields on the definition.
		if (is_object($definition) || is_array($definition)) {
			foreach ($definition as $key => $value) {
				if (array_search($key, $handled) !== false) continue;
				if ($handler = self::getTypeHandler($entity->type(), $key, $subtype)) {
					$handlers[$key] = $handler;
				}
			}
		}

		return $handlers;
	}

	public static function getTypeHandler($entity_type, $type_name, $subtype = false)
	{
		$handlers = array();
		foreach (self::getHandlers() as $handler) {
			if (!($handler instanceof DefinitionHandlerBase)) continue;
			if ($handler->handlesFieldType($entity_type, $type_name, $subtype)) {
				$handlers[] = $handler;
			}
		}
		if (count($handlers) <= 0) {
			return false;
		} else {
			return array(
				'handlers' => $handlers,
				'type' => $type_name,
			);
		}
	}

	public static function getFieldHandler($entity_type, $field_name, $subtype = false)
	{
		$field_info = \field_info_field($field_name);
		if (!array_key_exists('type', $field_info)) {
			throw new MalformedFieldDefinitionException('The field for ' . $field_name . ' does not have a type.');
		}

		return self::getTypeHandler($entity_type, $field_info['type'], $subtype);
	}

	/**
	 * Gets a relationship handler by the specified name.
	 *
	 * @param string $handler_name The name of the relationship handler to get.
	 *
	 * @return bool|RelationshipHandler
	 */
	public static function getRelationshipHandler($handler_name)
	{
		foreach (self::getHandlers() as $handler) {
			if (in_array('Drupal\\publisher\\Dependencies\\RelationshipHandler', class_uses($handler))) {
				/** @var $handler RelationshipHandler */
				if ($handler->getRelationshipHandlerName() == $handler_name) {
					return $handler;
				}
			}
		}

		return false;
	}

}

class MalformedFieldDefinitionException extends \Exception {}
