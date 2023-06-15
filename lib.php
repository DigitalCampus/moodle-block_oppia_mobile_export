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

require_once($CFG->dirroot.'/config.php');
require_once(dirname(__FILE__) . '/constants.php');

const REGEX_FORBIDDEN_DIR_CHARS = '([\\/?%*:|"<>\. ]+)'; // Catches any sequence of forbidden UNIX dir chars.
const REGEX_FORBIDDEN_TAG_CHARS = '([^a-zA-z0-9\_]+)'; // Catches any character not allowed inside an XML tag.
const REGEX_HTML_ENTITIES = '(&nbsp;|&amp;|&quot;)'; // Catches HTML entities after urlencoding text contents.
const REGEX_RESOURCE_EXTENSIONS = '/\.(mp3|mp4|avi)/'; // Catches media resource supported extensions.
const REGEX_IMAGE_EXTENSIONS = '/\.(png|jpg|jpeg|gif)/'; // Catches image supported extensions.
const REGEX_EXTERNAL_EXTENSIONS = '/\.(pdf|PDF)/'; //Catches external resources supported extensions.
const BASIC_HTML_TAGS = '<strong><b><i><em>'; // Basic HTML tags allowed for the strip_tags() method.
const REGEX_LANGS = '((lang=[\'|\"](?P<langs>[\w\-]*)[\'|\"]))'; // Extracts the lang attribute.
const REGEX_BR = '(<br[[:space:]]*/?>)'; // Catches <br> tags in all its possible ways.

const MEDIAFILE_PREFIX = '@@PLUGINFILE@@';
const MEDIAFILE_REGEX = '(('.MEDIAFILE_PREFIX.'/(?P<filenames>[^\"\'\?<>]*)))'; // Catches the filenames for Moodle embeded files in the content.
// Detects any number of spaces or <br> or <p> tags (in any of its forms).
const SPACES_REGEX = '([[:space:]]|\<br\/?[[:space:]]*\>|\<\/?p\>)*';
// Captures the old media embed method code ( [[media object="..."]]).
const EMBED_MEDIA_REGEX = '((\[\['.SPACES_REGEX . 'media'.SPACES_REGEX.'object=[\"|\'](?P<mediaobject>[\{\}\'\"\:a-zA-Z0-9\._\-\/,[:space:]]*)([[:space:]]|\<br\/?[[:space:]]*\>)*[\"|\']'.SPACES_REGEX.'\]\]))';
// Captures the filename of images inside old media embed method code ( [[media object="..."]]).
const EMBED_MEDIA_IMAGE_REGEX = '(\]\]'.SPACES_REGEX.'\<img[[:space:]]src=[\"|\\\']images/(?P<filenames>[\w\W_\-.]*?)[\"|\\\'])';
const COURSE_EXPORT_FILEAREA = 'course_export';

function delete_dir($dirpath) {
    if (! is_dir($dirpath)) {
        return;
    }
    if (substr($dirpath, strlen($dirpath) - 1, 1) != '/') {
        $dirpath .= '/';
    }

    $it = new RecursiveDirectoryIterator($dirpath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,
            RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->getFilename() === '.' || $file->getFilename() === '..') {
            continue;
        }
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dirpath);
}

function add_or_update_oppiaconfig($modid, $name, $value, $servid="default") {
    global $DB;

    if ($servid !== null) {
        $record = $DB->get_record_select(OPPIA_CONFIG_TABLE,
        "modid=$modid and `name`='$name' and serverid='$servid'");
    } else {
        $record = $DB->get_record(OPPIA_CONFIG_TABLE, array('modid' => $modid, 'name' => $name));
    }

    if ($record) {
        $DB->update_record(OPPIA_CONFIG_TABLE,
            array('id' => $record->id, 'value' => $value));
    } else {
        $DB->insert_record(OPPIA_CONFIG_TABLE,
            array('modid' => $modid, 'name' => $name, 'value' => $value, 'serverid' => $servid));
    }
}

function remove_oppiaconfig_if_exists($modid, $name, $servid="default") {
    global $DB;

    if ($servid !== null) {
        $record = $DB->delete_records_select(OPPIA_CONFIG_TABLE,
        "modid=$modid and `name`='$name' and serverid='$servid'");
    } else {
        $record = $DB->delete_records(OPPIA_CONFIG_TABLE, array('modid' => $modid, 'name' => $name));
    }
}

