<?php

namespace Drupal\publisher\Forms;

class DeleteRemote extends RemoteFormBase {

	public function build($form, &$form_state)
	{
		// Get the remote.
		$remote = self::hasRemote($form, $form_state);

		if ($remote === false) {
			\drupal_set_message('There was an error deleting that remote.', 'error');
			\drupal_goto('admin/config/publisher/remotes');
		}

		$form['warning'] = array(
			'#type' => 'markup',
			'#markup' => t('Are you sure you want to delete the remote <strong>@remote?</strong>', array(
				'@remote' => $remote->label
			)),
		);

		$form['actions'] = array('#type' => 'actions');
		$form['actions']['delete'] = array('#type' => 'submit', '#button_type' => 'delete', '#value' => 'Delete');
		$form['actions']['cancel'] = array('#type' => 'markup', '#markup' => l(t('Cancel'), 'admin/config/publisher/remotes'));

		return $form;
	}

	public function submit($form, &$form_state)
	{
		$remote = self::hasRemote($form, $form_state);
		if ($remote === false) {
			\drupal_set_message('There was an error deleting that remote.', 'error');
			\drupal_goto('admin/config/publisher/remotes');
		}

		if ($remote->delete() !== false) {
			\drupal_set_message(t('Remote <strong>@remote</strong> deleted successfully!', array('@remote' => $remote->label)));
		} else {
			\drupal_set_message('There was an error deleting that remote. Please try again later.', 'error');
		}
		\drupal_goto('admin/config/publisher/remotes');
	}

}
