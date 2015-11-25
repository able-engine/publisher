<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\Batch\DeleteOperation;
use Drupal\publisher\Batch\DeleteOperationQueue;
use Drupal\publisher\Remote;

class DeletedEntitiesForm extends FormBase {

	public function build($form, &$form_state)
	{
		$remote = $this->getRemote($form_state);

		// Set a flag for admin functionality.
		$admin = user_access('send with publisher');

		// Prepare the header for the table.
		$header = array(
			'entity_type' => array('data' => t('Entity Type'), 'field' => 'd.entity_type'),
			'title' => array('data' => t('Title'), 'field' => 'd.entity_title'),
			'uuid' => array('data' => t('UUID'), 'field' => 'd.entity_uuid'),
			'deleted' => array('data' => t('Deleted'), 'field' => 'd.deleted'),
		);

		// Prepare the query.
		$query = publisher_deleted_entities_query();
		/** @var \TableSort $query */
		$query = $query->extend('TableSort');
		$query->orderByHeader($header);
		$deleted_entities = $query->execute()->fetchAll();

		// Prepare the options.
		$options = array();
		foreach ($deleted_entities as $deleted_entity) {
			$option = array(
				'entity_type' => ucwords(str_replace('_', ' ', $deleted_entity->entity_type)),
				'title' => $deleted_entity->entity_title,
				'uuid' => $deleted_entity->entity_uuid,
				'deleted' => format_date($deleted_entity->deleted),
			);
			$key = $deleted_entity->entity_type . '|' . $deleted_entity->entity_uuid;
			$options[$key] = $admin ? $option : array_values($option);
			$form_state['entity_title'][$key] = $deleted_entity->entity_title;
		}

		// Add the overview message and the delete all button.
		if ($admin && count($options) > 0) {
			$form['delete_all'] = array(
				'#type' => 'fieldset',
				'#title' => t('Overview'),
				'message' => array(
					'#markup' => t('<p>There are currently <strong>!count</strong> ' .
						'pending entities to be deleted from $remote.</p>', array(
						'!count' => count($options),
						'%remote' => $remote->label,
					)),
				),
				'delete_all' => array(
					'#type' => 'submit',
					'#value' => t('Delete all !count entities from @remote', array(
						'@remote' => $remote->label,
						'!count' => count($options),
					)),
				),
			);
		}

		// Now, start building out the table.
		if ($admin) {
			$form['deletions'] = array(
				'#type' => 'tableselect',
				'#header' => $header,
				'#options' => $options,
				'#empty' => t('There are currently no entities to be deleted.'),
			);
		} else {
			$form['deletions'] = array(
				'#theme' => 'table',
				'#header' => array_values($header),
				'#rows' => $options,
				'#empty' => t('There are currently no pending changes.'),
			);
		}

		// Add the delete selected button.
		if ($admin && count($options) > 0) {
			$form['actions'] = array('#type' => 'actions');
			$form['actions']['submit'] = array(
				'#type' => 'submit',
				'#value' => t('Delete selected entities from @remote', array(
					'@remote' => $remote->label,
				)),
			);
		}

		return $form;
	}

	public function submit($form, &$form_state)
	{
		$remote = $this->getRemote($form_state);

		$deletions_to_perform = array();
		if ($form_state['values']['op'] == $form_state['values']['submit']) {
			$deletions_to_perform = array_filter(array_values($form_state['values']['deletions']));
		} elseif ($form_state['values']['op'] == $form_state['values']['delete_all']) {
			$deletions_to_perform = array_keys($form_state['values']['deletions']);
		}

		$queue = new DeleteOperationQueue();
		foreach ($deletions_to_perform as $deletion) {
			list($uuid, $type) = explode('|', $deletion);
			$queue->addOperation(new DeleteOperation(), $remote, $type, $uuid,
				$form_state['entity_title'][$deletion]);
		}
		$queue->start();

		drupal_set_message(t('Entities deleted successfully.'));
	}

	/**
	 * Gets the remote from the form state.
	 *
	 * @param array $form_state
	 *
	 * @return Remote The remote.
	 * @throws \Exception
	 */
	protected function getRemote($form_state)
	{
		if (!empty($form_state['build_info']['args'][0]) &&
			$form_state['build_info']['args'][0] instanceof Remote) {
			return $form_state['build_info']['args'][0];
		} else {
			throw new \Exception('No remote was supplied.');
		}
	}

}
