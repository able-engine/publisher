<?php

if (!function_exists('path_publisher_unchanging_properties')) {
	/**
	 * Implements hook_publisher_unchanging_properties() for the path module.
	 */
	function path_publisher_unchanging_properties()
	{
		return array(
			'all' => array('path'),
		);
	}
}

if (!function_exists('system_publisher_unchanging_properties')) {
	/**
	 * Implements hook_publisher_unchanging_properties() for the system module.
	 */
	function system_publisher_unchanging_properties()
	{
		return array(
			'all' => array('created'),
		);
	}
}

if (!function_exists('defaultcontent_publisher_unchanging_properties')) {
	/**
	 * Implements hook_publisher_unchanging_properties() for the defaultcontent module.
	 */
	function defaultcontent_publisher_unchanging_properties()
	{
		return array(
			'node' => array('machine_name'),
		);
	}
}

if (!function_exists('webform_publisher_unchanging_properties')) {
	/**
	 * Implements hook_publisher_unchanging_properties for the webform module.
	 */
	function webform_publisher_unchanging_properties()
	{
		return array(
			'node' => array('webform'),
		);
	}
}

if (!function_exists('webform_publisher_force_entity_alter')) {
	/**
	 * Implements hook_publisher_force_entity_alter for the webform module.
	 */
	function webform_publisher_force_entity_alter(&$force, \Drupal\publisher\Entity $entity)
	{
		if ($entity->type() == 'node' && in_array($entity->bundle(), webform_node_types())) $force = true;
	}
}
