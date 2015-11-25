<?php

namespace Drupal\publisher\Endpoints;
use Drupal\publisher\Entity;

class Begin extends Endpoint {

	public function receive($endpoint, $payload = array())
	{
		// Check to see if we have any dependencies.
		if (!array_key_exists('dependencies', $payload)) {
			throw new MalformedRequestException('The payload must contain dependencies, but it does not.');
		}

		// Satisfy them.
		$dependencies = $this->satisfyDependencies($payload['dependencies']);

		// Generate the response.
		return array(
			'dependencies' => $dependencies,
		);
	}

	/**
	 * Given an array of revision IDs and UUIDs and an entity, searches through
	 * our site for a matching revision UUID. If there are no matching UUIDs,
	 * this function returns false.
	 *
	 * @param array  $revisions The list of revisions. Each item contains an ID and
	 *                          UUID field.
	 * @param Entity $entity    The entity on our side to check for revisions.
	 *
	 * @return array|bool
	 */
	protected function findMatchingRevision($revisions, Entity $entity)
	{
		$revision_uuids = array();
		foreach ($revisions as $revision) {
			$revision_uuids[] = $revision['uuid'];
		}

		// Get the revisions on our end and find the nearest one that matches.
		$our_revisions = Entity::getAllRevisions($entity->id(), $entity->type());
		if (!$our_revisions || !is_array($our_revisions)) return false;
		foreach ($our_revisions as $revision) {
			if (in_array($revision['uuid'], $revision_uuids)) {
				return $revision['uuid'];
			}
		}

		return false;
	}

	protected function satisfyDependencies($dependencies)
	{
		$required = array();
		foreach ($dependencies as $dependency) {

			$entity = Entity::loadByUUID($dependency['uuid'], $dependency['entity_type']);
			if (!$entity || (array_key_exists('force', $dependency) && $dependency['force'] === true)) {
				$dependency['need revision'] = $dependency['vuuid'];
				$dependency['have revision'] = '';
				$dependency['have your revision'] = '';
				$dependency['required_from_remote'] = true;
				$required[] = $dependency;
			} else {

				// Check the entity tracking table.
				$status = publisher_entity_tracking_get_status($entity->uuid(), $entity->type(),
					$this->remote->name);

				$dependency['required'] = false;
				$dependency['need revision'] = $dependency['vuuid'];
				$dependency['have revision'] = $entity->vuuid();

				// Get the revision from their end that we have.
				$dependency['have your revision'] = false;
				if ($entity->supportsRevisions() && is_array($dependency['revisions'])) {
					$dependency['have your revision'] = self::findMatchingRevision($dependency['revisions'], $entity);
				}

				if (!$status) {

					// If the revision information we have matches, create an entry
					// in the tracking table and skip it.
					if (!$this->compareRevisions($dependency['have revision'], $dependency['need revision'])) {

						// Get the last time the entity was modified.
						$entity->setRevision($dependency['have revision']);
						$date = $this->getModificationDate($dependency['have revision']);
						if (!$date) {
							if ($entity->supportsRevisions()) {
								$entity->setRevision(Entity::getLatestRevisionID($entity->id(), $entity->type()));
							}
							$date = $entity->getModified();
						}

						publisher_entity_tracking_create_status($entity, $this->remote, array(
							'date_synced' => REQUEST_TIME,
							'changed' => $date,
						));
						continue;

					}

					// Assume that we don't require the latest version by default.
					$dependency['required_from_remote'] = array_key_exists('requires_latest', $dependency) ?
						$dependency['requires_latest'] : false;
					$required[] = $dependency;

				} else if (!$status->date_synced || ($this->compareRevisions($status->vuuid, $dependency['need revision']) &&
						$dependency['have your revision'] != $dependency['need revision']) ||
						(array_key_exists('source_required', $dependency) && $dependency['source_required'])) {

					// If the entity has an entry in the entity tracking table, but changes have
					// been made to it since the last sync, mark it as changed.
					$dependency['required_from_remote'] = array_key_exists('requires_latest',
						$dependency) ? $dependency['requires_latest'] : false;
					$required[] = $dependency;

				}

			}

		}
		return $required;
	}

	/**
	 * Compare Revisions
	 *
	 * Compares two revisions returned by the Entity class.
	 *
	 * @param string $have The revision we have, returned by the Entity class.
	 * @param string $need The revision we need, returned by the Entity class.
	 *
	 * @return bool True if we have an outdated version of the node, false if we don't.
	 */
	protected function compareRevisions($have, $need)
	{
		$have_modified = $this->getModificationDate($have);
		$need_modified = $this->getModificationDate($need);

		if ($have_modified !== false && $need_modified !== false) {
			return ($have_modified < $need_modified);
		}

		return ($have != $need);
	}

	protected function getModificationDate($revision)
	{
		if (strpos($revision, 'norevision') === 0) {
			$segments = explode('|', $revision);
			if (count($segments) === 3) {
				return intval($segments[2]);
			}
		}
		return false;
	}

	public static function handlesEndpoint($endpoint)
	{
		if ($endpoint == 'begin') return true;
		return false;
	}

}
