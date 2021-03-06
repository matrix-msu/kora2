<?php 
use KORA\Manager;
use KORA\User;
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

// This ajax file handles all requests related to a Search Model 

require_once(__DIR__.'/../includes/includes.php');

Manager::Init();

if(Manager::CheckRequestsAreSet(['action', 'source']) && $_REQUEST['source'] == 'SearchFunctions')
{
	$action = $_REQUEST['action'];
	//Handles retreival of a record
	if ($action == 'getRecord') {
		if (Manager::IsSystemAdmin() &&  Manager::CheckRequestsAreSet(['uid', 'admin']))
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->updateAdmin($_REQUEST['admin']); }
		}
	}
	//Handles providing navigation links for searching through objects
	elseif ($action == 'GetSearchNavLinks') {
		if (Manager::CheckRequestsAreSet(['maxpage', 'currpage', 'adjacentpage']))
		{ print Manager::GetBreadCrumbsHTML((int)$_REQUEST['maxpage'], (int)$_REQUEST['currpage'], (int)$_REQUEST['adjacentpage'], '', 'ks_results_nav'); }
	}
	//Handles special character encoding for multi-anythings
	elseif ($action == 'encodeValue') {
		if (Manager::CheckRequestsAreSet(['value']))
		{ echo encodeValue($_REQUEST['value']); }
	}
}
?>