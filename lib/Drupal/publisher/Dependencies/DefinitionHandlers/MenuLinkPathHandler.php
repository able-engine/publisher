<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

use Drupal\publisher\Dependencies\InvalidReferenceDefinitionException;

class MenuLinkPathHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'link_path') return true;
		if ($type == 'href') return true;
		if ($type == 'loc' && $subtype == 'xmlsitemap') return true;
		if ($type == 'redirect' && $entity_type == 'redirect') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		if ($entity = publisher_entity_from_path($value)) {
			$original_link = $value;
			$value = self::createReferenceDefinition($entity);

			// Make sure not to include the source if we're processing an xmlsitemap
			// item.
			$this->addDependency($entity, $field_type != 'loc');

			$value['original_link'] = $original_link;
		}
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (is_array($value) && self::verifyReferenceDefinition($value)) {
			try {
				$entity = self::entityFromReferenceDefinition($value);
				if ($entity && array_key_exists('original_link', $value)) {
					$value = str_replace($value['original'], $entity->id(), $value['original_link']);
				}
			} catch (InvalidReferenceDefinitionException $ex) {
				$value = $value['original_link'];
			}
		}
	}

}
