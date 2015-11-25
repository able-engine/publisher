<?php

namespace Drupal\publisher\Batch;

class OperationQueue extends \AbleCore\Batch\OperationQueue {

	public function __construct()
	{
		$this->finished = 'publisher_batch_operation_finished';
	}

}
