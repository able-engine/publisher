(function($) {
	Drupal.behaviors.PublisherEntitySelect = {

		internal: {
			required_ifs: {},
			entities: {}
		},
		attach: function(context, settings) {

			var self = Drupal.behaviors.PublisherEntitySelect;
			if (Drupal.settings.publisher === undefined) {
				return;
			}
			self.internal.required_ifs = Drupal.settings.publisher;
			var trigger_entities = [];

			$(':input[type="checkbox"].entity-checkbox').each(function(index, element) {

				var $checkbox = $(element);
				if ($checkbox.hasClass('publisher-processed')) {
					return;
				}
				var entity_uuid = $checkbox.data('entity-uuid');
				var entity = {};
				entity.checkbox = $checkbox;
				entity.status = $checkbox.parents('tr').find('td .status-holder').first();
				entity.label = $checkbox.parents('tr').find('td .entity-label').first().html();
				entity.required_if = self.internal.required_ifs[entity_uuid];
				entity.refresh = self.refreshEntity;
				entity.uuid = entity_uuid;
				self.internal.entities[entity_uuid] = entity;

				if (entity.checkbox.is(':checked')) {
					trigger_entities.push(entity);
				}

				$checkbox.change(self.changed);
				$checkbox.addClass('publisher-processed');

			});

			$.each(trigger_entities, function(index, trigger_entity) {
				trigger_entity.checkbox.trigger('change');
			});

			$('#deselect-all-entities').click(self.deselectAll);

		},

		deselectAll: function() {

			var self = Drupal.behaviors.PublisherEntitySelect;
			for (var uuid in self.internal.entities) {
				if (self.internal.entities.hasOwnProperty(uuid)) {
					var entity = self.internal.entities[uuid];
					if (entity.checkbox.is(':enabled') && entity.checkbox.is(':checked')) {
						entity.checkbox.attr('checked', false).trigger('change', entity.uuid);
					}
				}
			}

		},

		refreshEntity: function() {

			var self = Drupal.behaviors.PublisherEntitySelect;
			var entity = this;
			var status_messages = [];
			for (var uuid in entity.required_if) {
				if (entity.required_if.hasOwnProperty(uuid)) {
					if (self.internal.entities[uuid] &&
						self.internal.entities[uuid].checkbox.is(':checked')) {
						status_messages.push(self.internal.entities[uuid].label);
					}
				}
			}
			if (status_messages.length > 0) {
				entity.checkbox.attr('checked', true).attr('disabled', true).trigger('change', entity.uuid);
				entity.status.html('Must be synced. Required by ' + status_messages.join(', '));
			} else {
				entity.checkbox.attr('checked', entity.checkbox.data('original-value') || false);
				entity.checkbox.attr('disabled', false).trigger('change', entity.uuid);
				entity.status.html('Can be synced.');
			}

		},

		changed: function(e, source_uuid) {

			var self = Drupal.behaviors.PublisherEntitySelect;
			var $checkbox = $(this);
			var entity_uuid = $checkbox.data('entity-uuid');
			var entity = self.internal.entities[entity_uuid];

			if (!source_uuid) {
				$checkbox.data('original-value', $checkbox.is(':checked'));
			}

			for (var uuid in self.internal.entities) {
				if (self.internal.entities.hasOwnProperty(uuid) && uuid !== source_uuid) {
					var candidate_entity = self.internal.entities[uuid];
					for (var required_if_uuid in candidate_entity.required_if) {
						if (candidate_entity.required_if.hasOwnProperty(required_if_uuid)) {
							if (required_if_uuid === entity_uuid) {
								candidate_entity.refresh();
							}
						}
					}
				}
			}

		}

	};
})(jQuery);
