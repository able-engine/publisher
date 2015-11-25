<?php

/**
 * Normalizes the results from drupal_get_messages() into a more
 * programmer-friendly array.
 *
 * @return array
 */
function publisher_get_messages()
{
	$messages = drupal_get_messages();
	$results = array();
	foreach ($messages as $type => $innermsgs) {
		$results[$type] = array();
		if (is_array($innermsgs)) {
			foreach ($innermsgs as $msg) {
				$results[$type][] = $msg;
			}
		} else {
			$results[$type][] = $innermsgs;
		}
	}

	return $results;
}
