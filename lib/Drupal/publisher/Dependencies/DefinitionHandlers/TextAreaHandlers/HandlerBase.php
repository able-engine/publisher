<?php

namespace Drupal\publisher\Dependencies\DefinitionHandlers\TextAreaHandlers;

use Drupal\publisher\Dependencies\DefinitionHandlers\FieldHandlerBase;

abstract class HandlerBase extends FieldHandlerBase {

	public function handlesFieldType($entity_type, $type, $subtype)
	{
		if ($type == 'text_long') return true;
		if ($type == 'text_with_summary') return true;
		return false;
	}

	protected function handleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (isset($value['value']))
			$this->handleSingleValue($entity_type, $field_type, $field_name, $value['value'], $delta);
		if (isset($value['safe_value']))
			$this->handleSingleValue($entity_type, $field_type, $field_name, $value['safe_value'], $delta);
	}

	protected function unhandleIndividualValue($entity_type, $field_type, $field_name, &$value, $delta)
	{
		if (isset($value['value']))
			$this->unhandleSingleValue($entity_type, $field_type, $field_name, $value['value']);
		if (isset($value['safe_value']))
			$this->unhandleSingleValue($entity_type, $field_type, $field_name, $value['safe_value']);
	}

	protected function getContents($value)
	{
		if (is_array($value) && array_key_exists('contents', $value)) {
			return $value['contents'];
		} else {
			return $value;
		}
	}

	protected function postHandledResults(&$value, $results, $data_key)
	{
		if (!is_array($value)) {
			$value = array(
				'contents' => $value,
			);
		}
		$value[$data_key] = $results;
	}

	protected function postUnhandleResults(&$value, $find, $replace, $data_key)
	{
		$value['contents'] = str_replace($find, $replace, $value['contents']);
		unset($value[$data_key]);
		if (count(array_keys($value)) === 1) {
			$value = $value['contents'];
		}
	}

	/**
	 * Get DOM Contents
	 *
	 * Gets the DOM document for the current value of the body.
	 *
	 * @param string $value The value of the text area field.
	 *
	 * @return \SimpleXMLElement
	 */
	protected function getDOMContents($value)
	{
		$doc = new \DOMDocument();
		$doc->strictErrorChecking = false;
		libxml_use_internal_errors(true); // Suppress warnings about unsupported HTML5 tags.
		$doc->loadHTML($value);
		libxml_use_internal_errors(false); // Reset the value.
		return simplexml_import_dom($doc);
	}

	protected abstract function handleSingleValue($entity_type, $field_type, $field_name, &$value, $index);
	protected abstract function unhandleSingleValue($entity_type, $field_type, $field_name, &$value);

}
