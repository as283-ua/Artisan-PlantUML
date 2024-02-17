<?php
namespace As283\ArtisanPlantuml\Tests;

use As283\ArtisanPlantuml\Commands\ToPUML;
use PHPUnit\Framework\TestCase;

class ToPumlTest extends TestCase
{
    public function testParse()
    {
        $cmd = new ToPUML();

        $cmd->handle();
        $this->assertTrue(true);
    }
}
