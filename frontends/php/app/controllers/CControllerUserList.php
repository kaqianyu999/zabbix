<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerUserList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>				'in alias,name,surname,type',
			'sortorder' =>			'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>			'in 1',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'filter_usrgrpid' =>	'db usrgrp.usrgrpid',
			'filter_alias' =>		'string',
			'filter_name' =>		'string',
			'filter_surname' =>		'string',
			'filter_type' =>		'in -1,'.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$filter_usrgrpid = $this->getInput('filter_usrgrpid', CProfile::get('web.users.filter.usrgrpid', 0));
		CProfile::update('web.users.filter.usrgrpid', $filter_usrgrpid, PROFILE_TYPE_ID);

		$sortfield = $this->getInput('sort', CProfile::get('web.users.php.sort', 'name'));
		$sortorder = $this->getInput('sortorder', CProfile::get('web.users.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.users.php.sort', $sortfield, PROFILE_TYPE_STR);
		CProfile::update('web.users.php.sortorder', $sortorder, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.user.filter_alias', $this->getInput('filter_alias', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.filter_surname', $this->getInput('filter_surname', ''), PROFILE_TYPE_STR);
			CProfile::update('web.user.filter_type', $this->getInput('filter_type', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.user.filter_alias');
			CProfile::delete('web.user.filter_name');
			CProfile::delete('web.user.filter_surname');
			CProfile::delete('web.user.filter_type');
		}

		$filter = [
			'alias' => CProfile::get('web.user.filter_alias', ''),
			'name' => CProfile::get('web.user.filter_name', ''),
			'surname' => CProfile::get('web.user.filter_surname', ''),
			'type' => CProfile::get('web.user.filter_type', -1)
		];

		$config = select_config();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'config' => $config,
			'sort' => $sortfield,
			'sortorder' => $sortorder,
			'filter' => $filter,
			'profileIdx' => 'web.user.filter',
			'active_tab' => CProfile::get('web.user.filter.active', 1),
			'userGroups' => API::UserGroup()->get([
				'output' => ['name']
			]),
			'filter_usrgrpid' => $filter_usrgrpid
		];

		order_result($data['userGroups'], 'name');

		$data['users'] = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname', 'type', 'autologout', 'attempt_failed'],
			'selectUsrgrps' => ['name', 'gui_access', 'users_status'],
			'search' => [
				'alias' => ($filter['alias'] === '') ? null : $filter['alias'],
				'name' => ($filter['name'] === '') ? null : $filter['name'],
				'surname' => ($filter['surname'] === '') ? null : $filter['surname']
			],
			'filter' => [
				'type' => ($filter['type'] == -1) ? null : $filter['type']
			],
			'usrgrpids' => ($filter_usrgrpid > 0) ? $filter_usrgrpid : null,
			'getAccess' => 1,
			'limit' => $config['search_limit'] + 1
		]);

		order_result($data['users'], $sortfield, $sortorder);

		$url = (new CUrl('zabbix.php'))->setArgument('action', 'user.list');

		$data['paging'] = getPagingLine($data['users'], $sortorder, $url);

		// set default lastaccess time to 0
		foreach ($data['users'] as $user) {
			$data['usersSessions'][$user['userid']] = ['lastaccess' => 0];
		}

		$dbSessions = DBselect(
			'SELECT s.userid,MAX(s.lastaccess) AS lastaccess,s.status'.
			' FROM sessions s'.
			' WHERE '.dbConditionInt('s.userid', zbx_objectValues($data['users'], 'userid')).
			' GROUP BY s.userid,s.status'
		);
		while ($session = DBfetch($dbSessions)) {
			if ($data['usersSessions'][$session['userid']]['lastaccess'] < $session['lastaccess']) {
				$data['usersSessions'][$session['userid']] = $session;
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of users'));
		$this->setResponse($response);
	}
}