function get_oppiaconfig($modid, $name, $default, $usenonservervalue, $servid="default") {
    global $DB;
    $record = $DB->get_record_select(OPPIA_CONFIG_TABLE, "modid=$modid and `name`='$name' and serverid='$servid'");
    if ($record) {
        return $record->value;
    } else {
        if ($usenonservervalue) {
            // Try if there is a non-server value saved.
            $record = $DB->get_record(OPPIA_CONFIG_TABLE, array('modid' => $modid, 'name' => $name));
            if ($record) {
                return $record->value;
            }
        }
        return $default;
    }
}

function get_oppiaservers() {
    global $DB, $USER;
    return $DB->get_records(OPPIA_SERVER_TABLE);
}

function add_publishing_log($server, $userid, $courseid, $action, $data) {
    global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $DB->insert_record(OPPIA_PUBLISH_LOG_TABLE,
        array('server' => $server,
                'logdatetime' => $timestamp,
                'moodleuserid' => $userid,
                'courseid' => $courseid,
                'action' => $action,
                'data' => $data)
        );
}

function get_section_title($section) {

    $defaultsectiontitle = false;
    $sectiontitle = strip_tags(format_string($section->summary));
    $title = extract_langs($section->summary, false, false, false);

    // If the course has no summary, we try to use the section name.
    if ($sectiontitle == "") {
        $sectiontitle = strip_tags(format_string($section->name));
        $title = extract_langs($section->name, false, false, false);
    }
    // If the course has neither summary nor name, use the default topic title.
    if ($sectiontitle == "") {
        $sectiontitle = get_string('sectionname', 'format_topics') . ' ' . $section->section;
        $title = $sectiontitle;
        $defaultsectiontitle = true;
    }

    return array(
        'using_default' => $defaultsectiontitle,
        'display_title' => $sectiontitle,
        'title' => $title,
    );
}

function extract_langs($content, $asjson, $striptags, $stripbasictags) {
    global $CURRENTLANG, $DEFAULTLANG;
    preg_match_all(REGEX_LANGS, $content, $langstmp, PREG_OFFSET_CAPTURE);
    $templangs = array();
    if (isset($langstmp['langs']) && count($langstmp['langs']) > 0) {
        for ($i = 0; $i < count($langstmp['langs']); $i++) {
            $lang = $langstmp['langs'][$i][0];
            $lang = str_replace("-", "_", $lang);
            $templangs[$lang] = true;
        }
    } else {
        if ($striptags) {
            if ($stripbasictags) {
                $content = trim(strip_tags($content));
            } else {
                $content = trim(strip_tags($content, BASIC_HTML_TAGS));
            }
        }

        if (!$asjson) {
            return $content;
        } else {
            $json = new stdClass;
            $json->{$DEFAULTLANG} = $content;
            return json_encode($json);
        }
    }

    $filter = new tomobile_langfilter();
    foreach ($templangs as $k => $v) {
        $CURRENTLANG = $k;
        if ($striptags) {
            if ($stripbasictags) {
                $templangs[$k] = trim(strip_tags($filter->filter($content)));
            } else {
                $templangs[$k] = trim(strip_tags($filter->filter($content), BASIC_HTML_TAGS));
            }
        } else {
            $templangs[$k] = trim($filter->filter($content));
        }
    }

    // Reverse array.
    $templangsrev = array_reverse($templangs);
    foreach ($templangsrev as $k => $v) {
        $globals['mobilelangs'][$k] = true;
    }

    if ($asjson) {
        return json_encode($templangsrev);
    } else {
        return $templangsrev;
    }
}

function clean_html_entities($text, $replacebr=false) {
    $cleantext = trim($text);
    if ($replacebr) {
        $cleantext = preg_replace(REGEX_BR, "\n", $cleantext);
    }
    return preg_replace(REGEX_HTML_ENTITIES, " ", $cleantext);
}

function clean_tag_list($tags) {
    // Split on comma.
    $taglist = explode(",", $tags);
    $cleantags = array();

    // Clean each tag separately.
    foreach ($taglist as $tag) {
        $cleantag = trim($tag);
        $cleantag = preg_replace(REGEX_FORBIDDEN_TAG_CHARS, "-", $cleantag);
        if (strlen($cleantag) > 0) {
            array_push($cleantags, $cleantag);
        }
    }
    // Combine cleanTags to string and return.
    return implode(", ", $cleantags);
}

function clean_shortname($shortname) {
    $shortname = trim($shortname);
    $shortname = preg_replace(REGEX_FORBIDDEN_DIR_CHARS, "-", $shortname);
    return preg_replace('(\-+)', "-", $shortname); // Clean duplicated hyphens.
}

