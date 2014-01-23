<?php 

abstract class mobile_activity {
	
	public $courseroot;
	public $id;
	public $section;
	public $md5;
	
	abstract function process();
	abstract function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc);
	abstract function export2print();
}

