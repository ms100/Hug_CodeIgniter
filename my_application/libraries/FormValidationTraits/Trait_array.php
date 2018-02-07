<?php

Trait Trait_array
{
    public function count_min($data, $min)
    {
        return is_array($data) && count($data) >= $min;
    }

    public function count_max($data, $max)
    {
        return is_array($data) && count($data) <= $max;
    }

    public function count_exact($data, $exact)
    {
        return is_array($data) && count($data) == $exact;
    }
}