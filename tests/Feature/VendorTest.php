<?php
namespace As283\ArtisanPlantuml\Tests;

use As283\PlantUmlProcessor\PlantUmlProcessor;
use PHPUnit\Framework\TestCase;

class VendorTest extends TestCase
{
    public function testParse()
    {
        $parsed = PlantUmlProcessor::parse("@startuml
        Bob -- Alice : hello
        @enduml"
        );
        
        $this->assertTrue($parsed != null);
    }
}
