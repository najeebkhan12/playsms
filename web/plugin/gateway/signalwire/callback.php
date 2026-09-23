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

error_reporting(0);

// load callback init
if (!(isset($PLAYSMS_INIT_SKIP) && $PLAYSMS_INIT_SKIP === true) && is_file('../common/callback_init.php')) {
	include '../common/callback_init.php';
}

// log original request
_log($_REQUEST, 3, "signalwire callback");

$remote_id = $_REQUEST['MessageSid'] ?? $_REQUEST['SmsSid'] ?? $_REQUEST['id'] ?? '';
$status_raw = strtolower((string) ($_REQUEST['MessageStatus'] ?? $_REQUEST['SmsStatus'] ?? $_REQUEST['message_status'] ?? ''));
$from = $_REQUEST['From'] ?? $_REQUEST['from'] ?? '';
$to = $_REQUEST['To'] ?? $_REQUEST['to'] ?? '';
$message = $_REQUEST['Body'] ?? $_REQUEST['message'] ?? '';
$sms_datetime = core_get_datetime();
$sms_sender = $from ? core_sanitize_sender($from) : '';
$sms_receiver = $to ? core_sanitize_mobile($to) : '';
$smsc = isset($_REQUEST['smsc']) ? $_REQUEST['smsc'] : '';
$authcode = isset($_REQUEST['authcode']) && trim($_REQUEST['authcode']) ? trim($_REQUEST['authcode']) : '';

// validate authcode
if (!gateway_callback_auth('signalwire', 'callback_authcode', $authcode, $smsc)) {
	_log("error auth authcode:" . $authcode . " smsc:" . $smsc . " remote_id:" . $remote_id . " from:" . $sms_sender . " to:" . $sms_receiver . " content:[" . $message . "]", 2, "signalwire callback");

	ob_end_clean();
	echo 'ERROR AUTH ' . _PID_;
	exit();
}

// validate requests must be coming from callback servers
if (!gateway_callback_access('signalwire', 'callback_access', $smsc)) {
	_log("error forbidden authcode:" . $authcode . " smsc:" . $smsc . " remote_id:" . $remote_id . " from:" . $sms_sender . " to:" . $sms_receiver . " content:[" . $message . "]", 2, "signalwire callback");

	ob_end_clean();
	echo 'ERROR FORBIDDEN ' . _PID_;
	exit();
}

$dlr_statuses = [
	'queued',
	'accepted',
	'sending',
	'sent',
	'delivered',
	'undelivered',
	'failed',
	'canceled',
	'cancelled',
];

// handle DLR
if ($remote_id && in_array($status_raw, $dlr_statuses, true)) {
	$db_query = "SELECT uid,smslog_id FROM " . _DB_PREF_ . "_tblSMSOutgoing WHERE remote_id=? AND flag_deleted=0";
	$db_result = dba_query($db_query, [$remote_id]);
	if ($db_row = dba_fetch_array($db_result)) {
		$uid = (int) $db_row['uid'];
		$smslog_id = (int) $db_row['smslog_id'];
		switch ($status_raw) {
			case 'queued':
			case 'accepted':
			case 'sending':
			case 'sent':
				$p_status = 1; // sent
				break;
			case 'delivered':
				$p_status = 3; // delivered
				break;
			default:
				$p_status = 2; // failed
				break;
		}
		_log("dlr uid:" . $uid . " smslog_id:" . $smslog_id . " remote_id:" . $remote_id . " status:" . $status_raw . " p_status:" . $p_status, 2, "signalwire callback");

		dlr($smslog_id, $uid, $p_status);
	} else {
		_log("dlr ignored unknown remote_id:" . $remote_id . " status:" . $status_raw . " smsc:" . $smsc, 2, "signalwire callback");
	}

	ob_end_clean();
	echo 'OK ' . _PID_;
	exit();
}

// handle incoming SMS (ignore status callbacks that also include Body)
if ($remote_id && $sms_sender && $message && !in_array($status_raw, $dlr_statuses, true)) {
	_log("incoming smsc:" . $smsc . " remote_id:" . $remote_id . " from:" . $sms_sender . " to:" . $sms_receiver . " content:[" . $message . "]", 2, "signalwire callback");

	recvsms($sms_datetime, $sms_sender, $message, $sms_receiver, $smsc);

	ob_end_clean();
	echo 'OK ' . _PID_;
	exit();
}

ob_end_clean();
echo 'ERROR ' . _PID_;
