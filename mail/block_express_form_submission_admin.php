<?php

defined('C5_EXECUTE') or die("Access Denied.");

$formDisplayUrl = URL::to('/dashboard/reports/forms', 'view', $entity->getEntityResultsNodeId());

$submittedData = '';
foreach($attributes as $value) {
    $submittedData .= $value->getAttributeKey()->getAttributeKeyDisplayName() . ":\r\n";
    $submittedData .= $value->getPlainTextValue() . "\r\n\r\n";
}
$body .= $preambleAdmin;
$body .= t("

%s

To view all of this form's submissions, visit %s

", $submittedData, $formDisplayUrl);
$body .= $signatureAdmin;