<?php

namespace Drupal\publisher\Rules;

use RulesActionHandlerBase;
use Drupal\publisher\Entity;

class SendWithPublisherAction extends RulesActionHandlerBase {

	protected static function defaults()
	{
		return array(
			'parameter' => array(
				'entity' => array(
					'type' => 'entity',
					'label' => t('Entity'),
					'description' => t('The entity to send to the remote.'),
					'save' => true,
				),
				'remote' => array(
					'type' => 'text',
					'label' => t('Remote'),
					'description' => t('The remote to send the entity to.'),
					'options list' => 'publisher_get_remote_options',
					'restriction' => 'input',
				),
				'force' => array(
					'type' => 'boolean',
					'label' => t('Force'),
					'description' => t('If checked, publisher will send the current entity from scratch.'),
				),
			),
			'group' => t('Publisher'),
			'access callback' => 'publisher_rules_action_access',
		);
	}

	public static function getInfo()
	{
		return self::defaults() + array(
			'name' => 'publisher_send_to_remote',
			'label' => t('Send to Remote'),
		);
	}

	public function execute(\EntityDrupalWrapper $entity_raw, $remote_raw, $force)
	{
		$remote = publisher_remote_load($remote_raw);
		if (!$remote) return false;

		$entity = Entity::load($entity_raw->getIdentifier(), $entity_raw->type());
		if (!$entity) return false;

		publisher_send_entity($entity, $remote, array('force' => $force));
		return true;
	}

} 
