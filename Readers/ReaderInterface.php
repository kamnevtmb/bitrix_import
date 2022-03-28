<?php

namespace Import\Yml\Readers;

interface ReaderInterface
{
    public function getNextCategory();

    public function getNextOffer();
}
