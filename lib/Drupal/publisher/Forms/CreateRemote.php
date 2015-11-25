<?php

namespace Drupal\publisher\Forms;
use Drupal\publisher\Remote;

class CreateRemote extends RemoteFormBase {

	public function build($form, &$form_state)
	{
		$form['label'] = array(
			'#type' => 'textfield',
			'#title' => t('Label'),
			'#description' => t('The human-readable name for the remote.'),
			'#required' => true,
		);
		$form['name'] = array(
			'#type' => 'machine_name',
			'#title' => t('Machine Name'),
			'#maxlength' => 255,
			'#machine_name' => array(
				'exists' => 'publisher_create_remote_form_validate_machine_name',
				'source' => array('label'),
			),
			'#required' => true,
		);
		$form['url'] = array(
			'#type' => 'textfield',
			'#title' => t('URL'),
			'#description' => t('The URL to use for communicating with the remote server.'),
			'#required' => true,
		);
		$form['api_key'] = array(
			'#type' => 'textfield',
			'#title' => t('API Key'),
			'#description' => t('The API key of the remote server.'),
			'#required' => true,
		);
		$form['enabled'] = array(
			'#type' => 'checkbox',
			'#title' => t('Enabled'),
			'#description' => t('Whether or not to use this remote.'),
			'#default_value' => false,
		);
		$form['send'] = array(
			'#type' => 'checkbox',
			'#title' => t('Send Content'),
			'#description' => t('Whether or not content can be sent to this remote.'),
			'#default_value' => true,
		);
		$form['receive'] = array(
			'#type' => 'checkbox',
			'#title' => t('Receive Content'),
			'#description' => t('Whether or not content can be received from this remote.'),
			'#default_value' => true,
		);
		$form['actions'] = array('#type' => 'actions');
		$form['actions']['submit'] = array(
			'#type' => 'submit',
			'#value' => 'Save',
		);

		// If we have an existing Remote...
		if (self::hasRemote($form, $form_state)) {
			self::populateDefaultValues($form, $form_state['build_info']['args'][0]);
		}

		return $form;
	}

	public static function validateMachineName($machine_name)
	{
		return (Remote::load($machine_name) !== false);
	}

	protected static function populateDefaultValues(&$form, Remote $remote)
	{
		$form['name']['#default_value'] = $remote->name;
		$form['label']['#default_value'] = $remote->label;
		$form['url']['#default_value'] = $remote->url;
		$form['api_key']['#default_value'] = $remote->api_key;
		$form['enabled']['#default_value'] = $remote->enabled;
		$form['send']['#default_value'] = $remote->send;
		$form['receive']['#default_value'] = $remote->receive;

		// Add a delete button.
		$form['actions']['delete'] = array(
			'#type' => 'markup',
			'#markup' => l(t('Delete'), 'admin/config/publisher/remotes/' . $remote->name . '/delete'),
		);
	}

	public function validate($form, &$form_state)
	{
		// Make sure the URL is valid.
		$url = $form_state['values']['url'];
		if ($url && !valid_url($url, true)) {
			form_set_error('url', t('That URL is invalid. You must enter an absolute URL.'));
			// TODO: Add logic to test the connection to the server and throw validation errors if it fails.
		}
	}

	public function submit($form, &$form_state)
	{
		$remote = self::hasRemote($form, $form_state);
		$op = 'Updated';
		if ($remote === false) {
			$remote = new Remote();
			$op = 'Created';
		}

		// Create or update the remote.
		$remote->name = $form_state['values']['name'];
		$remote->label = $form_state['values']['label'];
		$remote->url = $form_state['values']['url'];
		$remote->api_key = $form_state['values']['api_key'];
		$remote->enabled = $form_state['values']['enabled'];
		$remote->send = $form_state['values']['send'];
		$remote->receive = $form_state['values']['receive'];

		if ($remote->save()) {
			\drupal_set_message("{$op} remote <strong>{$remote->label}</strong> successfully!");
			\drupal_goto('admin/config/publisher/remotes');
		} else {
			\drupal_set_message('There was an error saving that remote to the database.', 'error');
		}
	}

}
