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

//Ajax calls for DateControl
if(Manager::CheckRequestsAreSet(['action', 'source', 'pid', 'cid']) && $_REQUEST['source'] == 'DateControl'){
	
	if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT)){
		$action = $_REQUEST['action'];
		$ctrlopts = Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid']);
		//Handle updating of date range for DC
		if($action == 'updateDDateRange' && Manager::CheckRequestsAreSet(['rangestart', 'rangeend']) ) {
			$ctrlopts->updateDateRange($_REQUEST['rangestart'], $_REQUEST['rangeend']);
		} 
		//Handle updating of era for DC
		else if ($action == 'updateDEra' && Manager::CheckRequestsAreSet(['era']))  {
			$ctrlopts->updateEra($_REQUEST['era']);
		} 
		//Handle updating of date format for DC
		else if ($action == 'updateDFormat' && Manager::CheckRequestsAreSet(['format']) ) {
			$ctrlopts->updateFormat($_REQUEST['format']);
		} 
		//Handle updating of default value for DC
		else if ($action == 'updateDDefaultValue' && Manager::CheckRequestsAreSet(['month', 'day', 'year', 'era']) ) {
			$ctrlopts->OptUpdateDefaultValue($_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year'], $_REQUEST['era']);
		} 
		//Handle updating of prefixes for DC
		else if ($action == 'updateDPrefixes' && Manager::CheckRequestsAreSet(['values']) ) {
			$ctrlopts->OptUpdatePrefixes($_REQUEST['values']);
		} 
		//Handle updating of suffixes for DC
		else if ($action == 'updateDSuffixes' && Manager::CheckRequestsAreSet(['values']) ) {
			$ctrlopts->OptUpdateSuffixes($_REQUEST['values']);
		} 
		//Handle printing of control options for DC
		else if ($action == 'showDialog') {
			Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
		}
	}
}
?>