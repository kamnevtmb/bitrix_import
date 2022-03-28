<?php

namespace Import\Yml\Traits\textil;

use CIBlockElement;
use Import\Yml\Exceptions\ErrorAddException;

/**
 * Class GoodsProvider отвечает за привязку поставщиков к свойству товара
 * @package Import\Yml\Traits
 * @property $goodsPriverIblockId Инфоблок с поставщиками
 * @property $nameGoodsProvider Имя поставщика
 * @property $propertyNameGoodsProvider Имя свойства товара куда будет сохранён поставщик
 */
trait GoodsProvider
{
  /**
   *
   * @var int
   */
  protected $goodsProviderIblockId = 32;

  /**
   * Имя поставщика, определить в классе Райтера
   * @var string
   */
//  protected $nameGoodsProvider = 'molly';

  /**
   *
   * @var string
   */
  protected $propertyNameGoodsProvider = 'Поставщик';

    /**
     * Ид нового поставщика
     *
     * @var null|int|string
     */
    protected $idGoodsProvider;

    /**
     * Добавляет нового поставщика в инфоблок поставщиков если его нет
     * nameGoodsProvider должно содержать имя
     *
     * @return int
     * @throws ErrorAddException
     */
    protected function addGoodsProvider()
    {
        if ($this->idGoodsProvider) {
            return $this->idGoodsProvider;
        }

        // Проверим на существование
        $e = CIBlockElement::GetList(
            [],
            ['NAME' => $this->nameGoodsProvider, 'IBLOCK_ID' => $this->goodsProviderIblockId],
            false,
            false,
            ['ID', 'IBLOCK_ID']
        )->GetNext();

        if ($e) {
            return $this->idGoodsProvider = $e['ID'];
        }

        // Если не существует добавим
        $el = new CIBlockElement;

        $id = $el->Add([
            'NAME' => $this->nameGoodsProvider,
            'IBLOCK_ID' => $this->goodsProviderIblockId
        ]);

        if (!$id) {
            throw new ErrorAddException(
                $this->getFormatError('Ошибка добавления поставщика в инфоблок: ' . $el->LAST_ERROR, null)
            );
        }

        return $this->idGoodsProvider = $id;
    }
}
