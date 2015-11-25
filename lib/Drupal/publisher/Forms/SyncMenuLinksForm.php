<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\Remote;

class SyncMenuLinksForm extends FormBase {

	public function build($form, &$form_state)
	{
		$form = array();
		$form['message'] = array(
			'#type' => 'markup',
			'#markup' => t('<p>Currently, when menu links are transferred over to another server, the system menu link UUIDs ' .
				'(and some of the regular menu link UUIDs) are not synchronized even though they\'re the same menu link. ' .
				'Therefore, we must manually sync the menu link UUIDs with the remote before sending entities to it.</p><p>' .
				'<strong>Note:</strong> This <em>should not</em> be a destructive action, but can be under certain circumstances. ' .
				'Please make sure you take a backup of the target database before running this operation.</p>'),
		);
		$form['remote'] = array(
			'#type' => 'select',
			'#options' => publisher_get_remote_options(),
			'#title' => t('Remote'),
			'#description' => t('The remote to synchronize the menu link UUIDs with.'),
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
		module_load_include('inc', 'publisher', 'uuid_sync');
		publisher_sync_menu_link_uuids(Remote::load($form_state['values']['remote']));
	}

	public function validate($form, &$form_state)
	{
		$remote = Remote::load($form_state['values']['remote']);
		if (!$remote) {
			form_set_error('remote', 'The specified remote is invalid. Please choose another.');
		}
	}
}
