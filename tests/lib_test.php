<?php
require_once(dirname(__FILE__) . '/../lib.php');
use PHPUnit\Framework\TestCase;

class LibTest extends TestCase {
    
    public function testExtractLangs() {
        $stack = "";
        $this->assertSame(extractLangs($stack), "");
    }
}