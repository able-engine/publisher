<?php

/**
 * Implements hook_cron()
 */
function publisher_cron()
{
	$duplicates_query = db_select('publisher_entity_tracking', 't');
	$duplicates_query->addField('t', 'uuid');
	$duplicates_query->addField('t', 'entity_type');
	$duplicates_query->addField('t', 'remote');
	$duplicates_query->addExpression("group_concat(distinct t2.changed order by t2.changed desc separator '|')", 'timestamps');
	$duplicates_query->addJoin('left', 'publisher_entity_tracking', 't2',
		't2.uuid = t.uuid');
	$duplicates_query->groupBy('t.uuid');
	$duplicates_query->groupBy('t.remote');
	$results = $duplicates_query->execute();

	foreach ($results as $row) {
		$timestamps = explode('|', $row->timestamps);
		if (count($timestamps) > 1) {

			// Remove the latest duplicate.
			array_shift($timestamps);

			// Generate the query to delete the duplicates.
			db_delete('publisher_entity_tracking')
				->condition('uuid', $row->uuid)
				->condition('entity_type', $row->entity_type)
				->condition('remote', $row->remote)
				->condition('changed', $timestamps)
				->execute();

		}
	}

	// Update the topmost relationships.
	publisher_relationships_update_topmost();
}
