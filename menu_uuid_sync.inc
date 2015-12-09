<?php

use Drupal\publisher\Remote;

function publisher_sync_menu_link_uuids(Remote $remote)
{
	// Get the current list of menu link UUIDs.
	$associations = publisher_sync_get_associations();
	$transaction = new \Drupal\publisher\Transaction($remote);
	$response = $transaction->send('sync-menu-uuids', array('associations' => $associations));

	if (!$response['success']) {
		$message = "There was an error updating menu link UUIDs with the remote. See the error log for more details.";
		drupal_set_message($message, 'error');
		watchdog('publisher', $message, array(), WATCHDOG_WARNING);
		AbleCore\Debug::wd($response);
	} else {
		drupal_set_message(t('Menu links updated successfully. There were @errors errors, @success successfully updated and @unchanged either unmodified or non-existant menu links.',
			array(
				'@errors' => $response['counts']['errors'],
				'@success' => $response['counts']['success'],
				'@unchanged' => $response['counts']['unchanged'],
			)));
	}
}

function publisher_sync_get_associations()
{
	/*
	 * So really, what are the odds of two menu links having the following items in common?
	 *
	 * - Linking to the same place.
	 * - Having the same title.
	 * - At the same depth.
	 * - Hidden or not.
	 * - In the same menu.
	 */

	// Query the database for the current list of menu link UUIDs.
	$results = db_select('menu_links', 'ml')
		->fields('ml')
		->execute();

	$map = array();
	while ($row = $results->fetch()) {

		$uuid = $row->uuid;

		if (!$row->uuid) {
			$link = menu_link_load($row->mlid);
			$link['uuid'] = uuid_generate();

			db_update('menu_links')
				->fields(array('uuid' => $link['uuid']))
				->condition('mlid', $row->mlid)
				->execute();

			$uuid = $link['uuid'];
		}

		$link_path = \Drupal\publisher\EntityPathHelper::entitiesFromPath($row->link_path);
		foreach ($link_path['entities'] as $index => $entity) {
			$link_path['entities'][$index] = \Drupal\publisher\Dependencies\TextAreaHandlers\HandlerBase::createReferenceDefinition($entity);
		}

		// Generate the path references based on the menu link.
		$map_item = array();
		$map_item['link_path'] = $link_path;
		$map_item['router_path'] = $row->router_path;
		$map_item['link_title'] = $row->link_title;
		$map_item['hidden'] = $row->hidden;
		$map_item['depth'] = $row->depth;
		$map_item['menu_name'] = $row->menu_name;
		$map_item['uuid'] = $uuid;

		$map[] = $map_item;
	}

	return $map;
}
