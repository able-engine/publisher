<?php

use Drupal\publisher\Entity;

function publisher_send_to_remote_action($entity, &$context)
{
	if (drupal_static('publisher_error_encountered')) return;

	// Load the remote.
	$remote = publisher_remote_load($context['remote']);
	if (!$remote) {
		drupal_set_message('The remote ' . $context['remote'] . ' does not exist.', 'error');
		drupal_goto();
	}

	// Prepare the options array.
	$options = $context['options'];

	$entity = Entity::convert($entity);
	if ($entity === false) {
		return;
	}

	if (!publisher_send_entity($entity, $remote, $options)) {
		$error = &drupal_static('publisher_error_encountered');
		$error = true;
	}
}

function publisher_send_to_remote_action_form($options)
{
	$form = array();
	$form['#tree'] = true;
	$form['remote'] = array(
		'#type' => 'select',
		'#title' => t('Remote'),
		'#description' => t('The remote to send the entity to.'),
		'#options' => publisher_get_remote_options(),
		'#required' => true,
	);
	$form['force'] = array(
		'#type' => 'checkbox',
		'#title' => t('Force'),
		'#description' => t('If this is checked, the latest revision of the entity (if supported) will be sent regardless.'),
	);

	return $form;
}

function publisher_send_to_remote_action_views_bulk_operations_form($options)
{
	return publisher_send_to_remote_action_form($options);
}

function publisher_send_to_remote_action_submit($form, $form_state)
{
	$options = array();
	$options['remote'] = $form_state['values']['remote'];
	$options['options']['force'] = $form_state['values']['force'];

	return $options;
}
