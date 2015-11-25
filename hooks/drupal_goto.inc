<?php

function publisher_drupal_goto_alter(&$path, &$options, &$http_response_code)
{
	// Check to see if there is a publisher session.
	$transaction_session = \Drupal\publisher\TransactionSession::getFromSession();
	if (!$transaction_session) {
		return;
	}

	// Override the goto if the transaction session is ready.
	if ($transaction_session->isReadyToOverride()) {
		$transaction_session->overrideGoto($path);
	}
}
