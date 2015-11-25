<?php

function theme_publisher_select_entities_form($variables)
{
	$form = $variables['form'];
	$table_headers = array('', t('Entity'), t('Status'));
	$table_rows = array();

	// Render the first items in the form.
	$output = drupal_render($form['info']);
	if (array_key_exists('forced_message', $form)) {
		$output .= drupal_render($form['forced_message']);
	}

	foreach (element_children($form['entities']) as $entity_uuid) {

		$form_entity = &$form['entities'][$entity_uuid];

		$row = array(
			drupal_render($form_entity['check']),
			drupal_render($form_entity['label']),
			drupal_render($form_entity['status']),
		);

		$table_rows[] = $row;

	}

	$output .= theme('table', array(
		'header' => $table_headers,
		'rows' => $table_rows,
		'empty' => t('There are currently no entities available to select.'),
	));

	if (array_key_exists('actions', $form)) {
		$output .= drupal_render($form['actions']);
	}

	// Render the rest of the form.
	foreach (element_children($form) as $child) {
		$output .= drupal_render($form[$child]);
	}

	return $output;
}
