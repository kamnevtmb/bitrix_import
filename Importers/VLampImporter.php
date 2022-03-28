<?php

namespace Import\Yml\Importers;

use Import\Yml\Readers\VLampYmlReader;
use Import\Yml\Writers\BitrixWriter\BitrixWriter;
use Import\Yml\Writers\BitrixWriter\CategoriesWriter;
use Import\Yml\Writers\VLampWriter\OffersWriter;

class VLampImporter extends AbstractImporter
{
    /**
     * В конструкторе обязательно должны быть взываны 2 метода setReader и setWriter
     */
    public function __construct()
    {
		$this->setReader(new VLampYmlReader('/var/www/cm37190/data/www/svet161.ru/updatable_catalog.xml'));
		//$this->setReader(new VLampYmlReader('/var/www/cm37190/data/www/svet161.ru/Import/Yml/1.xml'));

        $this->setWriter(new BitrixWriter(
            $this->getReader(),
            CategoriesWriter::class,
            OffersWriter::class,
            [
                'iblock_id' => 19,
                'prefix_xml_id' => 'vkrugulamp',
                'price_retail_type_id' => 1,
//                'price_std_type_id' => 0,
            ]
        ));
    }
}
