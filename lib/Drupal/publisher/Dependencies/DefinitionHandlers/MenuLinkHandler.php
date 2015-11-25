<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Entity;

class MenuLinkHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'menu_links') return true;
		if ($type == 'mlid' && $entity_type != 'menu_link') return true;
		if ($type == 'plid') return true;
		if (array_search($type, array('p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9')) !== false) return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		if (is_array($value)) {
			foreach ($value as $key => $mlid) {
				$value[$key] = $this->handleSingle($mlid, $field_type);
			}
		} elseif (is_numeric($value) && $value != "0") {
			$value = $this->handleSingle($value, $field_type);
			return;
		}
	}

	protected function handleSingle($mlid, $field_type)
	{
		if (!is_numeric($mlid)) {
			throw new MenuLinkHandlerException('The link ID ' . $mlid . ' is not numeric.');
		}

		$entity = Entity::load($mlid, 'menu_link');
		if (!$entity) {
			throw new MenuLinkHandlerException('The entity ' . $mlid . ' does not exist.');
		}

		if ($entity->uuid() == $this->entity->uuid()) {
			return 'self';
		}

		if ($field_type == 'menu_links') {
			// If the menu item is associated with the node, make the menu item
			// depend on the node, not the other way around.
			$sources = array($entity->uuid());
			$this->addDependency($this->original_entity, $sources);
		}

		// Don't create a source of the node if the type is menu links.
		$this->addDependency($entity, $field_type !== 'menu_links');

		return self::createReferenceDefinition($entity);
	}

	protected function unhandleSingle($definition)
	{
		if (is_array($definition) && self::verifyReferenceDefinition($definition)) {
			$entity = self::entityFromReferenceDefinition($definition);
			return ($entity) ? $entity->id() : null;
		}
		return null;
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if ($value == 'self') {
			unset($value);
			return;
		}
		if (is_array($value) && !array_key_exists('uuid', $value)) {
			foreach ($value as $key => $definition) {
				$value[$key] = $this->unhandleSingle($definition);
			}
		} else {
			$value = $this->unhandleSingle($value);
		}
	}

}

class MenuLinkHandlerException extends \Exception {}
