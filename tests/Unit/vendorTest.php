<?php
namespace As283\ArtisanPlantuml\Tests;

use function As283\PlantUmlProcessor\parse;
use function As283\PlantUmlProcessor\serialize;
use PHPUnit\Framework\TestCase;

class vendorTest extends TestCase
{
    public function testParse()
    {
        parse("hello");
        $this->assertTrue(true);
    }

    public function testSerialize()
    {
        serialize(null);
        $this->assertTrue(true);
    }
}
