<?php

function action_all()
{
	return publisher_entity_tracking_remote_statuses_table();
}

function action_session()
{
	return drupal_get_form('publisher_session_status_form');
}

function action_single($remote)
{
	drupal_set_title(t('Publisher Status for @remote', array('@remote' => $remote->label)));
	return drupal_get_form('publisher_entity_tracking_status', $remote);
}

function action_deleted($remote)
{
	drupal_set_title(t('Deleted Entities for @remote', array('@remote' => $remote->label)));
	return drupal_get_form('publisher_deleted_entities_form', $remote);
}
