<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

class ChangedHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'changed' && $entity_type == 'node') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		// Intentionally left blank.
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (isset($this->entity->definition->revision_timestamp)) {
			$value = $this->entity->definition->revision_timestamp;
			$this->entity->definition->timestamp = $this->entity->definition->revision_timestamp;
		}
	}

}
