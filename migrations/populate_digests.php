<?php 

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../constants.php');

$pluginroot = $CFG->dirroot . PLUGINPATH;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/componentlib.class.php');
require_once($pluginroot . 'lib.php');
require_once($pluginroot . 'activity/processor.php');

const SELECT_COURSES_DIGEST = 'name="coursepriority"';

function populate_digests_published_courses(){
	global $DB, $CFG;

	$courses_count = $DB->count_records_select(OPPIA_CONFIG_TABLE,
				SELECT_COURSES_DIGEST, null,
				'COUNT(DISTINCT modid)');

	$courses_result = $DB->get_recordset_select(OPPIA_CONFIG_TABLE,
				SELECT_COURSES_DIGEST, null, null,
				'DISTINCT modid');

	if (($courses_count > 0) && ($courses_result->valid())){
		echo '<p class="lead">' . $courses_count . " courses to process" . '</p>';
		
		foreach ($courses_result as $r) {
			$course_id = $r->modid;
			$course = $DB->get_record('course', array('id'=>$course_id));

			echo '<br>';
			echo '<h3>' . strip_tags($course->fullname) . '</h3>';

			$course_servers = $DB->get_recordset_select(OPPIA_CONFIG_TABLE,
				"modid='$course_id'", null, null,
				"DISTINCT serverid");

			foreach ($course_servers as $s) {
				$serverid = $s->serverid;
				echo '<h4>Server ID:' . $serverid . '</h4>';
				populate_digests_for_course($course, $course_id, $serverid);
			}
		}
	}
	else{
		echo "There were no previously exported courses to process.";
	}

	$courses_result->close();

}

function populate_digests_for_course($course, $course_id, $server_id){

	$modinfo = course_modinfo::instance($course_id);
	$sections = $modinfo->get_section_info_all();
	$mods = $modinfo->get_cms();

	$keep_html = get_oppiaconfig($course_id, 'keep_html', '', $server_id);
	$course->shortname = cleanShortname($course->shortname);

	deleteDir($pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp");
	deleteDir($pluginroot.OPPIA_OUTPUT_DIR."upgrade");

	mkdir($pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp/",0777, true);
	$course_root = $pluginroot.OPPIA_OUTPUT_DIR."upgrade"."/temp/".strtolower($course->shortname);
	mkdir($course_root,0777);
	mkdir($course_root."/images",0777);
	$fh = fopen($course_root."/images/.nomedia", 'w');
	fclose($fh);
	mkdir($course_root."/resources",0777);
	$fh = fopen($course_root."/resources/.nomedia", 'w');
	fclose($fh);

	$processor = new ActivityProcessor(array(
		'course_root' => $course_root,
		'server_id' => $server_id,
		'course_id' => $course_id,
		'course_shortname' => $course->shortname,
		'versionid' => '0',
		'keep_html' => $keep_html
	));

	echo '<div class="oppia_export_section py-3">';

	$sect_orderno = 1;
	foreach($sections as $sect) {
		flush_buffers();
		// We avoid the topic0 as is not a section as the rest
		if ($sect->section == 0) {
		    $sectionTitle = "Intro";
		}
		else{
			$sectionTitle = strip_tags($sect->summary);
			// If the course has no summary, we try to use the section name
			if ($sectionTitle == "") {
				$sectionTitle = strip_tags($sect->name);
			}
		}

		echo "<h4>".get_string('export_section_title', PLUGINNAME, $sectionTitle)."</h4>";	

		$sectionmods = explode(",", $sect->sequence);
		$act_orderno = 1;
		$processor->set_current_section($sect_orderno);

		foreach ($sectionmods as $modnumber) {
			if ($modnumber == "" || $modnumber === false){
				continue;
			}
			$mod = $mods[$modnumber];
			echo '<div class="step"><strong>'.$mod->name.'</strong>'.OPPIA_HTML_BR;
			$activity = $processor->process_activity($mod, $sect, $act_orderno);
			if ($activity != null){
				$nquestions = null;
				if (($mod->modname == 'quiz') || ($mod->modname == 'feedback')){
					$nquestions = $activity->get_no_questions();
				}
				echo 'Digest: <span class="alert alert-warning mt-3 py-1">' . $activity->md5 . '</span>';
				save_activity_digest($course_id, $mod->id, $activity->md5, $server_id, $nquestions);
				$act_orderno++;
			}
			echo '</div>';
		}
		if ($act_orderno > 1){
			$sect_orderno++;
		}
	
	}
	echo '</div>';
}

function save_activity_digest($courseid, $modid, $digest, $serverid, $nquestions=null){
	global $DB;
    $date = new DateTime();
    $timestamp = $date->getTimestamp();
    $DB->insert_record(OPPIA_DIGEST_TABLE,
        array(
                'courseid'=>$courseid,
                'modid'=>$modid,
                'digest'=>$digest,
                'updated'=>$timestamp,
                'serverid'=>$serverid,
                'nquestions'=>$nquestions)
        );
}
