<?php

use Drupal\publisher\Remote;

function action_edit_remote($identifier)
{
	$remote = Remote::load($identifier);
	return drupal_get_form('publisher_create_remote', $remote);
}

function action_toggle_remote($identifier)
{
	$remote = Remote::load($identifier);
	if ($remote->enabled == false) {
		$remote->enabled = true;
		$operation = 'enabled';
	} else {
		$remote->enabled = false;
		$operation = 'disabled';
	}

	if ($remote->save()) {
		drupal_set_message("Remote <strong>{$remote->label}</strong> was {$operation} successfully!");
	} else {
		drupal_set_message('There was an error updating the remote. Please try again later.', 'error');
	}
	drupal_goto('admin/config/publisher/remotes');
}

function action_delete_remote($identifier)
{
	$remote = Remote::load($identifier);
	return drupal_get_form('publisher_delete_remote', $remote);
}

function action_create_remote()
{
	return drupal_get_form('publisher_create_remote');
}

function action_settings()
{
	return drupal_get_form('publisher_settings');
}

function action_results()
{
	// Get the results from the drupal cache and clear them.
	$results = cache_get('publisher_batch_results');
	$success = cache_get('publisher_batch_success');
	cache_clear_all('publisher_batch_results', 'cache');
	cache_clear_all('publisher_batch_success', 'cache');

	if (!$success && !$results) {
		drupal_set_message('There are currently no results to display.', 'error');
		return '';
	}

	if ($results && isset($results->data)) {
		$results = $results->data;
	}
	if ($success && isset($success->data)) {
		$success = $success->data;
	}

	if ($success) {
		drupal_set_message('Publisher batch operation completed successfully!');
	} else {
		drupal_set_message('There was an error during the publisher batch operation. See the table below for details.', 'error');
	}

	// Check the count of the results.
	if (!$results || !is_array($results) || count($results) <= 0) {
		return '<p>There are currently no results to display. This could mean the batch operation failed entirely
		or there were no entities to process and send over to the remote server.</p>';
	}

	// Build the table.
	$table_attributes = array(
		'class' => array('system-status-report'),
	);

	$table_rows = array();
	foreach ($results as $result) {
		if (!array_key_exists('type', $result) || !array_key_exists('message', $result) ||
			!array_key_exists('entity_type', $result) || !array_key_exists('entity_id', $result)) continue;
		$row = array('data' => array());
		$row['class'] = ($result['type'] != 'status') ? array($result['type']) : array('ok');
		$row['no_striping'] = true;
		$row['data'] = array(
			$result['entity_type'],
			$result['entity_id'],
			$result['message'],
		);
		$table_rows[] = $row;
	}

	$table_header = array(
		'Entity Type',
		'Entity ID',
		'Message',
	);
	return theme('table', array(
		'header' => $table_header,
		'rows' => $table_rows,
		'attributes' => $table_attributes,
	));
}
