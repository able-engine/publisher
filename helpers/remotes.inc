<?php

use Drupal\publisher\Remote;

/**
 * Gets an array of all remotes.
 *
 * @return array|bool
 */
function publisher_get_remotes()
{
	$remotes = &drupal_static(__FUNCTION__);
	if (!isset($remotes)) {
		$query = db_select('publisher_remotes', 'remotes');
		$query->fields('remotes');
		$query->orderBy('weight', 'asc');
		$results = $query->execute()->fetchAll();

		$remotes = array();
		foreach ($results as $result) {
			$remotes[$result->rid] = Remote::import($result);
		}

		if (count($remotes) <= 0) {
			$remotes = false;
		}
	}

	return $remotes;
}

/**
 * Gets a select-ready list of remotes, keyed by the machine name of the
 * remote.
 *
 * @return array
 */
function publisher_get_remote_options()
{
	$remote_options = array();
	foreach (publisher_get_remotes() as $remote) {
		if (!$remote->enabled || !$remote->send) {
			continue;
		}
		$remote_options[$remote->name] = $remote->label;
	}

	return $remote_options;
}

/**
 * Gets a remote.
 *
 * @param string $machine_name The machine name of the remote to load.
 *
 * @return bool|Remote
 */
function publisher_remote_load($machine_name)
{
	return Remote::load($machine_name);
}

/**
 * Gets a remote by its API key.
 *
 * @param string $api_key The API key of the remote to load.
 *
 * @return bool|Remote
 */
function publisher_get_remote_by_key($api_key)
{
	return Remote::loadByKey($api_key);
}
