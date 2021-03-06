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

//Ajax calls for MultiDateControl
if (Manager::CheckRequestsAreSet(['action', 'source', 'pid','cid']) && $_REQUEST['source'] == 'MultiDateControl')
{
	$action = $_REQUEST['action'];
	$ctrlopts = Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid']);
	
	//Handle printing of control options for MDC
	if ($action == 'showDialog') {
		Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
	} 
	//Handle saving of default value for MDC
	else if ($action == 'saveMDDefault' && Manager::CheckRequestsAreSet(['values'])) {
		$ctrlopts->SaveDefaultValue($_REQUEST['values']);
	}
	//Handle ordering of default values in MDC 
	else if ($action == 'moveDefaultValue' && Manager::CheckRequestsAreSet(['defaultV', 'direction'])) {
		$ctrlopts->moveDefaultValue($_REQUEST['cid'], $_REQUEST['defaultV'], $_REQUEST['direction']);
	} 
	//Handle removing of default values from MDC
	else if ($action == 'removeDefaultValue' && Manager::CheckRequestsAreSet(['defaultV'])) {
		$ctrlopts->removeDefaultValue($_REQUEST['cid'], $_REQUEST['defaultV']);
	} 
	//Handle addition of default values for MDC
	else if ($action == 'addDefaultValue' && Manager::CheckRequestsAreSet(['month', 'day', 'year', 'era'])) {
		$ctrlopts->addDefaultValue($_REQUEST['cid'], $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year'], $_REQUEST['era']);
	} 
	//Handle updating of date range for MDC
	else if($action == 'updateDateRange' && Manager::CheckRequestsAreSet(['startYear', 'endYear'])) {
		$ctrlopts->updateDateRange($_REQUEST['cid'], $_REQUEST['startYear'], $_REQUEST['endYear']);
	} 
	//Handle updating if era for MDC
	else if ($action == 'updateEra' && Manager::CheckRequestsAreSet(['era'])) {
		$ctrlopts->updateEra($_REQUEST['cid'], $_REQUEST['era']);
	} 
	//Handle updating of date format for MDC
	else if ($action == 'updateFormat' && Manager::CheckRequestsAreSet(['format'])) {
		$ctrlopts->updateFormat($_REQUEST['cid'], $_REQUEST['format']);
	}
}
?>