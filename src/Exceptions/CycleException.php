<?php

namespace As283\ArtisanPlantuml\Exceptions;
use Exception;

class CycleException extends Exception{
    /**
     * @var array[string]
     */
    public $classes;

    public function __construct($classes, $message = "Cycle detected in schema", $code = 0, Exception $previous = null){
        parent::__construct($message, $code, $previous);
        $this->classes = $classes;
    }
}