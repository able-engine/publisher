<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\Remote;

class SelectEntitiesForm extends FormBase {

	protected function getStatuses($form_state)
	{
		$arguments = $form_state['build_info']['args'];
		if (!is_array($arguments)) return false;
		if (!array_key_exists(0, $arguments)) return false;
		$statuses = $arguments[0];
		if (!is_array($statuses)) return false;
		return $statuses;
	}

	protected function getCallbackFunction($form_state)
	{
		$arguments = $form_state['build_info']['args'];
		if (!is_array($arguments)) return false;
		if (!array_key_exists(1, $arguments)) return false;
		return $arguments[1];
	}

	protected function getForced($form_state)
	{
		$arguments = $form_state['build_info']['args'];
		if (!array_key_exists(2, $arguments)) return false;
		return $arguments[2];
	}

	protected function getRemote($form_state)
	{
		$arguments = $form_state['build_info']['args'];
		if (!is_array($arguments)) return false;
		if (!array_key_exists(3, $arguments)) return false;
		return $arguments[3];
	}

	public function build($form, &$form_state)
	{
		/** @var Remote $remote */
		$remote = $this->getRemote($form_state);

		$form = array(
			'#tree' => true,
			'info' => array(
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#value' => t('Select the entities you would like to send to <strong>:remote.</strong><br /><a href="#" id="deselect-all-entities">Deselect All Items</a>', array(
					':remote' => $remote->label,
				)),
			),
		);

		// If they have opted to display the forced message...
		if ($this->getForced($form_state)) {
			$form['forced_message'] = array(
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#value' => t('<strong>Note:</strong> It appears you have opted to force sending some entities to the ' .
					'remote. If you notice a large number of entities marked as \'Must be synced,\' this is probably the ' .
					'cause.'),
			);
		}

		$form['entities'] = array();
		$statuses = $this->getStatuses($form_state);
		if (is_array($statuses)) {
			foreach ($statuses as $uuid => $status) {

				// Add the main form entity item.
				$form_entity = array(
					'check' => array(
						'#type' => 'checkbox',
						'#disabled' => $status['required'],
						'#default_value' => $status['required'] || $status['root_entity'],
						'#attributes' => array(
							'data-entity-uuid' => $uuid,
							'class' => array('entity-checkbox'),
						),
					),
					'label' => array(
						'#type' => 'html_tag',
						'#tag' => 'span',
						'#attributes' => array('class' => 'entity-label'),
						'#value' => $status['label'],
					),
					'status' => array(
						'#type' => 'html_tag',
						'#tag' => 'span',
						'#value' => $status['required'] ? 'Must be synced.' : 'Can be synced.',
						'#attributes' => array('class' => 'status-holder'),
					),
				);

				// Prepare the JS.
				$form_entity['check']['#attached']['js'][] = array(
					'data' => drupal_get_path('module', 'publisher') . '/js/entity_select.js',
				);
				$form_entity['check']['#attached']['js'][] = array(
					'type' => 'setting',
					'data' => array('publisher' => array($uuid => drupal_map_assoc($status['required_if']))),
				);
				$form['entities'][$uuid] = $form_entity;

			}
		}

		$deleted_entities = publisher_deleted_entities();
		if (count($deleted_entities) > 0) {
			$form['deleted_entitites'] = array(
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#value' => t('Additionally, there are <strong>!count</strong> entities waiting to be deleted from @remote. Would you like to !link', array(
					'!count' => count($deleted_entities),
					'@remote' => $remote->label,
					'!link' => l('review them now?', 'admin/reports/publisher/' . $remote->name . '/deleted', array('attributes' => array('target' => '_blank'))),
				)),
			);
		}

		if (count($form['entities']) > 0) {
			$form['actions'] = array('#type' => 'actions');
			$form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Sync Entities'));
			$form['actions']['sync_all'] = array('#type' => 'submit', '#value' => t('Sync All Entities'));
			$form['actions']['cancel'] = array('#type' => 'submit', '#value' => t('Cancel'));
		}

		return $form;
	}

	public function validate($form, &$form_state)
	{
		// Select all entities if 'Sync All Entities' was clicked.
		if ($form_state['values']['op'] == t('Sync All Entities')) {
			$statuses = $this->getStatuses($form_state);
			$form_state['selected_entities'] = is_array($statuses) ? array_keys($statuses) : array();
			return;
		} elseif ($form_state['values']['op'] == t('Sync Entities')) {

			// First, get the selected entities from the input.
			$selected_entities = array();
			if (!empty($form_state['values']['entities']) && is_array($form_state['values']['entities'])) {
				foreach ($form_state['values']['entities'] as $key => $entity) {
					if ($entity['check']) {
						$selected_entities[] = $key;
					}
				}
			}

			// Now, make sure all the required ifs are met.
			$statuses = $this->getStatuses($form_state);
			if (is_array($statuses)) {
				$loop = true;
				while ($loop == true) {
					$loop = false;
					$new_selected_entities = $selected_entities;
					foreach ($selected_entities as $selected_entity) {
						if (!in_array($selected_entity, $new_selected_entities)) {
							$new_selected_entities[] = $selected_entity;
							$loop = true;
						}
						foreach ($statuses as $status_uuid => $status) {
							if (in_array($selected_entity, $status['required_if']) &&
								!in_array($status_uuid, $new_selected_entities)) {
								$new_selected_entities[] = $status_uuid;
								$loop = true;
							}
						}
					}
					$selected_entities = array_unique($new_selected_entities);
				}
			}

			// Make sure the new $selected_entities is in the same order as
			// the old $selected_entities.
			$new_selected_entities = array();
			foreach (array_keys($statuses) as $index => $entity_candidate) {
				if (in_array($entity_candidate, $selected_entities)) {
					$new_selected_entities[$index] = $entity_candidate;
				}
			}

			$form_state['selected_entities'] = $new_selected_entities;

		}
	}

	public function submit($form, &$form_state)
	{
		$callback_function = $this->getCallbackFunction($form_state);
		if (is_callable($callback_function)) {
			if (empty($form_state['selected_entities'])) {
				$selected_entities = array();
			} else {
				$selected_entities = $form_state['selected_entities'];
			}
			call_user_func_array($callback_function,
				array($form_state['values']['op'], $selected_entities));
		} else {
			drupal_set_message(t('There was an error with the publisher operation.'), 'error');
			drupal_goto('admin/content');
		}
	}

}
