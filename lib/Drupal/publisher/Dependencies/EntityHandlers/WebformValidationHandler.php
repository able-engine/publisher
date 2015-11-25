<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Dependencies\Resolver;
use Drupal\publisher\Dependencies\Unresolver;
use Drupal\publisher\Entity;

class WebformValidationHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		if (!module_exists('webform_validation')) return false;
		if ($entity->type() != 'node') return false;
		if (!in_array($entity->bundle(), webform_node_types())) return false;
		return true;
	}

	public function handleEntity(array &$metadata = array())
	{
		$metadata['webform_validation']['processed'] = true;
		$metadata['webform_validation']['rules'] = array();
		$validation = webform_validation_get_node_rules($this->original_entity->id());
		if ($validation) {
			$resolver = new Resolver($this->original_entity, false);
			$resolver->resolveDependencies(false, $validation, false, 'webform_validation');
			$metadata['webform_validation']['rules'] = $resolver->resolvedDefinition();
		}
	}

	public function unhandleEntity(array $metadata = array())
	{
		if (!empty($metadata['webform_validation']['processed'])) {

			$unresolver = new Unresolver($this->original_entity, false);
			$unresolver->unresolveDependencies($metadata['webform_validation']['rules'], 'webform_validation');
			$new_webform_validation = (array)$unresolver->unresolvedDefinition();

			// Delete the existing validation fields.
			webform_validation_node_delete($this->original_entity->definition);

			// Add the new validation fields.
			foreach ($new_webform_validation as $rule) {
				unset($rule['ruleid']);
				$rule['action'] = 'add';
				$rule['nid'] = $this->original_entity->id();
				$rule['rule_components'] = $rule['components'];
				webform_validation_rule_save($rule);
			}

		}
	}

}