function remove_ids_from_json($jsonstring) {
    $jsonstring = preg_replace("(\"courseversion\":\"[0-9]+\",?)", "", $jsonstring);
    $jsonstring = preg_replace("(\"moodle_question_id\":\"[0-9]+\",?)", "", $jsonstring);
    return preg_replace("(\"id\":[0-9]+,?)", "", $jsonstring);
}


function extract_image_file($content, $component, $filearea, $itemid, $contextid, $courseroot, $cmid) {
    global $CFG;
    // Find if any images/links exist.
    preg_match_all(MEDIAFILE_REGEX, $content, $filestmp, PREG_OFFSET_CAPTURE);

    if (!isset($filestmp['filenames']) || count($filestmp['filenames']) == 0) {
        return false;
    }

    $lastimg = false;
    for ($i = 0; $i < count($filestmp['filenames']); $i++) {

        $filename = trim($filestmp['filenames'][$i][0]);

        if (!is_file_an_image($courseroot . "/" . $filename)) {
            // If the file is not an image, we pass on it.
            continue;
        }
        if ($CFG->block_oppia_mobile_export_debug) {
            echo 'Attempting to export thumbnail image: <code>'.urldecode($filename).'</code><br/>';
        }
        $fs = get_file_storage();
        $fileinfo = array(
                'component' => $component,
                'filearea' => $filearea,
                'itemid' => $itemid,
                'contextid' => $contextid,
                'filepath' => '/',
                'filename' => $filename);
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], urldecode($fileinfo['filename']));
        $result = copy_file($file, $component, $filearea, $itemid, $contextid, $courseroot, $cmid);

        if ($result) {
            $lastimg = $result;
        }
    }
    return $lastimg;
}

function get_file_info($filename, $component, $filearea, $itemid, $contextid) {

    $fs = get_file_storage();
    $path = '/';
    $file = $fs->get_file($contextid, $component, $filearea, $itemid, $path, $filename);

    if ($file) {
        return array(
            'filename' => $file->get_filename(),
            'digest' => md5($file->get_content()),
            'filesize' => $file->get_filesize(),
            'moodlefile' => $contextid.';'.$component.';'.$filearea.';'.$itemid.';'.$path.';'.$filename
        );
    }
    return false;

}

// Returns the filename without special or non-ASCII characters, replacing them with underscores.
function filename_to_ascii($filename) {
    $clean = preg_replace(
        '([^\x1F-\x7F]|'.    // Non-ASCII characters.
        '[[:space:]]|' .    // Spaces.
        '[<>:"/\\|?*]|'.      // File system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words.
        '[\x00-\x1F]|'.     // Control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx.
        '[\x7F\xA0\xAD]|'.     // Non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN.
        '[{}^\~`])',           // URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt.
        '_', $filename);

    $clean = preg_replace('(_+)', '_', $clean); // Remove multiple repeated underscores.
    return $clean;
}

function copy_file($file, $component, $filearea, $itemid, $contextid, $courseroot, $cmid) {
    global $CFG;

    $isimage = true;
    if ($file) {
        $filename = $file->get_filename();
        $fullpath = '/'. $contextid .'/'. $component .'/'. $filearea .'/'. $itemid .'/'. $filename;
        $sha1 = sha1($fullpath);
        if (preg_match(REGEX_RESOURCE_EXTENSIONS, $filename) > 0) {
            $isimage = false;
            $filedest = "/resources/".$filename;
        } else {
            $filedest = "/images/".$sha1;
        }
        $file->copy_content_to($courseroot.$filedest);
    } else {
        $link = $CFG->wwwroot.'/course/modedit.php?return=0&sr=0&update='.$cmid;
        $message = 'error_'.($isimage ? 'image' : 'file').'_edit_page';
        echo '<span class="export-error">'.get_string($message, PLUGINNAME, $link).'</span><br/>';
        return false;
    }

    $tr = new StdClass;
    $tr->originalfilename = $filename;
    $tr->filename = sha1($fullpath);
    if ($CFG->block_oppia_mobile_export_debug) {
        $message = 'export_'.($isimage ? 'image' : 'file').'_success';
        echo get_string($message, PLUGINNAME, urldecode($filename))."<br/>";

    }
    return ($isimage ? $filedest : false);
}


function resize_image($image, $imagenewname, $imagewidth, $imageheight, $transparent) {
    global $CFG;

    if ($CFG->block_oppia_mobile_export_thumb_crop) {
        $filename = resize_image_crop($image, $imagenewname, $imagewidth, $imageheight, $transparent);
    } else {
        $filename = resize_image_scale($image, $imagenewname, $imagewidth, $imageheight, $transparent);
    }
    // Just return the last part of the filename (name + extn... not the dir path).
    $pieces = explode("/", $filename);

    return $pieces[count($pieces) - 1];
}

