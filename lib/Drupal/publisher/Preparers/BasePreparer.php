<?php

namespace Drupal\publisher\Preparers;

use Drupal\publisher\Entity;

interface PreparerInterface {

	public static function handlesEntity(Entity $entity);
	public function beforeDependencies(&$definition);
	public function beforeSave(&$definition);
	public function afterSave(&$definition);
	public function beforeSend(&$definition);

}

abstract class BasePreparer implements PreparerInterface {

	/**
	 * @var \Drupal\publisher\Entity|null
	 */
	protected $entity = null;

	public function __construct(Entity $entity)
	{
		$this->entity = $entity;
	}

	public function beforeDependencies(&$definition) {}
	public function beforeSave(&$definition) {}
	public function afterSave(&$definition) {}
	public function beforeSend(&$definition) {}

} 
