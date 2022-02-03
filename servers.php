<?php 

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/constants.php');
require_once($CFG->libdir.'/componentlib.class.php');
require_once($CFG->dirroot . PLUGINPATH . 'lib.php');
require_once($CFG->dirroot . PLUGINPATH . 'forms.php');

require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url(PLUGINPATH.'servers.php');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_title(get_string('pluginname', PLUGINNAME));
$PAGE->set_heading(get_string('oppia_block_export_servers', PLUGINNAME));

echo $OUTPUT->header();

$serverform = new OppiaServerForm();

if ($serverform->is_cancelled()) {
	// do nothing
} else if ($fromform = $serverform->get_data()) {
	$record = new stdClass();
	$record->servername = $fromform->server_ref;
	$record->url = trim($fromform->server_url);
	$record->moodleuserid = $USER->id;
	$DB->insert_record('block_oppia_mobile_server', $record, false);
}

$delete = optional_param('delete', 0, PARAM_INT);
if($delete != 0){
    $DB->delete_records('block_oppia_mobile_server', array('id' => $delete,
        'moodleuserid'=>$USER->id));
}


// get users current servers
$servers = get_oppiaservers();

echo "<h2>".get_string('servers_current', PLUGINNAME)."</h2>";

if (count($servers)== 0){
	echo "<p>".get_string('servers_none', PLUGINNAME)."</p>";
} else {
	echo "<ul>";
	foreach ($servers as $s){
		echo "<li>";
		echo $s->servername;
		echo " (<a href='".$s->url."' target='_blank'>".$s->url. "</a>)";
		echo " [<a href='?delete=".$s->id."'>". get_string('server_delete', PLUGINNAME)."</a>]";
		echo OPPIA_HTML_LI_END;
	}	
	echo "</ul>";
}

echo "<h2>".get_string('servers_add', PLUGINNAME)."</h2>";
$serverform->display();
echo $OUTPUT->footer();