function resize_image_scale($image, $imagenewname, $imagewidth, $imageheight, $transparent) {
    global $CFG;
    $size = getimagesize($image);
    $origw = $size[0];
    $origh = $size[1];
    $ratiosrc = $origw / $origh;

    $ratiotarget = $imagewidth / $imageheight;

    $imagenew = imagecreatetruecolor($imagewidth, $imageheight);

    if (!$transparent) {
        $bgcolour = imagecolorallocate($imagenew,
                        $CFG->block_oppia_mobile_export_thumb_bg_r,
                        $CFG->block_oppia_mobile_export_thumb_bg_g,
                        $CFG->block_oppia_mobile_export_thumb_bg_b);
        imagefill($imagenew, 0, 0, $bgcolour);
    } else {
        imagealphablending( $imagenew, false );
        imagesavealpha($imagenew, true);
    }

    switch($size['mime']) {
        case 'image/jpeg':
            $imagesrc = imagecreatefromjpeg($image);
            break;
        case 'image/png':
            $imagesrc = imagecreatefrompng($image);
            break;
        case 'image/gif':
            $imagesrc = imagecreatefromgif($image);
            break;
    }

    if ($origh > $origw || $ratiosrc < $ratiotarget) {
        $border = floor(($imagewidth - ($imageheight * $origw / $origh)) / 2);
        imagecopyresampled($imagenew, $imagesrc, $border, 0, 0, 0, $imagewidth - ($border * 2), $imageheight , $origw, $origh);
    } else {
        $border = floor(($imageheight - ($imagewidth * $origh / $origw)) / 2);
        imagecopyresampled($imagenew, $imagesrc, 0, $border, 0, 0, $imagewidth , $imageheight - ($border * 2) , $origw, $origh);
    }
    $imagenewname = $imagenewname.'.png';
    imagepng($imagenew, $imagenewname, 9);

    imagedestroy($imagenew);
    imagedestroy($imagesrc);
    return $imagenewname;
}

function is_file_a_resource($filepath){ 
    return (preg_match(REGEX_EXTERNAL_EXTENSIONS, $filepath) > 0); 
}
function is_file_an_image($filepath) {
    return (preg_match(REGEX_IMAGE_EXTENSIONS, $filepath) > 0);
}

function resize_image_crop($image, $imagenewname, $imagewidth, $imageheight, $transparent) {
    global $CFG;
    if (!file_exists($image)) {
        return false;
    }
    $size = getimagesize($image);
    $origw = $size[0];
    $origh = $size[1];
    $ratiosrc = $origw / $origh;

    $ratiotarget = $imagewidth / $imageheight;

    $imagenew = imagecreatetruecolor($imagewidth, $imageheight);

    if (!$transparent) {
        $bgcolour = imagecolorallocate($imagenew,
                $CFG->block_oppia_mobile_export_thumb_bg_r,
                $CFG->block_oppia_mobile_export_thumb_bg_g,
                $CFG->block_oppia_mobile_export_thumb_bg_b);
        imagefill($imagenew, 0, 0, $bgcolour);
    } else {
        imagealphablending( $imagenew, false );
        imagesavealpha($imagenew, true);
    }

    switch($size['mime']) {
        case 'image/jpeg':
            $imagesrc = imagecreatefromjpeg($image);
            break;
        case 'image/png':
            $imagesrc = imagecreatefrompng($image);
            break;
        case 'image/gif':
            $imagesrc = imagecreatefromgif($image);
            break;
    }

    if ($ratiosrc > $ratiotarget) {
        $crop = floor(($origw - ($origh * $imagewidth / $imageheight)) / 2);
        imagecopyresampled($imagenew, $imagesrc, 0, 0, $crop, 0, $imagewidth, $imageheight, $origw - ( 2 * $crop), $origh);
    } else {
        $crop = floor(($origh - ($origw * $imageheight / $imagewidth)) / 2);
        imagecopyresampled($imagenew, $imagesrc, 0, 0,  0, $crop,  $imagewidth, $imageheight, $origw, $origh - (2 * $crop));
    }

    $imagenewname = $imagenewname.'.png';
    imagepng($imagenew, $imagenewname, 9);

    imagedestroy($imagenew);
    imagedestroy($imagesrc);
    return $imagenewname;
}

