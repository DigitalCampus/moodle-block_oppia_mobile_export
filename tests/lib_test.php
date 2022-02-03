<?php
require_once(dirname(__FILE__) . '/../lib.php');
use PHPUnit\Framework\TestCase;

class LibTest extends TestCase {
    
    public function testExtractLangs() {
        $content = "";
        $this->assertSame("", extractLangs($content));

    }
    
    public function testCleanShortname() {
        $shortname = "  my course    ";
        $this->assertSame("my-course", cleanShortname($shortname));
        
        $shortname = "My COURSE    ";
        $this->assertSame("My-COURSE", cleanShortname($shortname));

        $shortname = "My COURSE ____   ";
        $this->assertSame("My-COURSE-____", cleanShortname($shortname));
        
        $shortname = "minun kurssi123  ";
        $this->assertSame("minun-kurssi123", cleanShortname($shortname));
    }
}