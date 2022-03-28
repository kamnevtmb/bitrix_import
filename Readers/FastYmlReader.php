<?php

namespace Import\Yml\Readers;
use SimpleXMLElement;
use XMLReader;
use DOMDocument;

/**
 * Class SimpleXmlReader чтение yml файлов с помощью simplexml читает весь файл в память
 * @package Import\Yml\Readers
 */
class FastYmlReader implements ReaderInterface
{
    protected $cats = null;

    protected $offers = null;

    /**
     * SimpleXmlReader constructor.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        // Категории
        $this->cats = new XMLReader();
        $this->cats->open($path);
        // устанавливаем на первый элемент
        while ($this->cats->read() && $this->cats->name !== 'category');

        // Товары
        $this->offers = new XMLReader();
        $this->offers->open($path);
        while ($this->offers->read() && $this->offers->name !== 'offer');
    }

    protected function getCategoryData($node)
    {
      $attr = $node->attributes();
      return [
        'value'     => (string) $node,
        'id'        => (int) $attr->id,
        'parentId'  => (int) $attr->parentId
        ];
    }

    protected function getOfferData($offer)
    {
      return null;

      /* example
      $attr = $offer->attributes();
      return [
          'id'            => (string) $attr->id,
          'available'     => (string) $attr->available === 'true' ? true : false,
          'name'          => (string) $offer->name,
          'barcode'         => (string) $offer->barcode,
          'description'   => (string) $offer->description,
          'categoryId'    => (string) $offer->categoryId,
          //'vendor'        => (string) $offer->vendor,
          'price'         => (string) $offer->price,
          //'price_retail'  => (string) $offer->price_retail,
          //'weight'        => ((string) $offer->weight) * 1000,
          'params'        => $this->getParams($offer),
          'pictures'      => $this->getPictures($offer)
      ];
      */

    }

    /**
     * Возвращает массив с данными следующей категории, если категории закончились вернёт false
     *
     * @return array|false
     */
    public function getNextCategory()
    {
        $doc = new DOMDocument;
        if ($this->cats->name === 'category')
        {
            // два  способа получения узла
            //  $node = new SimpleXMLElement($this->cats->readOuterXML());
            $node = simplexml_import_dom($doc->importNode($this->cats->expand(), true));
            $this->cats->next('category');

            return $this->getCategoryData($node);

        } else {
            return false;
        }
    }

    /**
     * Вернёт массив с данными следующего товара, если товары закончились то вернёт false
     *
     * @return array|false
     */
    public function getNextOffer()
    {
        $doc = new DOMDocument;
        if ($this->offers->name === 'offer')
        {
            // два  способа получения узла
            // $offer = new SimpleXMLElement($$this->offers->readOuterXML());
            $offer = simplexml_import_dom($doc->importNode($this->offers->expand(), true));
            $this->offers->next('offer');

            return $this->getOfferData($offer);
        } else {
            return false;
        }
    }

    /**
     * Вернёт массив с характеристиками товара
     *
     * @param SimpleXMLElement $offer
     * @return array
     */
    protected function getParams(SimpleXMLElement $offer)
    {
        $params = [];

        foreach ($offer->param as $param) {
            $attr = $param->attributes();

            $params[] = [
                'name' => (string) $attr->name,
                'unit' => (string) $attr->unit,
                'value' => (string) $param
            ];
        }

        return $params;
    }

    protected function getPictures(SimpleXMLElement $offer)
    {
        $pictures = [];

        foreach ($offer->picture as $pic) {
            $pictures[] = trim((string) $pic);
        }

        return $pictures;
    }

}