function zip_oppia_course($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        echo '<span class="export-error">Unable to load Zip extension (is it correctly installed and configured in the Moodle server?)</span><br/>';
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        echo '<span class="export-error">Couldn\'t create Zip archive</span><br/>';
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $file = str_replace('\\', '/', realpath($file));

            if (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}


function libxml_display_error($error) {
    $return = "<br/>\n";
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= '<strong>Warning'.$error->code.OPPIA_HTML_STRONG_END.': ';
            break;
        case LIBXML_ERR_ERROR:
            $return .= '<strong>Error'.$error->code.OPPIA_HTML_STRONG_END.': ';
            break;
        case LIBXML_ERR_FATAL:
            $return .= '<strong>Fatal Error'.$error->code.OPPIA_HTML_STRONG_END.': ';
            break;
    }
    $return .= trim($error->message);
    if ($error->file) {
        $return .= " in <strong>$error->file</strong>";
    }
    $return .= " on line <strong>$error->line</strong>\n";

    return $return;
}

function libxml_display_errors() {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        print libxml_display_error($error);
    }
    libxml_clear_errors();
}

function flush_buffers() {
    ob_end_flush();
    @ob_flush();
    @flush();
    ob_start();
}

function recurse_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}


function create_dom_element_from_template($doc, $templatename, $params) {
    global $OUTPUT;

    $elemhtml = $OUTPUT->render_from_template($templatename, $params);
    $dom = new DOMDocument();
    $dom->loadHTML($elemhtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    return $doc->importNode($dom->documentElement, true);
}


function get_compiled_css_theme($pluginroot, $theme) {
    $stylesroot = $pluginroot.STYLES_DIR;
    $themescss = file_get_contents($stylesroot.STYLES_THEMES_DIR.$theme.".scss");
    $scsspath = $stylesroot.STYLES_BASE_SCSS;

    $compiler = new core_scss();
    $compiler->prepend_raw_scss($themescss);
    $compiler->set_file($scsspath);

    $extrafilename = $stylesroot.STYLES_THEMES_DIR.$theme.STYLES_EXTRA_SUFFIX .'.scss';
    if (file_exists($extrafilename)) {
        $extrascss = file_get_contents($extrafilename);
        $compiler->append_raw_scss($extrascss);
    }

    $css = $compiler->to_css();
    return $css;
}

/**
 * Serve the files from the block file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function block_oppia_mobile_export_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    // Make sure the filearea is one of those used by the block.
    if ($filearea !== COURSE_EXPORT_FILEAREA) {
        return false;
    }

    require_login($course, true, $cm);

    $userid = array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // Then $args is empty => the path is '/'.
    } else {
        $filepath = '/'.implode('/', $args).'/'; // The $args contains elements of the filepath.
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, PLUGINNAME, $filearea, $userid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

function clean_up_exported_files($context, $userid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        PLUGINNAME,
        COURSE_EXPORT_FILEAREA,
        $userid
    );
    foreach ($files as $file) {
        $file->delete();
        unlink($file->get_filepath());
    }
}


function add_or_update_grade_boundary($modid, $grade, $message, $servid="default") {
    global $DB;

    if ($servid !== null) {
        $record = $DB->get_record_select(OPPIA_GRADE_BOUNDARY_TABLE,
            "modid=$modid and `grade`='$grade' and serverid='$servid'");
    } else {
        $record = $DB->get_record(OPPIA_GRADE_BOUNDARY_TABLE, array('modid' => $modid, 'grade' => $grade));
    }

    if ($record) {
        $DB->update_record(OPPIA_GRADE_BOUNDARY_TABLE,
            array('id' => $record->id, 'message' => $message));
    } else {
        $DB->insert_record(OPPIA_GRADE_BOUNDARY_TABLE,
            array('modid' => $modid, 'grade' => $grade, 'message' => $message, 'serverid' => $servid));
    }
}
function delete_grade_boundary($modid, $grade, $servid="default") {
    global $DB;
    $DB->delete_records(OPPIA_GRADE_BOUNDARY_TABLE,
        array(
            'modid' => $modid,
            'grade' => $grade,
            'serverid' => $servid
        )
    );
}
function get_grade_boundaries($modid, $servid="default") {
    global $DB;
    $records = $DB->get_records_select(OPPIA_GRADE_BOUNDARY_TABLE,
        "modid=$modid and serverid='$servid'");
    if ($records) {
        return $records;
    } else {
        // Try if there is a non-server value saved.
        $records = $DB->get_records_select(OPPIA_GRADE_BOUNDARY_TABLE,
            "modid=$modid and serverid=''");
        if ($records) {
            return $records;
        } else {
            return array();
        }
    }
}

function sort_grade_boundaries_descending($gb1, $gb2) {
    return $gb2->grade - $gb1->grade;
}
