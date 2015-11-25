<?php

namespace Drupal\publisher\Preparers;

use Drupal\publisher\Entity;

class PreparerRegistry {

	protected static $registry = array();

	public function __construct()
	{
		if (count(self::$registry) <= 0) {

			// Prepare the registry.
			self::$registry[] = 'Drupal\publisher\Preparers\Node';
			self::$registry[] = 'Drupal\publisher\Preparers\MenuLink';
			self::$registry[] = 'Drupal\publisher\Preparers\User';

			// Call the hook for other modules.
			self::$registry += module_invoke_all('publisher_preparers');

		}
	}

	public function getPreparers(Entity $entity)
	{
		$preparers = array();
		foreach (self::$registry as $class_name) {
			if (forward_static_call(array($class_name, 'handlesEntity'), $entity) === true) {
				$preparers[] = $class_name;
			}
		}
		return $preparers;
	}

	protected function prepareEntity(Entity &$entity, $function = 'beforeSave')
	{
		$extra_arguments = array_slice(func_get_args(), 2);

		$preparers = $this->getPreparers($entity);
		$return_values = array();
		foreach ($preparers as $preparer) {

			$instance = new $preparer($entity);
			$class = get_class($instance);

			$new_extra_arguments = array();
			foreach ($extra_arguments as $index => $argument) {
				if (is_array($argument) && array_key_exists($class, $argument)) {
					$new_extra_arguments[$index] = $argument[$class];
				} else {
					$new_extra_arguments[$index] = $argument;
				}
			}

			$arguments = array_merge(array(&$entity->definition), $new_extra_arguments);
			$return_values[$class] = call_user_func_array(array($instance, $function), $arguments);

		}

		return $return_values;
	}

	public function beforeSave(Entity &$entity)
	{
		$this->prepareEntity($entity, 'beforeSave');
	}

	public function afterSave(Entity &$entity)
	{
		$this->prepareEntity($entity, 'afterSave');
	}

	public function beforeDependencies(Entity &$entity)
	{
		$this->prepareEntity($entity, 'beforeDependencies');
	}

	public function beforeSend(Entity &$entity)
	{
		$this->prepareEntity($entity, 'beforeSend');
	}

	public function getMetadata(Entity &$entity)
	{
		return $this->prepareEntity($entity, 'getMetadata');
	}

	public function processMetadata(Entity &$entity, array $metadata = array())
	{
		$this->prepareEntity($entity, 'processMetadata', $metadata);
	}

} 
