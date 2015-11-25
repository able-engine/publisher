<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;

class ListRemotes extends FormBase {

	public function build($form, &$form_state)
	{
		$remotes = \publisher_get_remotes();
		$form['#tree'] = true;

		if (empty($remotes)) {
			$form['#no-results'] = true;
			return $form;
		}

		foreach ($remotes as $id => $remote) {

			$form['remotes'][$id]['name'] = array(
				'#markup' => \check_plain($remote->label)
			);
			$form['remotes'][$id]['url'] = array(
				'#markup' => \check_plain($remote->url),
			);
			$form['remotes'][$id]['api_key'] = array(
				'#markup' => \check_plain($remote->api_key),
			);

			$send_receive = 'Send and Receive';
			if (!$remote->send && $remote->receive) {
				$send_receive = 'Receive Only';
			} elseif ($remote->send && !$remote->receive) {
				$send_receive = 'Send Only';
			} elseif (!$remote->send && !$remote->receive) {
				$send_receive = 'No Communication';
			}
			$form['remotes'][$id]['send_receive'] = array(
				'#markup' => \check_plain($send_receive),
			);
			$form['remotes'][$id]['configure'] = array(
				'#type' => 'link',
				'#title' => t('configure'),
				'#href' => 'admin/config/publisher/remotes/' . $remote->name,
			);

			// Add enable and disable.
			$form['remotes'][$id]['endis'] = array(
				'#type' => 'link',
				'#title' => t('disable'),
				'#href' => 'admin/config/publisher/remotes/' . $remote->name . '/toggle',
			);
			if (!$remote->enabled) {
				$form['remotes'][$id]['endis']['#title'] = t('enable');
			}

			$form['remotes'][$id]['delete'] = array(
				'#type' => 'link',
				'#title' => t('delete'),
				'#href' => 'admin/config/publisher/remotes/' . $remote->name . '/delete',
			);
			$form['remotes'][$id]['weight'] = array(
				'#type' => 'weight',
				'#title' => t('Weight for @title', array('@title' => $remote->label)),
				'#title_display' => 'invisible',
				'#default_value' => $remote->weight,
			);

		}

		$form['actions'] = array('#type' => 'actions');
		$form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save changes'));

		return $form;
	}

	public function submit($form, &$form_state)
	{
		foreach ($form_state['values']['remotes'] as $id => $data) {
			if (is_array($data) && isset($data['weight'])) {
				\db_update('publisher_remotes')
					->fields(array('weight' => $data['weight']))
					->condition('rid', $id)
					->execute();
			}
		}
		\drupal_set_message(t('The order of the remotes has been saved.'));
	}

}
