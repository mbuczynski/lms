<?php

/*
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2013 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id: nodelocks.php,v 1.1 2012/04/07 23:12:01 chilek Exp $
 */

function getNodeLocks($nodeid) {
	global $SMARTY, $DB;

	$result = new xajaxResponse();
	$nodelocks = NULL;
	$locks = $DB->GetAll('SELECT id, days, fromsec, tosec FROM nodelocks WHERE nodeid = ? ORDER BY id', array($nodeid));
	if ($locks)
		foreach ($locks as $lock) {
			$fromsec = intval($lock['fromsec']);
			$tosec = intval($lock['tosec']);
			$days = intval($lock['days']);
			$lockdays = array();
			for ($i = 0; $i < 7; $i++)
				if ($days & (1 << $i))
					$lockdays[$i] = 1;
			$nodelocks[] = array('id' => $lock['id'], 'days' => $lockdays, 'fhour' => intval($fromsec / 3600), 'fminute' => intval(($fromsec % 3600) / 60),
					'thour' => intval($tosec / 3600), 'tminute' => intval(($tosec % 3600) / 60));
		}
	$SMARTY->assign('nodelocks', $nodelocks);
	$nodelocklist = $SMARTY->fetch('nodelocklist.html');

	$result->assign('nodelocktable', 'innerHTML', $nodelocklist);

	return $result;
}

function addNodeLock($nodeid, $params) {
	global $DB;

	$result = new xajaxResponse();

	if (empty($params['days']))
		return $result;
	$days = 0;
	foreach ($params['days'] as $key => $value)
		$days += (1 << $key);
	$fromsec = $params['fhour'] * 3600 + $params['fminute'] * 60;
	$tosec = $params['thour'] * 3600 + $params['tminute'] * 60;
	if ($fromsec >= $tosec || !$days)
		return $result;

	$DB->Execute('INSERT INTO nodelocks (nodeid, days, fromsec, tosec) VALUES (?, ?, ?, ?)', array($nodeid, $days, $fromsec, $tosec));
	$result->call('xajax_getNodeLocks', $nodeid);
	$result->assign('nodelockaddlink', 'disabled', false);

	return $result;
}

function delNodeLock($nodeid, $id) {
	global $DB;

	$result = new xajaxResponse();
	$DB->Execute('DELETE FROM nodelocks WHERE id = ?', array($id));
	$result->call('xajax_getNodeLocks', $nodeid);
	$result->assign('nodelocktable', 'disabled', false);

	return $result;
}

function getThroughput($ip) {
	global $CONFIG;

	$result = new xajaxResponse();
	$cmd = get_conf('phpui.live_traffic_helper');
	if (empty($cmd))
		return $result;

	$cmd = str_replace('%i', $ip, $cmd);
	exec($cmd, $output);
	if (!is_array($output) && count($output) != 1)
		return $result;

	$stats = explode(' ', $output[0]);
	if (count($stats) != 4)
		return $result;

	array_walk($stats, intval);
	foreach (array(0, 2) as $idx)
		if ($stats[$idx] > 1000000)
			$stats[$idx] = (round(floatval($stats[$idx]) / 1000000.0, 2)) . ' Mbit/s';
		elseif ($stats[$idx] > 1000)
			$stats[$idx] = (round(floatval($stats[$idx]) / 1000.0, 2)) . ' Kbit/s';
		else
			$stats[$idx] = $stats[$idx] . ' bit/s';
	$result->assign('livetraffic', 'innerHTML', $stats[0] . ' / ' . $stats[2]);
	$result->call('live_traffic_finished');

	return $result;
}

function change_linktypes($linktype,$nodelinktype=0)
{
    global $LINKTYPES, $LINKTECHNOLOGIES;
    $obj = new xajaxResponse();
    $str = "<select name='nodeedit[linktechnology]' id='linktechnology' style='min-width:170px;'>";
    $str .= "<option value='0'>- nieznany -</option>";
    foreach ($LINKTECHNOLOGIES[$linktype] as $idx => $key) {
	$str .= "<option value='".$idx."'";
	if ($idx == $nodelinktype)
	    $srt .= " selected";
	$str .= ">".$key."</option>";
    }
    $str .= "</select>";
    $obj->assign("view_select_linktechnology","innerHTML",$str);
    
    return $obj;
}

$LMS->InitXajax();
$LMS->RegisterXajaxFunction(array('getNodeLocks', 'addNodeLock', 'delNodeLock', 'getThroughput','change_linktypes'));

?>
