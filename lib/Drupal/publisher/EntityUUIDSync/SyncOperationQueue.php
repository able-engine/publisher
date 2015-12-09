<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Batch\OperationQueue;

class SyncOperationQueue extends OperationQueue
{
	public function __construct()
	{
		parent::__construct();

		$this->finished = 'publisher_entity_uuid_sync_complete';
	}
}
