<?php

global $IP;
require_once( "$IP/includes/specials/SpecialListusers.php" );

/**
 * RestAuthExportForm displays the main form displayed on the special page
 */
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
				'section' => 'service-section-header',
			),

			// users
			'export-users' => array(
				'type' => 'check',
				'label-message' => 'users-label',
				'help-message' => 'users-help',
				'default' => true,
				'section' => 'users-section-header',
			),
			'export-properties' => array(
				'type' => 'check',
				'label-message' => 'properties-label',
				'help-message' => 'properties-help',
				'default' => true,
				'section' => 'users-section-header',
			),

			// groups
			'export-groups' => array(
				'type' => 'check',
				'label-message' => 'groups-label',
				'help-message' => 'groups-help',
				'default' => true,
				'section' => 'groups-section-header',
			),
			'exclude-groups' => array(
				'type' => 'text',
				'label-message' => 'exclude-groups-label',
				'help-message' => 'exclude-groups-help',
				'default' => 'bot',
				'section' => 'groups-section-header',
			),
			'groups-service' => array(
				'type' => 'text',
				'label-message' => 'groups-service-label',
				'help-message' => 'groups-service-help',
				'default' => is_null($wgRestAuthService) ? '' : $wgRestAuthService,
				'section' => 'groups-section-header',
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
			$form->show();
		}
	}

	/**
	 * Get the service data. See also:
	 * 	https://server.restauth.net/migrate/import-format.html#services
	 */
	private function getServiceData($formdata) {
		global $wgRestAuthService, $wgRestAuthServicePassword;
		$return = array($wgRestAuthService => array());
		if (!is_null($wgRestAuthServicePassword)) {
			$return[$wgRestAuthService]['password'] = $wgRestAuthServicePassword;
		}
		return $return;
	}

	private function getUserData($formdata, $groups=array()) {
		$excluded_groups = explode(',', $formdata['exclude-groups']);
		$users = array();
		# get list of users:
		$usersPager = new UsersPager();
		$usersPager->mDefaultLimit = 1000000;
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
				if ($formdata['groups-service']) {
					$groups[$group]['service'] = $formdata['groups-service'];
				}
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
			if ($formdata['export-groups']) {
				$result['users'] = $this->getUserData($formdata, &$result['groups']);
			} else {
				$result['users'] = $this->getUserData($formdata);
			}
		}

		die(json_encode($result));
	}
}
