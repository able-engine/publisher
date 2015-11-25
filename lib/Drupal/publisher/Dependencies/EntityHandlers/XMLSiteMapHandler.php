<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Dependencies\Resolver;
use Drupal\publisher\Dependencies\Unresolver;
use Drupal\publisher\Entity;

class XMLSiteMapHandler extends EntityHandlerBase {

	public function handlesEntity(Entity $entity)
	{
		if (!module_exists('xmlsitemap')) return false;
		$info = entity_get_info($entity->type());
		return array_key_exists('xmlsitemap', $info) && is_array($info['xmlsitemap']);
	}

	public function handleEntity(array &$metadata = array())
	{
		$sitemap = xmlsitemap_link_load($this->original_entity->type(),
			$this->original_entity->id());
		if ($sitemap) {
			$resolver = new Resolver($this->original_entity, false);
			$resolver->resolveDependencies(false, $sitemap, false, 'xmlsitemap');
			$metadata['sitemap_definition'] = $resolver->resolvedDefinition();
		}
	}

	public function unhandleEntity(array $metadata = array())
	{
		if (!empty($metadata['sitemap_definition'])) {

			// Unresolve the sitemap information.
			$unresolver = new Unresolver($this->original_entity, false);
			$unresolver->unresolveDependencies($metadata['sitemap_definition'], 'xmlsitemap');
			$new_sitemap = (array)$unresolver->unresolvedDefinition();
			unset($new_sitemap['id']);
			unset($new_sitemap['changefreq']);
			unset($new_sitemap['changecount']);
			unset($new_sitemap['lastmod']);

			// Get the existing sitemap link.
			$existing = xmlsitemap_link_load($this->original_entity->type(),
				$this->original_entity->id());

			// Overwrite the values in the existing if one already exists.
			if ($existing) {
				xmlsitemap_link_save(array_replace($existing, $new_sitemap));
			} else {
				xmlsitemap_link_save($new_sitemap);
			}

		}
	}

}
