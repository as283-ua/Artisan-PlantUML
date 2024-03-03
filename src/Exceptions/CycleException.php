<?php

namespace As283\ArtisanPlantuml\Exceptions;
use Exception;

class CycleException extends Exception{
    /**
     * @var array
     */
    public $classes;

    /**
     * @param array $classes
     */
    public function __construct($classes, $message = "Cycle detected in schema. ", $code = 0, Exception $previous = null){
        parent::__construct($message . implode(", ", $classes), $code, $previous);
        $this->classes = $classes;
    }
}