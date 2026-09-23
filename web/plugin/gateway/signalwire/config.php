<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

// gateway configuration in registry
$reg = gateway_get_registry('signalwire');

// plugin configuration
$plugin_config['signalwire'] = [
	'name' => 'signalwire',
	'space' => isset($reg['space']) && $reg['space'] ? $reg['space'] : '',
	'project_id' => isset($reg['project_id']) && $reg['project_id'] ? $reg['project_id'] : '',
	'api_token' => isset($reg['api_token']) && $reg['api_token'] ? $reg['api_token'] : '',
	'callback_url' => gateway_callback_url('signalwire'),
	'callback_authcode' => isset($reg['callback_authcode']) && $reg['callback_authcode'] ? $reg['callback_authcode'] : '',
	'callback_access' => isset($reg['callback_access']) && $reg['callback_access'] ? $reg['callback_access'] : '',
	'module_sender' => isset($reg['module_sender']) ? $reg['module_sender'] : '',
	'datetime_timezone' => isset($reg['datetime_timezone']) ? $reg['datetime_timezone'] : '',
];

// smsc configuration
$plugin_config['signalwire']['_smsc_config_'] = [
	'space' => _('SignalWire space'),
	'project_id' => _('Project ID'),
	'api_token' => _('API token'),
	'callback_authcode' => _('Callback authcode'),
	'callback_access' => _('Callback access'),
	'module_sender' => _('Module sender ID'),
	'datetime_timezone' => _('Module timezone')
];
