<?php

function publisher_get_flag($flag_name)
{
	$flags = drupal_static('publisher_flags', array());
	return in_array($flag_name, $flags);
}

function publisher_reset_flags()
{
	drupal_static_reset('publisher_flags');
}

function publisher_set_flag($flag_name)
{
	$flags = &drupal_static('publisher_flags', array());
	$flags[] = $flag_name;
}

function publisher_unset_flag($flag_name)
{
	$flags = &drupal_static('publisher_flags', array());
	$index = array_search($flag_name, $flags);
	if ($index !== false) {
		unset($flags[$index]);
	}
}
