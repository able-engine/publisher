<?php

namespace Drupal\publisher\EntityUUIDSync;

use Drupal\publisher\Batch\Operation;
use Drupal\publisher\Remote;

class BuildMetadataOperation extends Operation
{
	public function execute($entity_type, &$context)
	{
		$context['results']['metadata'] = array(
			'entity_type' => $entity_type,
			'entities' => array(),
		);
	}
}
