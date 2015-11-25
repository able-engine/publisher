<?php

namespace Drupal\publisher;

class Remote {

	/**
	 * The RID of the Remote.
	 * @var int
	 */
	public $rid;

	/**
	 * The machine name of the remote.
	 * @var string
	 */
	public $name;

	/**
	 * The human-readable name for the remote.
	 * @var string
	 */
	public $label;

	/**
	 * The URL to communicate with for the remote.
	 * @var string
	 */
	public $url;

	/**
	 * The API key to use for authenticating with the remote.
	 * @var string
	 */
	public $api_key;

	/**
	 * Whether or not the remote is enabled.
	 * @var bool
	 */
	public $enabled;

	/**
	 * The weight of the remote.
	 * @var int
	 */
	public $weight;

	/**
	 * Whether or not content can be sent to this remote.
	 * @var bool
	 */
	public $send;

	/**
	 * Whether or not content can be received from this remote.
	 * @var bool
	 */
	public $receive;

	/**
	 * Save
	 *
	 * Saves the remote to the database.
	 *
	 * @return bool|int False if the save failed, otherwise the remote ID.
	 */
	public function save()
	{
		if (empty($this->rid)) {
			$result = drupal_write_record('publisher_remotes', $this);
		} else {
			$result = drupal_write_record('publisher_remotes', $this, 'rid');
		}

		menu_rebuild();
		return $result;
	}

	/**
	 * Load
	 *
	 * Loads a remote from the database by ID or machine name.
	 *
	 * @param string $identifier The ID or machine name to use to load the remote.
	 *
	 * @return bool|Remote False if the load failed or the remote doesn't exist, else the remote.
	 */
	public static function load($identifier)
	{
		$result = &\drupal_static(__FUNCTION__ . '[' . $identifier . ']');
		if (!isset($result)) {

			$query = \db_select('publisher_remotes', 'remotes');
			$query->fields('remotes');
			$query->range(0, 1);

			if (is_numeric($identifier)) {
				$query->condition('remotes.rid', $identifier);
			} else {
				$query->condition('remotes.name', $identifier);
			}

			$query_result = $query->execute()->fetchAll();
			if (count($query_result) > 0) {
				$result = self::import($query_result[0]);
			} else {
				$result = false;
			}

		}

		return $result;
	}

	/**
	 * Load by key.
	 *
	 * Loads a remote by its API key.
	 *
	 * @param string $api_key The API key of the remote.
	 *
	 * @return bool|Remote
	 */
	public static function loadByKey($api_key)
	{
		$result = &\drupal_static(__FUNCTION__ . '|' . $api_key);
		if (!isset($result)) {

			$query = \db_select('publisher_remotes', 'remotes');
			$query->fields('remotes');
			$query->range(0, 1);
			$query->condition('remotes.api_key', $api_key);

			$query_result = $query->execute()->fetchAll();
			if (count($query_result) > 0) {
				$result = self::import($query_result[0]);
			} else {
				$result = false;
			}

		}
		return $result;
	}

	/**
	 * Import
	 *
	 * Converts a PDO result object into a Remote object.
	 *
	 * @param object $query_result The result object from the database.
	 *
	 * @return Remote
	 */
	public static function import($query_result)
	{
		$result = new self();
		foreach (get_object_vars($result) as $key => $value) {
			if (isset($query_result->$key)) {
				$result->$key = $query_result->$key;
			}
		}
		return $result;
	}

	/**
	 * Delete
	 *
	 * Deletes the loaded remote from the database. The remote ID must be set.
	 *
	 * @return bool True if the delete succeeded, false if not.
	 */
	public function delete()
	{
		if (empty($this->rid)) {
			return false;
		} else {
			$query = \db_delete('publisher_remotes');
			$query->condition('rid', $this->rid);
			$result = $query->execute();

			menu_rebuild();
			return $result;
		}
	}

}
