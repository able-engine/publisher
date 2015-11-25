<?php

function theme_publisher_list_remotes($variables)
{
	$form = $variables['form'];

	if (array_key_exists('#no-results', $form) && $form['#no-results'] == true) {
		return '<p>There are currently no remotes available. Why not create one?</p>';
	}

	$rows = array();
	foreach (element_children($form['remotes']) as $id) {
		$form['remotes'][$id]['weight']['#attributes']['class'] = array('remotes-order-weight');
		$rows[] = array(
			'data' => array(
				drupal_render($form['remotes'][$id]['name']),
				drupal_render($form['remotes'][$id]['url']),
				drupal_render($form['remotes'][$id]['api_key']),
				drupal_render($form['remotes'][$id]['send_receive']),
				drupal_render($form['remotes'][$id]['weight']),
				drupal_render($form['remotes'][$id]['configure']),
				drupal_render($form['remotes'][$id]['endis']),
				drupal_render($form['remotes'][$id]['delete']),
			),
			'class' => array('draggable'),
		);
	}

	$header = array(
		t('Name'),
		t('URL'),
		t('API Key'),
		t('Communication'),
		t('Weight'),
		array(
			'data' => t('Operations'),
			'colspan' => 3,
		),
	);

	$output = theme('table',
		array(
			'header' => $header,
			'rows' => $rows,
			'attributes' => array(
				'id' => 'remotes-listing',
			),
		));
	$output .= drupal_render_children($form);

	drupal_add_tabledrag('remotes-listing', 'order', 'sibling', 'remotes-order-weight');

	return $output;
}

function publisher_create_remote_form_validate_machine_name($machine_name)
{
	return Drupal\publisher\Forms\CreateRemote::validateMachineName($machine_name);
}
