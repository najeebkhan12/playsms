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

/**
 * Normalize a SignalWire space host.
 *
 * Accepts "example", "example.signalwire.com" or a full https URL.
 *
 * @param string $space
 * @return string
 */
function signalwire_normalize_space($space)
{
	$space = trim((string) $space);
	$space = preg_replace('#^https?://#i', '', $space);
	$space = preg_replace('#/.*$#', '', $space);
	$space = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $space);
	if ($space === '') {
		return '';
	}
	if (!preg_match('/\.signalwire\.com$/i', $space) && strpos($space, '.') === false) {
		$space .= '.signalwire.com';
	}

	return $space;
}

/**
 * Build the Compatibility API Messages endpoint.
 *
 * @param string $space
 * @param string $project_id
 * @return string
 */
function signalwire_messages_url($space, $project_id)
{
	$space = signalwire_normalize_space($space);
	$project_id = preg_replace('/[^a-zA-Z0-9\-]/', '', trim((string) $project_id));
	if (!($space && $project_id)) {
		return '';
	}

	return 'https://' . $space . '/api/laml/2010-04-01/Accounts/' . rawurlencode($project_id) . '/Messages.json';
}

/**
 * Force E.164 so SignalWire accepts From/To.
 *
 * @param string $number
 * @return string
 */
function signalwire_format_e164($number)
{
	$number = preg_replace('/[^\d\+]/', '', trim((string) $number));
	if ($number === '') {
		return '';
	}
	if ($number[0] !== '+') {
		$number = '+' . ltrim($number, '0');
	}

	return $number;
}

/**
 * Append authcode and SMSC to the callback URL used as StatusCallback.
 *
 * @param string $callback_url
 * @param string $smsc
 * @return string
 */
function signalwire_callback_url_with_params($callback_url, $smsc)
{
	global $plugin_config;

	$callback_url = trim((string) $callback_url);
	if ($callback_url === '') {
		return '';
	}

	$callback_args = [
		'smsc' => $smsc,
	];
	if (!empty($plugin_config['signalwire']['callback_authcode'])) {
		$callback_args['authcode'] = $plugin_config['signalwire']['callback_authcode'];
	}

	$separator = (strpos($callback_url, '?') === false) ? '?' : '&';

	return $callback_url . $separator . http_build_query($callback_args);
}

/**
 * This function hooks sendsms() and called by daemon sendsmsd
 *
 * @param string $smsc Selected SMSC
 * @param string $sms_sender SMS sender ID
 * @param string $sms_footer SMS message footer
 * @param string $sms_to Mobile phone number
 * @param string $sms_msg SMS message
 * @param int $uid User ID
 * @param int $gpid Group phonebook ID
 * @param int $smslog_id SMS Log ID
 * @param string $sms_type Type of SMS
 * @param int $unicode Indicate that the SMS message is in unicode
 * @return bool true if delivery successful
 */
function signalwire_hook_sendsms($smsc, $sms_sender, $sms_footer, $sms_to, $sms_msg, $uid = 0, $gpid = 0, $smslog_id = 0, $sms_type = 'text', $unicode = 0)
{
	global $plugin_config;

	// override $plugin_config by $plugin_config from selected SMSC
	$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);

	$module_sender = isset($plugin_config['signalwire']['module_sender']) && core_sanitize_sender($plugin_config['signalwire']['module_sender'])
		? core_sanitize_sender($plugin_config['signalwire']['module_sender']) : '';
	$sms_sender = signalwire_format_e164($module_sender ?: core_sanitize_sender($sms_sender));
	$sms_to = signalwire_format_e164(core_sanitize_mobile($sms_to));
	$sms_footer = core_sanitize_footer($sms_footer);
	$sms_msg = stripslashes($sms_msg . $sms_footer);

	$space = $plugin_config['signalwire']['space'] ?? '';
	$project_id = $plugin_config['signalwire']['project_id'] ?? '';
	$api_token = $plugin_config['signalwire']['api_token'] ?? '';
	$api_url = signalwire_messages_url($space, $project_id);

	_log("enter smsc:" . $smsc . " smslog_id:" . $smslog_id . " uid:" . $uid . " from:" . $sms_sender . " to:" . $sms_to, 3, "signalwire_hook_sendsms");

	if ($api_url && $api_token && $sms_sender && $sms_to && $sms_msg) {
		$params = [
			'From' => $sms_sender,
			'To' => $sms_to,
			'Body' => $sms_msg,
		];

		$callback_url = signalwire_callback_url_with_params($plugin_config['signalwire']['callback_url'], $smsc);
		if ($callback_url) {
			$params['StatusCallback'] = $callback_url;
		}

		$url = $api_url . '?' . http_build_query($params);
		_log("send url:[" . $api_url . "] from:" . $sms_sender . " to:" . $sms_to, 3, "signalwire_hook_sendsms");

		$header = "Content-type: application/x-www-form-urlencoded\r\nAuthorization: Basic " . base64_encode($project_id . ':' . $api_token);
		$response = core_get_contents($url, 'POST', $header);
		$json = json_decode((string) $response, true);
		$remote_id = (is_array($json) && !empty($json['sid'])) ? (string) $json['sid'] : '';

		if ($remote_id) {
			_log("sent smslog_id:" . $smslog_id . " remote_id:" . $remote_id . " status:" . ($json['status'] ?? '') . " smsc:" . $smsc, 2, "signalwire_hook_sendsms");
			if (dba_update(_DB_PREF_ . '_tblSMSOutgoing', ['remote_id' => $remote_id], ['smslog_id' => $smslog_id, 'flag_deleted' => 0])) {
				$p_status = 1;
				dlr($smslog_id, $uid, $p_status);
			} else {
				$p_status = 0;
				dlr($smslog_id, $uid, $p_status);
			}

			return true;
		}

		$error = '';
		if (is_array($json)) {
			$error = $json['message'] ?? $json['error'] ?? '';
			if (!empty($json['code'])) {
				$error = trim($error . ' code:' . $json['code']);
			}
		}
		if ($error === '') {
			$error = trim((string) $response);
		}

		_log("failed smslog_id:" . $smslog_id . " response:[" . $error . "] smsc:" . $smsc, 2, "signalwire_hook_sendsms");
	} else {
		_log("failed missing config/sender/to/msg smslog_id:" . $smslog_id . " space:" . signalwire_normalize_space($space) . " from:" . $sms_sender . " to:" . $sms_to . " smsc:" . $smsc, 2, "signalwire_hook_sendsms");
	}

	$p_status = 2;
	dlr($smslog_id, $uid, $p_status);

	return false;
}
