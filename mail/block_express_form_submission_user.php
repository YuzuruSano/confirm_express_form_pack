<?php

defined('C5_EXECUTE') or die("Access Denied.");

$formDisplayUrl = URL::to('/dashboard/reports/forms', 'view', $entity->getEntityResultsNodeId());

$submittedData = '';
foreach($attributes as $value) {
    $submittedData .= $value->getAttributeKey()->getAttributeKeyDisplayName() . ":\r\n";
    $submittedData .= $value->getPlainTextValue() . "\r\n\r\n";
}
$body .= $preambleUser;
$body .= t("

%s

", $submittedData);
$body .= $signatureUser;