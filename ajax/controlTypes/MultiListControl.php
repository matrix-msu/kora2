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

//Ajax calls for MultiListControl
if(Manager::CheckRequestsAreSet(['action', 'source']) && $_REQUEST['source'] == 'MultiListControl'){
	if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT)){	
		$action = $_REQUEST['action'];
		//Handles update of default values for MLC
		if($action == 'updateMultiDef') {
			$ctrlopts = Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid']);
			$ctrlopts->updateMultiDef($_REQUEST['defVal']);
		} 
		//Handle printing of control options for MLC
		else if ($action == 'showDialog') {
			Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
		}
	}
}
?>