<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/RestAuthExport/RestAuthExport.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
        'name' => 'RestAuthExport',
        'author' => 'Mathias Ertl <mati@restauth.net>',
        'url' => 'https://restauth.net/wiki/MediaWiki',
        'description' => 'Export user and group-data to the [https://server.restauth.net/migrate/import-format.html RestAuth import data format]',
        'descriptionmsg' => 'restauthexport-desc',
        'version' => '0.1',
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['SpecialRestAuthExport'] = $dir . 'SpecialRestAuthExport.php'; # Location of the SpecialRestAuthExport class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['RestAuthExport'] = $dir . 'RestAuthExport.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgSpecialPages['RestAuthExport'] = 'SpecialRestAuthExport'; # Tell MediaWiki about the new special page and its class name
