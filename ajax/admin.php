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

// This ajax file handles all requests related to an Admin

require_once(__DIR__.'/../includes/includes.php');

Manager::Init();

if(Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['action', 'source']) && $_REQUEST['source'] == 'UserFunctions')
{
	$action = $_REQUEST['action'];
	
	//Handles updating of a user admin status
	if ($action == 'updateAdmin') 
	{
		if (Manager::CheckRequestsAreSet(['uid', 'admin']))
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->updateAdmin($_REQUEST['admin']); }
		}
	}
	//Handles updating activation of a user account
	elseif ($action == 'updateActivated') 
	{
		if (Manager::CheckRequestsAreSet(['uid', 'activated']) )
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->updateActivated($_REQUEST['activated']); }
		}
	}
	//Handles update of user's real name
	elseif ($action == 'updateRealName') 
	{
		if (Manager::CheckRequestsAreSet(['uid', 'realname']))
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->updateRealName($_REQUEST['realname']); }
		}
	}
	//Handles update of user's organization
	elseif ($action == 'updateOrganization') 
	{
		if (Manager::CheckRequestsAreSet(['uid', 'organization']) )
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->updateOrganization($_REQUEST['organization']); }
		}
	}
	//Handles printing of all users form
	elseif ($action == 'showUsers') 
	{
		User::loadNames();
	}
	//Handles deletion of a user
	elseif ($action == 'deleteUser') 
	{
		if (Manager::CheckRequestsAreSet(['uid'])) { User::deleteUser($_REQUEST['uid']); }
	}
	//Handles checking of a username
	elseif ($action == 'checkUsername') 
	{
		User::checkUsername($_REQUEST['uname']);
	}
	//Handles resetting of a password
	elseif ($action == 'resetPassword') 
	{
		if (Manager::CheckRequestsAreSet(['uid', 'password']) )
		{
			$u = new User($_REQUEST['uid']);
			if ($u) { $u->resetPassword($_REQUEST['password']); }
		}
	}
	//Handles printing of tokens form
	elseif ($action == 'PrintTokens') 
	{
		Manager::PrintTokens(); 
	}
	//Handles creation of a project token
	elseif ($action == 'createToken') 
	{
		Manager::createToken();  
	}
	//Handles deletion of a token
	elseif ($action == 'deleteToken')
	{
		if (Manager::CheckRequestsAreSet(['tokenid'])) 
		{ 
			Manager::deleteToken($_REQUEST['tokenid']); 
		}
	}
	//Handles adding project access to a token
	elseif ($action == 'addAccess')
	{	
		if (Manager::CheckRequestsAreSet(['tokenid', 'tokpid'])) 
		{ 
			Manager::addAccess($_REQUEST['tokenid'], $_REQUEST['tokpid']); 
		} 
	}
	//Handles removing project access to a token
	elseif ($action == 'removeAccess')
	{ 
		if (Manager::CheckRequestsAreSet(['tokenid', 'tokpid'])) 
		{ 
			Manager::removeAccess($_REQUEST['tokenid'], $_REQUEST['tokpid']); 
		} 
	}        
}

if(Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['action', 'source']) && $_REQUEST['source'] == 'SystemManagement')
{
	$action = $_REQUEST['action'];
	//Handles updating of control list
	if ($action == 'UpdateControlList') 
	{
		Manager::UpdateControlList();
	} 
	//Handles updating of style list
	elseif ($action == 'UpdateStyleList') 
	{
		Manager::UpdateStyleList();
	}
}
?>