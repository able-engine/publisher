<?php

use Drupal\publisher\EntityDiff;
use Drupal\publisher\Entity;

function publisher_compare_revision(Entity $entity, $old_revision, $new_revision)
{
	$differ = new EntityDiff($entity, $old_revision, $new_revision);
	return $differ->diff();
}

function publisher_compare_revision_uuid(Entity $entity, $old_revision_uuid, $new_revision_uuid)
{
	$differ = EntityDiff::diffRevisionUUIDs($entity, $old_revision_uuid, $new_revision_uuid);
	return $differ->diff();
}
