<?php

function theme_publisher_purge_configure_content_type($variables)
{
	$form = $variables['form'];

	// Render the paths table.
	$output = theme('publisher_purge_configure_content_type_table', array(
		'paths_form' => $form['paths'],
	));
	$form['paths']['#access'] = false;
	$output .= drupal_render_children($form);

	return $output;
}

function theme_publisher_purge_configure_content_type_table($variables)
{
	$table = array(
		'header' => array('Path', 'Operations'),
		'rows' => array(),
		'attributes' => array(
			'id' => 'path-container',
		),
	);

	$children = element_children($variables['paths_form']);
	if (empty($children)) {
		$table['rows'][] = array(
			array(
				'data' => 'There are currently no paths available. ' .
					'Add one using the fields below.',
				'colspan' => 2,
			)
		);
	} else {
		foreach (element_children($variables['paths_form']) as $index) {

			// Prepare variables.
			$path = $variables['paths_form'][$index]['path'];
			$delete = $variables['paths_form'][$index]['delete'];

			$row = array();
			$row[] = drupal_render($path);
			$row[] = drupal_render($delete);
			$table['rows'][] = $row;

		}
	}

	// Render the table.
	return theme('table', $table);
}
