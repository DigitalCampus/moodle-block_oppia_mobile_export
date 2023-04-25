<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_oppia_mobile_export;

require_once(dirname(__FILE__) . '/../lib.php');
use PHPUnit\Framework\TestCase;

class lib_test extends TestCase {

    public function test_extract_langs() {
        $content = "";
        $this->assertSame("", extract_langs($content));

    }

    public function test_clean_shortname() {
        $shortname = "  my course    ";
        $this->assertSame("my-course", clean_shortname($shortname));

        $shortname = "My COURSE    ";
        $this->assertSame("My-COURSE", clean_shortname($shortname));

        $shortname = "My COURSE ____   ";
        $this->assertSame("My-COURSE-____", clean_shortname($shortname));

        $shortname = "minun kurssi123  ";
        $this->assertSame("minun-kurssi123", clean_shortname($shortname));
    }
}
