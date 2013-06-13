<?php

class mobile_activity_resource extends mobile_activity {
	
	private $act = array();
	private $page_media = array();
	private $page_image = null;
	
	function process(){
		global $DB, $MOBILE_LANGS, $DEFAULT_LANG, $MEDIA;
		$cm= get_coursemodule_from_id('resource', $this->id);
		$resource = $DB->get_record('resource', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$context = get_context_instance(CONTEXT_MODULE, $cm->id);
		
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		global $DEFAULT_LANG;
	}
	
}
?>