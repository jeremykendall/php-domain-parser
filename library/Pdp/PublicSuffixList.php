<?php

namespace Pdp;

class PublicSuffixList extends \ArrayObject
{
    public function search($value)
    {
        for ($iterator = $this->getIterator(); $iterator->valid(); $iterator->next()) {
            if ($iterator->current() == $value) {
                return $iterator->key();
            }
        }

        return false;
    }
}
