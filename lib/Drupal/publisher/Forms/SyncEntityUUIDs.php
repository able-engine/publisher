<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\EntityUUIDSync\Sync;
use Drupal\publisher\EntityUUIDSync\SyncOperationQueue;
use Drupal\publisher\Remote;

class SyncEntityUUIDs extends FormBase
{
	public function build($form, &$form_state)
	{
		$form['message'] = array(
			'#type' => 'markup',
			'#markup' => t('<p>If you\'re using Publisher in a more advanced context (like syncing content ' .
				'between websites that have users or taxonomy terms that weren\'t created at the same time, ' .
				'and therefore don\'t have the same UUIDs), you can use this feature to sync the UUIDs based ' .
				'on name. That way, instead of creating two separate taxonomy terms or users with the same name, ' .
				'Publisher will be able to use the same user or taxonomy term.</p><p>' .
				'<strong>Note:</strong> This <em>should not</em> be a destructive action, but can under certain ' .
				'circumstances. Please make sure you take a backup of the target website before running this ' .
				'operation.</p>'),
		);
		$form['remote'] = array(
			'#type' => 'select',
			'#options' => publisher_get_remote_options(),
			'#title' => t('Remote'),
			'#description' => t('The remote to synchronize the entity type UUIDs with.'),
			'#required' => true,
		);
		$form['entity_type'] = array(
			'#type' => 'select',
			'#options' => Sync::getInstance()->supportedTypesOptions(),
			'#title' => t('Entity Type'),
			'#description' => t('The entity type to sync.'),
			'#required' => true,
		);

		$form['actions'] = array('#type' => 'actions');
		$form['actions']['submit'] = array(
			'#type' => 'submit',
			'#value' => t('Synchronize'),
		);

		return $form;
	}

	public function submit($form, &$form_state)
	{
		$remote = Remote::load($form_state['values']['remote']);
		$queue = new SyncOperationQueue();
		Sync::getInstance()->addBatchOperations($form_state['values']['entity_type'], $remote, $queue);
		$queue->start();
	}

	public function validate($form, &$form_state)
	{
		$remote = Remote::load($form_state['values']['remote']);
		if (!$remote) {
			form_set_error('remote', 'The specified remote is invalid. Please choose another.');
		}
	}
}
