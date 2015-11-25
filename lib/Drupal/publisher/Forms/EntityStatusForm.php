<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\Entity;
use Drupal\publisher\Remote;

class EntityStatusForm extends FormBase {

	public function build($form, &$form_state)
	{
		// Get the remote.
		$remote = $this->getRemote($form_state);

		// Check to see if we have an entity.
		$entity = null;
		if (!empty($form_state['build_info']['args'][1]) &&
			$form_state['build_info']['args'][1] instanceof Entity) {
			$entity = $form_state['build_info']['args'][1];
		}

		// Set a flag for admin functionality.
		$admin = user_access('send with publisher');

		// Prepare the header for the table.
		$header = array(
			'entity_type' => array('data' => t('Entity Type'), 'field' => 't.entity_type'),
			'title' => array('data' => t('Title'), 'field' => 'n.title'),
			'uuid' => t('UUID'),
			'vuuid' => t('Revision'),
			'changed' => array('data' => t('Changed'), 'field' => 't.changed', 'sort' => 'desc'),
			'user' => array('data' => t('User'), 'field' => 'u.name'),
			'date_synced' => array('data' => t('Date Synced'), 'field' => 't.date_synced'),
		);

		// Get the statuses based on whether or not an entity was supplied.
		$statuses = $this->getStatuses($header, $remote, $entity);

		// Prepare the options.
		$options = array();
		foreach ($statuses as $status) {

			$option = array(
				'entity_type' => check_plain($status->entity_type),
				'uuid' => check_plain($status->uuid),
				'vuuid' => check_plain($status->vuuid),
				'changed' => format_date($status->changed),
				'date_synced' => 'Not Synced',
				'user' => theme('username', array('account' => user_load($status->uid), 'uid' => $status->uid)),
			);

			if (isset($status->date_sent) && $status->date_sent) {
				$option['date_synced'] = format_date($status->date_sent);
			}

			if ($status->title && $status->nid) {
				$option['title'] = array(
					'data' => array(
						'#type' => 'link',
						'#title' => $status->title,
						'#href' => 'node/' . $status->nid,
					),
				);
			} else {
				$option['title'] = '';
			}

			// Get the class based on the status.
			$status_status = 'ok';
			if (!$status->date_synced) {
				$status_status = 'warning';
			}

			if ($admin) {
				$options[$status->id] = $option;
				$options[$status->id]['#attributes'] = array('class' => array($status_status));
				if ($status_status == 'ok') {
					$options[$status->id]['#disabled'] = true;
				}
			} else {
				$options[] = array(
					'data' => array(
						$option['entity_type'],
						$option['title'],
						$option['uuid'],
						$option['vuuid'],
						$option['changed'],
						$option['user'],
						$option['date_synced'],
					),
					'class' => array($status_status),
				);
			}

		}

		// Add the overview message and the clear all button.
		if ($admin && count($options) > 0) {
			$form['send_all'] = array(
				'#type' => 'fieldset',
				'#title' => t('Overview'),
				'message' => array(
					'#markup' => format_string('<p>There are currently <strong>!count</strong> ' . 'pending entities to be sent to %remote.</p>',
						array(
							'!count' => count($options),
							'%remote' => $remote->label,
						)),
				),
				'send_all' => array(
					'#type' => 'submit',
					'#value' => t('Send all !count entities to @remote', array(
						'@remote' => $remote->label,
						'!count' => count($options)
					)),
				),
			);
		}

		// Now, start building out the table.
		if ($admin) {
			$form['statuses'] = array(
				'#type' => 'tableselect',
				'#header' => $header,
				'#options' => $options,
				'#empty' => t('There are currently no pending changes.'),
			);
		} else {
			$form['statuses'] = array(
				'#theme' => 'table',
				'#header' => array_values($header),
				'#rows' => $options,
				'#empty' => t('There are currently no pending changes.'),
			);
		}

		// Add the send selected button.
		if ($admin && count($options) > 0) {
			$form['actions'] = array('#type' => 'actions');
			$form['actions']['submit'] = array(
				'#type' => 'submit',
				'#value' => t('Send selected entities to @remote', array(
					'@remote' => $remote->label,
				)),
			);
		}

		return $form;
	}

	/**
	 * Gets an array of status objects based on either all nodes or the specified
	 * entity if one is passed.
	 *
	 * @param array  $header The headers to be displayed on the table.
	 * @param Remote $remote The remote to check for.
	 * @param Entity $entity The entity to get dependencies and statuses of those
	 *                       dependencies for.
	 *
	 * @return array An array of status objects.
	 */
	protected function getStatuses(array $header, Remote $remote, Entity $entity = null)
	{
		/** @var \TableSort $statuses_query */
		$statuses_query = db_select('publisher_entity_tracking',
			't')->extend('PagerDefault')->limit(25)
			->extend('TableSort');

		$statuses_query = publisher_entity_tracking_get_statuses_query($remote, $entity, $statuses_query);
		$statuses_query->addJoin('left', 'users', 'u', 'u.uid = t.uid');
		$statuses_query->orderByHeader($header);

		return $statuses_query->execute()->fetchAll();
	}

	protected function getRemote($form_state)
	{
		// Check to see if we have a remote.
		/** @var Remote $remote */
		$remote = null;
		if (!empty($form_state['build_info']['args'][0]) && $form_state['build_info']['args'][0] instanceof Remote) {
			$remote = $form_state['build_info']['args'][0];
		}

		// If we don't have a remote, 404.
		if (!$remote) {
			watchdog('publisher_entity_tracking', 'Remote not specified or invalid.');
			drupal_not_found();
			return array();
		}

		return $remote;
	}

	public function submit($form, &$form_state)
	{
		// Get the remote.
		$remote = $this->getRemote($form_state);

		$statuses_to_send = array();
		if ($form_state['values']['op'] == $form_state['values']['send_all']) {
			$statuses_to_send = array_keys($form_state['values']['statuses']);
		} elseif ($form_state['values']['op'] == $form_state['values']['submit']) {
			$statuses_to_send = array_filter(array_values($form_state['values']['statuses']));
		}

		$entities_to_send = array();
		foreach ($statuses_to_send as $status_to_send) {
			list($uuid, $type) = explode('|', $status_to_send);
			$entities_to_send[] = Entity::loadByUUID($uuid, $type);
		}

		publisher_send_entities($entities_to_send, $remote);
	}
}
