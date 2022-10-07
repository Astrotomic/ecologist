<?php

namespace App\Data;

use Money\Money;

class Package
{
    public function __construct(
        public string $name,
        public ?int $downloads = null,
        public ?int $trees = null,
        public ?Money $treePrice = null,
        public ?int $carbon = null,
        public ?Money $carbonPrice = null,
    ) {
    }

    public function price(): ?Money
    {
        if ($this->treePrice === null && $this->carbonPrice === null) {
            return null;
        }

        if ($this->treePrice === null) {
            return $this->carbonPrice;
        }

        if ($this->carbonPrice === null) {
            return $this->treePrice;
        }

        return $this->treePrice->add($this->carbonPrice);
    }
}
