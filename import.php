<?php

$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../../';

require_once __DIR__ . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$app = new \Import\Yml\App();
$app->importArray([
    \Import\Yml\Importers\VLampImporter::class,
//    \Import\Yml\Importers\PasionariaImporter::class
]);
