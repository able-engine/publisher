<?php

namespace Drupal\publisher\Forms;

use AbleCore\Forms\FormBase;

abstract class RemoteFormBase extends FormBase {

	protected static function hasRemote($form, &$form_state)
	{
		if (isset($form_state['build_info']['args'][0]) &&
			get_class($form_state['build_info']['args'][0]) == 'Drupal\publisher\Remote'
		) {
			return $form_state['build_info']['args'][0];
		} else {
			return false;
		}
	}

}
