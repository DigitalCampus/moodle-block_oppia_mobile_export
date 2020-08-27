<?php 
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/componentlib.class.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/lib.php');
require_once($CFG->dirroot . '/blocks/oppia_mobile_export/forms.php');


require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/oppia_mobile_export/servers.php');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('pluginname','block_oppia_mobile_export'));
$PAGE->set_heading(get_string('oppia_block_export_servers','block_oppia_mobile_export'));

echo $OUTPUT->header();

$serverform = new oppiaserver_form();

if ($serverform->is_cancelled()) {
	// do nothing
} else if ($fromform = $serverform->get_data()) {
	$record = new stdClass();
	$record->servername = $fromform->server_ref;
	$record->url = $fromform->server_url;
	$record->moodleuserid = $USER->id;
	$DB->insert_record('block_oppia_mobile_server', $record, false);
}

// get users current servers
$servers = get_oppiaservers();

echo "<h2>".get_string('servers_current','block_oppia_mobile_export')."</h2>";

if (count($servers)== 0){
	echo "<p>".get_string('servers_none','block_oppia_mobile_export')."</p>";
} else {
	echo "<ul>";
	foreach ($servers as $s){
		echo "<li>";
		echo $s->servername;
		echo " (<a href='".$s->url."' target='_blank'>".$s->url. "</a>)";
		echo "</li>";
	}	
	echo "</ul>";
}

echo "<h2>".get_string('servers_add','block_oppia_mobile_export')."</h2>";
$serverform->display();
echo $OUTPUT->footer();