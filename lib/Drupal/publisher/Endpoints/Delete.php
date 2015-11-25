<?php

namespace Drupal\publisher\Endpoints;

use Drupal\publisher\Entity;

class Delete extends Endpoint {

	public function receive($endpoint, $payload = array())
	{
		if (!array_key_exists('entities', $payload)) {
			throw new MalformedRequestException('The payload must contain entities to delete, but it does not.');
		}

		$deleted[] = array();
		foreach ($payload['entities'] as $to_delete) {
			if (Entity::exists($to_delete['entity_uuid'], $to_delete['entity_type'])) {

				publisher_set_flag('publisher_deleting');

				$entity_ids = entity_get_id_by_uuid($to_delete['entity_type'], array($to_delete['entity_uuid']));
				$entity_id = count($entity_ids) > 0 ? reset($entity_ids) : false;

				if ($entity_id === false) {
					continue;
				}

				entity_delete($to_delete['entity_type'], $entity_id);

				drupal_set_message(t('<strong>:type</strong> @title deleted successfully.', array(
					':type' => $to_delete['entity_type'],
					'@title' => $to_delete['entity_title'],
				)));
				$deleted[] = $to_delete;

			} else {
				drupal_set_message(t('<strong>:type</strong> @title did not exist.', array(
					':type' => $to_delete['entity_type'],
					'@title' => $to_delete['entity_title'],
				)));
				$deleted[] = $to_delete;
			}
		}

		return array(
			'deleted' => $deleted,
		);
	}

	public static function handlesEndpoint($endpoint)
	{
		if ($endpoint == 'delete') return true;
		return false;
	}

}
