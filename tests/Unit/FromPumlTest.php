<?php
namespace As283\ArtisanPlantuml\Tests;

use As283\ArtisanPlantuml\Commands\FromPUML;
use PHPUnit\Framework\TestCase;

class FromPumlTest extends TestCase
{
    public function testParse()
    {
        $cmd = new FromPUML();

        $cmd->handle();
        $this->assertTrue(true);
    }
}
