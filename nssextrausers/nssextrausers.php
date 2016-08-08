<?php

class nssextrausers extends FroxlorPlugin {
	public $name = 'nss_extrausers';
	public $version = '1.1';
	
	protected $defaultpath = '/var/lib/extrausers/';

	public function install() {
		$version = $this->settings->version();
		if (empty($version)) {
			$this->log(LOG_INFO, 'Adding settings...');
			$this->settings->AddNew('enabled', '0');
			$this->settings->AddNew('path', $this->defaultpath);
			$version = '1.0';
		}
		if ($version == '1.0') {
			$version = '1.1';
		}
		$this->settings->version($version);
		return true;
	}
	

	public function eventServerSettings($eventData) {
		$ui_fields = array(
			'plugins_nssextrausers_enabled' => array(
				'label' => $this->text->getText('serversettings.enabled'),
				'settinggroup' => 'nssextrausers',
				'varname' => 'enabled',
				'type' => 'bool',
				'default' => false,
				'save_method' => 'storeSettingField',
			),
			'plugins_nssextrausers_path' => array(
				'label' => $this->text->getText('serversettings.path'),
				'settinggroup' => 'nssextrausers',
				'varname' => 'path',
				'type' => 'string',
				'string_type' => 'path',
				'default' => $this->defaultpath,
				'save_method' => 'storeSettingField'
			)
		);
		
		$eventData['data']['nssextrausers'] = array(
			'title' => $this->text->getText('serversettings.title'),
			'fields' => $ui_fields
		);
	}
		
	public function eventCronTaskRunPre($eventData) {
		if ($this->settings->Get('enabled') != '1') {
			return;
		}
		$type = $eventData['row']['type'];
		switch ($type) {
			// Rewrite webserver configs
			case '1':
				$this->_checkUsers();
				break;
		}
	}
	
	public function eventCronTaskRunPost($eventData) {
		if ($this->settings->Get('enabled') != '1') {
			return;
		}
		$type = $eventData['row']['type'];
		switch ($type) {
			// Delete a user
			case '6':
				$this->_recreate_extrausers();
				break;
		}
	}
	
	
	protected function _checkUsers() {
		$recreate = false;
		
		$result_stmt = Database::query("
			SELECT `c`.`customerid`, `c`.`loginname`, `c`.`guid`, `c`.`documentroot` 
			FROM `" . TABLE_PANEL_CUSTOMERS . "` `c`
			ORDER BY `customerid` ASC
		");
		
		while($row = $result_stmt->fetch(PDO::FETCH_ASSOC)) {
			$loginname = $row['loginname'];
			if (posix_getpwnam($loginname) === false) {
				$this->log(LOG_INFO, "login '$loginname' missing");
				$recreate = true;
			}
		}
		
		if ($recreate) {
			$this->_recreate_extrausers();
		} else {
			$this->log(LOG_INFO, 'All users found, no need for recreating');
		}
	}
	
	protected function _recreate_extrausers() {
		$extrauserspath = makeCorrectDir(Settings::Get('nssextrausers.path'));
		if ($extrauserspath == '/etc/' ) {
			$this->log(LOG_ERR, 'Invalid nss-extrausers path');
			return;
		} elseif ($extrauserspath == '/') {
			$extrauserspath = $this->defaultpath;
			$this->log(LOG_WARNING, 'Empty nss-extrausers path, using default '.$extrauserspath);
		}
		$this->log(LOG_INFO, 'Regenerating of passwd, shadow, group, gshadow in '.$extrauserspath);
		
		$userlines = array();
		$shadowlines = array();
		$grouplines = array();
		$gshadowlines = array();
		
		$httpuser = Settings::Get('system.httpuser');
		
		
		$result_stmt = Database::query("
			SELECT `c`.`customerid`, `c`.`loginname`, `c`.`guid`, `c`.`documentroot` 
			FROM `" . TABLE_PANEL_CUSTOMERS . "` `c`
			ORDER BY `customerid` ASC
		");

		while($row = $result_stmt->fetch(PDO::FETCH_ASSOC)) {
			$username = $row['loginname'];
			$password = '!';
			$uid = (integer)$row['guid'];
			$gid = (integer)$row['guid'];
			$home = makeCorrectDir($row['documentroot']);
			$shell = '/bin/false';

			$lstchg = (integer)(time() / 86400);

			$userlines[] = "$username:x:$uid:$gid::$home:$shell\n";
			$shadowlines[] = "$username:$password:$lstchg:0:99999:7:::\n";
			$grouplines[] = "$username:x:$gid:$httpuser\n";
			$gshadowlines[] = "$username:!::\n";
		}

		// Default output to /var/lib/extrausers
		$path = makeCorrectDir($extrauserspath);
		$this->_writeFile($path.'passwd',$userlines, 0644);
		$this->_writeFile($path.'shadow',$shadowlines, 0640, "shadow");

		$this->_writeFile($path.'group',$grouplines, 0644);
		$this->_writeFile($path.'gshadow',$gshadowlines, 0640, "shadow");
	}


	protected function _writeFile($filename, $data, $mask, $group = NULL) {
		$oldumask = umask();
		umask( (~$mask) & 0777);
		if (file_put_contents($filename, $data, LOCK_EX) !== false) {
			chmod($filename, $mask);
			if ($group) {
				chgrp($filename, $group);
			}
		}
		umask($oldumask);
	}
	
}
