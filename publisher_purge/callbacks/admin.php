<?php

function action_list()
{
	$header = array(
		t('Content Type'),
		t('Paths'),
		t('Operations'),
	);
	$rows = array();

	$content_types = node_type_get_types();
	foreach (publisher_purge_get_all_content_type_paths() as $content_type => $paths) {
		$content_type_info = $content_types[$content_type];
		$row = array();
		$row[] = $content_type_info->name;

		if (empty($paths)) {
			$row[] = '<em>No Paths</em>';
		} else {
			$row[] = theme('item_list', array(
				'items' => array_map('check_plain', $paths)
			));
		}

		$row[] = theme('links', array(
			'links' => array(
				array(
					'title' => 'Edit Paths',
					'href' => 'admin/config/publisher/purge/' . $content_type
				),
			),
			'attributes' => array('class' => array('links', 'inline')),
		));

		$rows[] = $row;
	}

	return theme('table', array(
		'rows' => $rows,
		'header' => $header,
	));
}

function action_content_type_title($content_type)
{
	if (!$content_type) drupal_not_found();
	return t('Manage Purge Settings for !content_type', array('!content_type' => $content_type->name));
}
