<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers;

class WorkbenchModerationHandler extends DefinitionHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'workbench_moderation') return true;
		return false;
	}

	public function handleField($entity_type, $field_type, $field_name, &$value)
	{
		// Intentionally left blank.
	}

	public function unhandleField($entity_type, $field_type, $field_name, &$value)
	{
		if (isset($value['my_revision']['from_state'])) {
			$this->entity->definition->workbench_moderation_state_current = $value['my_revision']['from_state'];
		}
		if (isset($value['my_revision']['state'])) {
			$this->entity->definition->workbench_moderation_state_new = $value['my_revision']['state'];
		}

		if (isset($value['published']))
			unset($value['published']);

		// Reset the current revision to be my revision.
		$value['my_revision']['current'] = true;
		$value['my_revision'] = (object)$value['my_revision'];
		$value['current'] = $value['my_revision'];

		// Don't let workbench moderation handle the field until later.
		$value['updating_live_revision'] = true;
	}

}
