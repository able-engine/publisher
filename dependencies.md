# Publisher Dependencies

Since the Publisher module is complex in nature and deals with several different
components of Drupal, as well as interfaces with several third-party modules, there
are cases where those modules need to be modified to support Publisher. While
creating separate modules would be an alternative (which we might do in the future),
currently you must apply the following patches to their respective modules to get
those modules working with Publisher.

**Note:** Publisher will not break if these patches are not applied. It will simply
treat the module as if it didn't exist. For example, if the patch is not applied
to the `redirect` module, Publisher will treat the `redirect` module as if it is
not installed on the system.

## Required Patches

- `redirect` - https://www.drupal.org/files/issues/redirect-1517348-uuid-13.patch
	- **Originates From:** https://www.drupal.org/node/1517348
	- **Purpose:** Adds UUID support to redirects from the `redirect` module.
- `webform` - Change line 1512 of `webform.module` from `webform_conditional_insert`
	to `webform_conditional_update`
	- **TODO:** Create a bug report and officially add this patch to webform.
	- **Purpose:** Makes sure that webform conditions are being updated every time instead
		of inserted, because the update function handles the case where the conditional
		does not already exist in the database.
	- **Webform functionality will probably be buggy unless this patch is introduced. We need
		to implement unit tests on the project to ensure that it works correctly with these
		third-party modules.**
