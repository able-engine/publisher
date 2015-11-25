<?php

function publisher_webform_component_presave(&$component)
{
	if (empty($component['cid'])) return;
	if (empty($component['nid'])) return;

	if (empty($component['pid'])) {
		$component['pid'] = 0;
	}

	// Make sure we're being called from the insert context instead of the update
	// context.
	$backtrace = debug_backtrace(null, 2);
	$last = end($backtrace);
	if (array_key_exists('function', $last) && $last['function'] == 'webform_component_update') return;

	// Delete the existing component before adding the new one.
	db_delete('webform_component')
		->condition('nid', $component['nid'])
		->condition('cid', $component['cid'])
		->execute();
}
