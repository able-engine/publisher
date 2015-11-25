<?php

namespace Drupal\publisher\Dependencies\EntityHandlers;

use Drupal\publisher\Entity;

class EntityHandlerRegistry {

	protected static $handlers = null;

	public static function getHandlers()
	{
		if (self::$handlers !== null) return self::$handlers;
		self::$handlers = array();

		// Register handlers.
		self::$handlers[] = new MenuLinkHandler();
		if (module_exists('redirect') && module_exists('redirect_uuid')) {
			self::$handlers[] = new URLRedirectHandler();
			self::$handlers[] = new URLRedirectEntityHandler();
		}
		if (module_exists('xmlsitemap'))
			self::$handlers[] = new XMLSiteMapHandler();
		if (module_exists('manualcrop'))
			self::$handlers[] = new ManualCropHandler();
		if (module_exists('webform_validation'))
			self::$handlers[] = new WebformValidationHandler();

		// Allow modules to modify this.
		drupal_alter('publisher_entity_handlers', self::$handlers);

		return self::$handlers;
	}

	public static function getEntityHandlers(Entity $entity)
	{
		$handlers[] = array();
		foreach (self::getHandlers() as $handler) {
			/** @var $handler EntityHandlerBase */
			if ($handler->handlesEntity($entity)) {
				$handlers[] = $handler;
			}
		}

		return $handlers;
	}

}
