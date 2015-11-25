<?php

namespace Drupal\publisher_purge\Forms;

use AbleCore\Forms\FormBase;

class ConfigureContentTypeForm extends FormBase {

	public function build($form, &$form_state)
	{
		if (empty($form_state['build_info']['args'][0]->type)) {
			drupal_not_found();
			return false;
		}
		$content_type = $form_state['build_info']['args'][0]->type;

		// Update the form state storage with the current list of paths
		// if we don't have one already.
		if (!isset($form_state['storage']['paths'])) {
			$form_state['storage']['paths'] = publisher_purge_get_content_type_paths($content_type);
		}

		$form['#tree'] = true;
		$form['paths'] = array();
		foreach ($form_state['storage']['paths'] as $index => $path) {
			$form['paths'][$index] = array(
				'path' => array(
					'#markup' => check_plain($path),
				),
				'delete' => array(
					'#type' => 'submit',
					'#value' => t('Delete'),
					'#name' => 'delete-path-' . $index,
					'#ajax' => array(
						'callback' => '\\Drupal\\publisher_purge\\Forms\\ConfigureContentTypeForm::ajaxCallbackDelete',
						'wrapper' => 'path-container',
					),
					'#validate' => array('\\Drupal\\publisher_purge\\Forms\\ConfigureContentTypeForm::deletePath'),
					'#executes_submit_callback' => false,
				),
			);
		}

		$form['add_path'] = array(
			'#type' => 'fieldset',
			'#title' => t('Add Path'),
			'path' => array(
				'#type' => 'textfield',
				'#title' => t('Path'),
				'#description' => t('Enter a path to purge when a node of this content type is updated.'),
			),
			'add' => array(
				'#type' => 'submit',
				'#value' => t('Add'),
				'#name' => 'add-path',
				'#ajax' => array(
					'callback' => '\\Drupal\\publisher_purge\\Forms\\ConfigureContentTypeForm::ajaxCallbackAdd',
					'wrapper' => 'path-container',
				),
				'#validate' => array('\\Drupal\\publisher_purge\\Forms\\ConfigureContentTypeForm::addPath'),
				'#executes_submit_callback' => false,
				'#limit_validation_errors' => null,
			),
		);

		$form['actions'] = array('#type' => 'actions');
		$form['actions']['submit'] = array(
			'#type' => 'submit',
			'#value' => t('Save Configuration'),
		);

		return $form;
	}

	public function submit($form, &$form_state)
	{
		$paths = $form_state['storage']['paths'];
		$content_type = $form_state['build_info']['args'][0];
		publisher_purge_set_content_type_paths($content_type->type, $paths);

		drupal_set_message(t('Content Type <strong>@content_type</strong> updated successfully.', array(
			'@content_type' => $content_type->name
		)));
		drupal_goto('admin/config/publisher/purge');
	}

	/**
	 * Validation function for deleting a path from the form state
	 * storage.
	 *
	 * @param $form
	 * @param $form_state
	 */
	public static function deletePath($form, &$form_state)
	{
		if (!empty($form_state['triggering_element']['#parents']) &&
			is_array($form_state['triggering_element']['#parents'])) {
			$parents = $form_state['triggering_element']['#parents'];
			array_pop($parents);
			$identifier = end($parents);

			if (is_numeric($identifier) &&
				array_key_exists($identifier, $form_state['storage']['paths'])) {
				unset($form_state['storage']['paths'][$identifier]);
				$form_state['rebuild'] = true;
			}
		}
	}

	/**
	 * Validation function for adding a path to the form state
	 * storage.
	 *
	 * @param $form
	 * @param $form_state
	 */
	public static function addPath($form, &$form_state)
	{
		if (!empty($form_state['triggering_element']['#parents']) &&
			is_array($form_state['triggering_element']['#parents'])) {
			$parents = $form_state['triggering_element']['#parents'];
			array_pop($parents);
			$parents[] = 'path';
			$value = drupal_array_get_nested_value($form_state['values'], $parents);

			if (!in_array($value, $form_state['storage']['paths'])) {
				$form_state['storage']['paths'][] = $value;
				$form_state['rebuild'] = true;
			}
		}
	}

	public static function ajaxCallbackAdd($form, $form_state)
	{
		return self::ajaxCallbackBase($form, $form_state, 2, true);
	}

	public static function ajaxCallbackDelete($form, $form_state)
	{
		return self::ajaxCallbackBase($form, $form_state, 3);
	}

	/**
	 * AJAX Callback for the path table.
	 *
	 * @param $form
	 * @param $form_state
	 * @param $offset_to_root
	 * @param $clear
	 *
	 * @throws \Exception
	 * @return array
	 */
	public static function ajaxCallbackBase($form, $form_state, $offset_to_root, $clear = false)
	{
		if (!array_key_exists('triggering_element', $form_state)) {
			throw new \Exception('The trigger could not be found.');
		}
		$trigger = $form_state['triggering_element'];
		$parents = array_slice($trigger['#array_parents'], 0, count($trigger['#array_parents']) - $offset_to_root);
		$parents[] = 'paths';
		$parents_form = drupal_array_get_nested_value($form, $parents);

		$commands = array();
		$commands[] = ajax_command_replace(null,
			theme('publisher_purge_configure_content_type_table', array(
				'paths_form' => $parents_form,
			)));
		$commands[] = ajax_command_prepend(null, theme('status_messages'));

		if ($clear) {
			// Get the "Add Path" textbox.
			$add_path_parents = array_slice($parents, 0, count($parents) - 1);
			$add_path_parents[] = 'add_path';
			$add_path_parents[] = 'path';
			$add_path = drupal_array_get_nested_value($form, $add_path_parents);

			// TODO: Actually find out why the form API is adding numbers to the IDs.
			$add_path_id = str_replace('--2', '', $add_path['#id']);

			$commands[] = ajax_command_invoke('#' . $add_path_id, 'val', array(''));
		}

		return array('#type' => 'ajax', '#commands' => $commands);
	}

}
