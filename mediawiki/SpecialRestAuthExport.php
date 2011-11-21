<?php

global $IP;
require_once( "$IP/includes/specials/SpecialListusers.php" );

class SpecialRestAuthExport extends SpecialPage {
	function __construct() {
		parent::__construct( 'RestAuthExport', 'editinterface' );
		wfLoadExtensionMessages('RestAuthExport');
	}
 
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser;
		if ( !$this->userCanExecute($wgUser) ) {
			$this->displayRestrictionError();
			return;
		}
 
		$this->setHeaders();
 
		# Get request data from, e.g.
		 $param = $wgRequest->getText('param');

		# Do stuff
		# ...
		#$output="Hello world!";
		#$wgOut->addWikiText( $output );
		$this->outputRawData();
	}

	private function outputRawData() {
		global $wgRequest, $wgOut, $wgUser;
		global $wgRestAuthIgnoredOptions, $wgRestAuthGlobalOptions;

		if ( !$this->userCanExecute($wgUser) ) {
			$this->displayRestrictionError();
			return;
		}
#		if ( !$wgRequest->wasPosted() ) {
#			$wgOut->showErrorPage( 'mustpost-header', 'mustpost-text' );
#			return;
#		}
		$users = array();
		$groups = array();
		$result = array();

		# loop through all groups to get a list of all groups to add empty groups:
		$all_groups = User::getAllGroups();
		foreach ($all_groups as $group) {
			if (!array_key_exists($group, $groups)) {
				$groups[$group] = array('users' => array());
			}
		}
		
		# get list of users:
		$usersPager = new UsersPager();
		$usersPager->doQuery();
		$usersResult = $usersPager->getResult();
		
		$usersResult->seek( 0 );
		for ( $i = 0; $i < $usersResult->numRows(); $i++ ) {
			$row = $usersResult->fetchObject();
			$user = User::newFromId( $row->user_id );
			$user->load();


			# add user to users result:
			$users[$user->getName()] = array('properties'=>array());

			# add the password hash:
			if (!is_null($user->mPassword) && strpos($user->mPassword, ':') !== false) {
				$pwd_hash_parts = explode(':', $user->mPassword);
				if ($pwd_hash_parts[1] === 'A') {
					# pure md5 hash
					$password = array(
						'algorithm' => 'md5',
						'hash'      => $pwd_hash_parts[2],
					);
				} else {
					$password = array(
						'algorithm' => 'mediawiki',
						'salt'      => $pwd_hash_parts[2],
						'hash'      => $pwd_hash_parts[3],
					);
				}
				$users[$user->getName()]['password'] = $password;
			}

			// set settings (email and real name):
			$prop = fnRestAuthGetOptionName('email');
			if (!in_array( $prop, $wgRestAuthIgnoredOptions ) && $user->getEmail()) {
				$users[$user->getName()]['properties'][$prop] = $user->getEmail();
				
				// email confirmed?
				if (array_key_exists('email', $wgRestAuthGlobalOptions)) {
					$confirmed_prop = 'email confirmed';
				} else {
					$confirmed_prop = 'mediawiki email confirmed';
				}
				if ($user->isEmailConfirmed()){
					$users[$user->getName()]['properties'][$confirmed_prop] = '1';
				} else {
					$users[$user->getName()]['properties'][$confirmed_prop] = '0';
				}

			}

			// set options (everything else)
			foreach ($user->getOptions() as $key => $value) {
				if ( in_array( $key, $wgRestAuthIgnoredOptions ) ) {
					continue;
				}

				$prop = fnRestAuthGetOptionName($key);
				$users[$user->getName()]['properties'][$prop] = $value;
			}

			$prop = fnRestAuthGetOptionName( 'real name' );
			if (!in_array( $prop, $wgRestAuthIgnoredOptions ) && $user->getRealName()) {
				$users[$user->getName()]['properties'][$prop] = $user->getRealName();
			}


			# get groups (see UsersPager::getGroups()):
			$usergroups = array_diff( $user->getEffectiveGroups(), $user->getImplicitGroups() );
			foreach ($usergroups as $usergroup) {
				$groups[$usergroup]['users'][] = $user->getName();
			}
		}

		if (count($users)>0) {
			$result['users'] = $users;
		}
		if (count($groups)>0) {
			$result['groups'] = $groups;
		}
		global $wgRestAuthService, $wgRestAuthServicePassword;
		$result['services'] = array($wgRestAuthService => array('password' => $wgRestAuthServicePassword));

		die(json_encode($result));
	}
}
