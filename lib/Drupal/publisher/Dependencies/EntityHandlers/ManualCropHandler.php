<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class ManualCropHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		if ($entity->type() == 'file') return true;
		return false;
	}

	public function handleEntity(array &$metadata = array())
	{
		$metadata['manualcrop_selections'] = manualcrop_load_crop_selection($this->original_entity->definition->uri);
	}

	public function unhandleEntity(array $metadata = array())
	{
		if (!empty($metadata['manualcrop_selections']) && is_array($metadata['manualcrop_selections'])) {
			foreach ($metadata['manualcrop_selections'] as $data) {
				db_merge('manualcrop')->key(array(
						'fid' => $this->original_entity->id(),
						'style_name' => $data['style_name'],
					))->fields($data)->execute();
			}
		}
	}

}
