<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;

class SettingsForm extends FormBase {

	public function build($form, &$form_state)
	{
		$form['enabled'] = array(
			'#type' => 'checkbox',
			'#title' => t('Enabled'),
			'#description' => t('Whether or not this site will respond to API requests from other servers.'),
			'#default_value' => variable_get('publisher_enabled', false),
		);
		$form['api_key'] = array(
			'#type' => 'textfield',
			'#title' => t('API Key'),
			'#description' => t('A random string which other servers will use to communicate with this one.'),
			'#default_value' => variable_get('publisher_api_key', ''),
			'#required' => true,
		);
		$form['debug'] = array(
			'#type' => 'checkbox',
			'#title' => t('Enable Debug Mode'),
			'#description' => t('When debug mode is enabled, all transactions will be logged to recent log messages.'),
			'#default_value' => variable_get('publisher_debug_mode', false),
		);
		$form['actions'] = array('#type' => 'actions');
		$form['actions']['submit'] = array(
			'#type' => 'submit',
			'#value' => 'Save',
		);
		$form['actions']['generate'] = array(
			'#type' => 'submit',
			'#value' => 'Generate API Key',
		);
		return $form;
	}

	public function submit($form, &$form_state)
	{
		// Process the generate UUID button.
		if ($form_state['values']['op'] == $form_state['values']['generate']) {
			module_load_include('inc', 'uuid');
			$form_state['values']['api_key'] = uuid_generate();
		}

		variable_set('publisher_enabled', $form_state['values']['enabled']);
		variable_set('publisher_api_key', $form_state['values']['api_key']);
		variable_set('publisher_debug_mode', $form_state['values']['debug']);
		drupal_set_message('Publisher settings saved successfully!');
	}

}
