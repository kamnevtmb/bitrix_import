<?php

namespace Import\Yml\Writers\BitrixWriter;

interface OffersWriterInterface
{
    public function addOrUpdate(array $offer);
}
