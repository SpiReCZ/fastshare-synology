<?php
/**
 * This file is intended for testing the host.php file.
 */
include 'host.php';
include 'common.php';

$syno = new SynologyFastshareFree(
    '', // TODO:
    null,
    null,
    null
);
$results = $syno->GetDownloadInfo();

print_r($results);

?>