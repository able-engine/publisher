<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;
use Drupal\publisher\Entity;
use Drupal\publisher\TransactionSession;

class SessionForm extends FormBase {

	public function build($form, &$form_state)
	{
		$headers = array('Entity');
		$rows = array();

		$transaction = TransactionSession::getFromSession();
		if ($transaction !== false) {

			$remote = $transaction->getRemote();
			$root_entities = $transaction->getRootEntities();
			$form['current_session'] = array(
				'#type' => 'fieldset',
				'#title' => t('Current Session')
			);
			$form['current_session']['remote'] = array(
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#value' => t('Currently sending <strong>!count</strong> entities to <strong>:remote</strong>', array(
					'!count' => count($root_entities),
					':remote' => $remote->label,
				)),
			);

			foreach ($root_entities as $root_entity) {
				/** @var Entity $entity */
				$entity = $root_entity['entity'];
				$uri = entity_uri($entity->type(), $entity->definition);
				$url = ltrim($uri['path'], '/');
				$rows[] = array(l(entity_label($entity->type(), $entity->definition), $url));
			}

			$form['current_session']['clear'] = array('#type' => 'submit', '#value' => 'Clear Session');

		}

		$form['table'] = array(
			'#theme' => 'table',
			'#header' => $headers,
			'#rows' => $rows,
			'#empty' => t('There is currently no pending publisher session.'),
		);

		return $form;
	}

	public function submit($form, &$form_state)
	{
		TransactionSession::complete(false);
		drupal_set_message(t('Transaction cleared successfully.'));
	}

}
