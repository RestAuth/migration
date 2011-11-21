<?php

global $IP;
require_once( "$IP/includes/specials/SpecialListusers.php" );
class RestAuthExportForm extends HTMLForm {
	function __construct() {
		global $wgRestAuthService;
		$descriptor = array(
			'export-service' => array(
				'type' => 'check',
				'label-message' => 'service-label',
				'help-message' => 'service-help',
				'default' => !is_null($wgRestAuthService),
				'disabled' => is_null($wgRestAuthService),
			),
			'export-users' => array(
				'type' => 'check',
				'label-message' => 'users-label',
				'help-message' => 'users-help',
				'default' => true,
			),
			'export-properties' => array(
				'type' => 'check',
				'label-message' => 'properties-label',
				'help-message' => 'properties-help',
				'default' => true,
			),
			'export-groups' => array(
				'type' => 'check',
				'label-message' => 'groups-label',
				'help-message' => 'groups-help',
				'default' => true,
			),
			'exclude-groups' => array(
				'type' => 'text',
				'label-message' => 'exclude-groups-label',
				'help-message' => 'exclude-groups-help',
				'default' => 'bot',
			),
		);
		parent::__construct( $descriptor );
		global $wgTitle;
		$this->setTitle( $wgTitle );
	}
}

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

		$form = new RestAuthExportForm();
		$form->loadData();
		if ($wgRequest->wasPosted()) {
			$this->outputRawData($form->mFieldData);
		} else {
			$form->displayForm();
		}
	}
	private function getServiceData($formdata) {
		global $wgRestAuthService, $wgRestAuthServicePassword;
		return array($wgRestAuthService => array('password' => $wgRestAuthServicePassword));
	}
	private function getUserData($formdata, $groups) {
		$excluded_groups = explode(',', $formdata['exclude-groups']);
		$users = array();
		# get list of users:
		$usersPager = new UsersPager();
		$usersPager->doQuery();
		$usersResult = $usersPager->getResult();
		
		$usersResult->seek( 0 );
		for ( $i = 0; $i < $usersResult->numRows(); $i++ ) {
			$row = $usersResult->fetchObject();
			$user = User::newFromId( $row->user_id );
			$user->load();
			
			if (count(array_intersect($user->getEffectiveGroups(), $excluded_groups))>0 ) {
				continue;
			}

			# add user to users result:
			$users[$user->getName()] = array();

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

			if ($formdata['export-properties']) {
				$users[$user->getName()]['properties'] = $this->getUserProperties($formdata, $user);
			}

			if ($formdata['export-groups']) {
				# get groups (see UsersPager::getGroups()):
				$usergroups = array_diff( $user->getEffectiveGroups(), $user->getImplicitGroups() );
				foreach ($usergroups as $usergroup) {
					if (!in_array($usergroup, $excluded_groups)) {
						$groups[$usergroup]['users'][] = $user->getName();
					}
				}
			}
		}

		return $users;
	}
	
	private function getUserProperties($formdata, $user) {
		global $wgRestAuthIgnoredOptions, $wgRestAuthGlobalOptions;
		$properties = array();

		// set settings (email and real name):
		$prop = fnRestAuthGetOptionName('email');
		if (!in_array( $prop, $wgRestAuthIgnoredOptions ) && $user->getEmail()) {
			$properties[$prop] = $user->getEmail();
			
			// email confirmed?
			if (array_key_exists('email', $wgRestAuthGlobalOptions)) {
				$confirmed_prop = 'email confirmed';
			} else {
				$confirmed_prop = 'mediawiki email confirmed';
			}
			if ($user->isEmailConfirmed()){
				$properties[$confirmed_prop] = '1';
			} else {
				$properties[$confirmed_prop] = '0';
			}
		}

		$prop = fnRestAuthGetOptionName( 'real name' );
		if (!in_array( $prop, $wgRestAuthIgnoredOptions ) && $user->getRealName()) {
			$properties[$prop] = $user->getRealName();
		}

		// set options (everything else)
		foreach ($user->getOptions() as $key => $value) {
			if ( in_array( $key, $wgRestAuthIgnoredOptions ) ) {
				continue;
			}

			$prop = fnRestAuthGetOptionName($key);
			$properties[$prop] = $value;
		}

		return $properties;
	}

	private function getGroupData($formdata) {
		$excluded = explode(',', $formdata['exclude-groups']);
		$groups = array();
		$all_groups = User::getAllGroups();
		foreach ($all_groups as $group) {
			if (!in_array($group, $excluded)) {
				$groups[$group] = array();
			}
		}
		return $groups;
	}

	private function outputRawData($formdata) {
		global $wgRequest, $wgOut, $wgUser;

		if ( !$this->userCanExecute($wgUser) ) {
			$this->displayRestrictionError();
			return;
		}
		if ( !$wgRequest->wasPosted() ) {
			$wgOut->showErrorPage( 'mustpost-header', 'mustpost-text' );
			return;
		}

		$result = array();

		$users = array();
		if ($formdata['export-groups']) {
			$result['groups'] = $this->getGroupData($formdata);
		}

		if ($formdata['export-service']) {
			$result['services'] = $this->getServiceData($formdata);
		}

		if ($formdata['export-users']) {
			$result['users'] = $this->getUserData($formdata, &$result['groups']);
		}

		die(json_encode($result));
	}
}
