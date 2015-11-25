<?php

namespace Drupal\publisher\Endpoints;

use Drupal\publisher\Dependencies\InvalidReferenceDefinitionException;
use Drupal\publisher\Dependencies\TextAreaHandlers\HandlerBase;
use Drupal\publisher\EntityPathHelper;

class SyncMenuLinkUUIDs extends Endpoint {

	public function receive($endpoint, $payload = array())
	{
		if (!array_key_exists('associations', $payload) || !is_array($payload['associations'])) {
			throw new MalformedRequestException('There were no associations sent with the request.');
		}

		$errors = 0;
		$success = 0;
		$unchanged = 0;

		foreach ($payload['associations'] as $menu_item) {

			$router_url = $menu_item['link_path']['router_url'];
			$entities = $menu_item['link_path']['entities'];
			$errored = false;
			foreach ($entities as $index => $entity) {
				try {
					$loaded_entity = HandlerBase::entityFromReferenceDefinition($entity);
					if (!$loaded_entity) {
						$errored = true;
						break;
					}
					$entities[$index] = $loaded_entity;
				} catch (InvalidReferenceDefinitionException $ex) {
					drupal_set_message(t('Error importing link @title, because "@reason."', array('@title' => $menu_item['link_title'], '@reason' => $ex->getMessage())), 'error');
					$errored = true;
					break;
				}
			}
			if ($errored) {
				$errors++;
				continue;
			}

			$link_path = EntityPathHelper::pathFromEntities($router_url, $entities);

			if ($this->syncMenuItem(
				$menu_item['menu_name'],
				$link_path,
				$menu_item['link_title'],
				$menu_item['hidden'],
				$menu_item['depth'],
				$menu_item['router_path'],
				$menu_item['uuid']
			)) {
				$success++;
			} else {
				$unchanged++;
			}
		}

		return array(
			'counts' => array(
				'errors' => $errors,
				'success' => $success,
				'unchanged' => $unchanged,
			),
		);
	}

	protected function syncMenuItem($menu_name, $link_path, $link_title, $hidden, $depth, $router_path, $uuid)
	{
		$affected_rows = db_update('menu_links')
			->fields(array(
				'uuid' => $uuid,
			))
			->condition('menu_name', $menu_name)
			->condition('link_path', $link_path)
			->condition('link_title', $link_title)
			->condition('hidden', $hidden)
			->condition('depth', $depth)
			->condition('router_path', $router_path)
			->execute();
		return ($affected_rows > 0);
	}

	public static function handlesEndpoint($endpoint)
	{
		if ($endpoint == 'sync-menu-uuids') return true;
		return false;
	}

}
