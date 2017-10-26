<?php

namespace slprime\DependencyInjection;

class Constant {

    protected $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }

}