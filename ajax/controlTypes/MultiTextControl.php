<?php
use KORA\Manager;
/**
Copyright (2008) Matrix: Michigan State University

This file is part of KORA.

KORA is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

KORA is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

// Initial Version: Meghan McNeil, 2009
// Refactor: Joe Deming, Anthony D'Onofrio 2013

//Ajax calls for MultiTextControl
if(Manager::CheckRequestsAreSet(['action', 'source', 'pid', 'cid']) && $_REQUEST['source'] == 'MultiTextControl')
{
	$action = $_REQUEST['action'];
	//Handle printing of control options for MTC
	if($action == 'showMTDialog') {
		Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
	} 
	//Handle saving of default value for MTC
	else if ($action == 'saveDefault' && Manager::CheckRequestsAreSet(['values'])) {
		$ctrlopts = Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid']);
		$ctrlopts->updateDefaultValue($_REQUEST['values']);
	}
}
?>