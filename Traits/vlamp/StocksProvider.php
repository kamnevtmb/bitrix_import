<?php

namespace Import\Yml\Traits\vlamp;

use CCatalogStoreProduct;
use Import\Yml\Exceptions\ErrorAddException;

/**
 * Class Stock отвечает за добавление количества товара на склад
 * Если треёт добавляется, то склад должен быть создан вручную
 * @property $stockId
 * @package Import\Yml\Traits
 */
trait StocksProvider
{

  // Номер склада для StocksProvider, определить во Райтере для поставщика
  //protected $stockId = 9;

    /**
     * Добавляет или обновляет количество товара на складе
     *
     * @param $id
     * @param array $offer
     * @throws ErrorAddException
     */
    protected function addToStock($id, array $offer)
    {
        $data = [
            'PRODUCT_ID' => $id,
            'STORE_ID' => $this->stockId,
            'AMOUNT' => $offer['qty'] ?: 0,
        ];
        $res = CCatalogStoreProduct::UpdateFromForm($data);

        if (!$res) {
            throw new ErrorAddException(
                $this->getFormatError('Ошибка добавления количества на склад', ['offer' => $offer, 'data' => $data], true)
            );
        }
    }
}
