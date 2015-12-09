<?php

namespace Drupal\publisher\Endpoints;

use Drupal\publisher\EntityUUIDSync\Sync;
use Exception;

class SyncEntityUUIDs extends Endpoint
{
	public function receive($endpoint, $payload = array())
	{
		if (!array_key_exists('metadata', $payload) || !is_array($payload['metadata'])) {
			throw new MalformedRequestException('There was no metadata sent with the request.');
		}

		$errors = 0;
		$success = 0;
		$unchanged = 0;

		$entity_type = $payload['metadata']['entity_type'];
		$handler = Sync::getInstance()->getSyncHandler($entity_type);

		foreach ($payload['metadata']['entities'] as $entity) {
			try {
				if ($handler->handleIncomingEntity($this->remote, $entity)) {
					$success++;
				} else {
					$unchanged++;
				}
			} catch (Exception $ex) {
				$errors++;
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

	public static function handlesEndpoint($endpoint)
	{
		if ($endpoint == 'sync-entity-type') return true;
		return false;
	}
}
