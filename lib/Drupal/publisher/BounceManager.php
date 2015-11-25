<?php

namespace Drupal\publisher;

class BounceManager {

	protected static $instance;

	protected $active_remotes = array();

	/**
	 * Get Instance
	 *
	 * Gets the current instance of the class.
	 *
	 * @return BounceManager
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function checkRemote(Remote $remote)
	{
		foreach ($this->active_remotes as $active_remote) {
			if (!($active_remote instanceof Remote)) continue;
			if ($active_remote->name == $remote->name) return false;
		}
		return true;
	}

	public function addRemote(Remote $remote)
	{
		$this->active_remotes[] = $remote;
	}

	public function completeRemote(Remote $remote)
	{
		foreach ($this->active_remotes as $index => $active_remote) {
			if (!($active_remote instanceof Remote)) continue;
			if ($active_remote->name == $remote->name) {
				unset($this->active_remotes[$index]);
			}
		}
	}

}
