<?php
$messages = array();
$aliases = array();
 
/* *** English *** */
$aliases['en'] = array(
	'RestAuthExport' => array( 'MyExtension' ),
);
$messages['en'] = array(
	'restauthexport' => 'RestAuth export',
	'restauthexport-desc' => "Extension's description",
	'service-label' => 'Export RestAuth service',
	'service-help' => 'Use $wgRestAuthService and $wgRestAuthServicePassword from the main RestAuth extension to automatically export that service',
	'users-label' => 'Export local users',
	'users-help' => 'Export all users found in this wiki',
	'properties-label' => 'Export local properties',
	'properties-help' => 'Export properties of local users',
	'groups-label' => 'Export local groups',
	'groups-help' => 'Export all groups',
	'exclude-groups-label' => 'Exclude groups',
	'exclude-groups-help' => 'Comma-seperated list of groups to exclude from output. Any user that is in any of the named groups will be skipped.',

);
 
/* *** German (Deutsch) *** */
$aliases['de'] = array(
	'RestAuthExport' => array( 'RestAuthExport', 'RestAuthExport' ),
);
$messages['de'] = array(
	'restauthexport' => 'RestAuth export',
	'restauthexport-desc' => 'Beschreibung der Erweiterung',
);
