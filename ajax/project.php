<?php 
use KORA\Manager;
use KORA\Project;
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

// This ajax file handles all requests related to a Project Model

require_once(__DIR__.'/../includes/includes.php');

Manager::Init();

if (Manager::CheckRequestsAreSet(['action', 'source']) &&
	($_REQUEST['source'] == 'ProjectFunctions') &&
	Manager::GetUser())
{
	switch ($_REQUEST['action'])
	{
	//Handles printing of the project schemes
	case 'PrintProjectSchemes':
		if (Manager::GetProject()) { Manager::GetProject()->PrintSchemes(); }
		break;
	//Handles printing of the new scheme form
	case 'PrintNewScheme':
		if (Manager::GetProject()) { Manager::GetProject()->PrintNewScheme(); }
		break;
	//Handles printing of the edit scheme form
	case 'PrintEditScheme':
		if (Manager::GetProject() && Manager::CheckRequestsAreSet(['editsid'])) { Manager::GetProject()->PrintEditScheme($_REQUEST['editsid']); }
		break;
	//Handles creation of a scheme
	case 'CreateScheme':
		if (Manager::GetProject() && Manager::CheckRequestsAreSet(['schemeSubmit'])) { Manager::GetProject()->HandleNewSchemeForm(); }
		break;
	//Handles editing of a scheme
	case 'EditScheme':
		if (Manager::GetProject() && Manager::CheckRequestsAreSet(['schemeSubmit', 'editsid'])) { Manager::GetProject()->HandleEditSchemeForm($_REQUEST['editsid']); }
		break;
	//Handles deletion of a project scheme
	case 'DeleteProjectScheme':
		if (Manager::GetProject() && Manager::CheckRequestsAreSet(['delsid'])) { Manager::GetProject()->DeleteScheme($_REQUEST['delsid']); }
		break;
	//Handles moving/reording of project schemes
	case 'MoveProjectScheme':
		if ((Manager::GetProject() && Manager::CheckRequestsAreSet(['movesid', 'direction']) )) 
		{ Manager::GetProject()->MoveScheme($_REQUEST['movesid'], $_REQUEST['direction']); }
		break;
	//Handles update of project user group's permissions
	case 'updateGroupPerms':
		if (Manager::IsProjectAdmin() && Manager::CheckRequestsAreSet(['permission', 'checked', 'gid']))
		{ Manager::GetProject()->updateGroupPerms($_REQUEST['permission'], (strtolower($_REQUEST['checked']) == 'true'), $_REQUEST['gid']); }
		break;
	//Handles printing of the project user group forms
	case 'PrintGroups':
		if (Manager::IsProjectAdmin())
		{ Manager::GetProject()->PrintGroups(); }
		break;
	//Handles deletion of a project user group
	case 'deleteGroup':
		if (Manager::IsProjectAdmin() && Manager::CheckRequestsAreSet(['gid']))
		{ Manager::GetProject()->deleteGroup($_REQUEST['gid']); }
		break;
	//Handles addition of a project user group
	case 'addGroup':
		if (Manager::IsProjectAdmin() && Manager::CheckRequestsAreSet(['name', 'admin', 'ingestobj', 'delobj', 'edit', 'create', 'delscheme', 'exports', 'moderator']))
		{
			Manager::GetProject()->addGroup($_REQUEST['name'],$_REQUEST['admin'],$_REQUEST['ingestobj'],$_REQUEST['delobj'],$_REQUEST['edit'],$_REQUEST['create'],$_REQUEST['delscheme'],$_REQUEST['exports'],$_REQUEST['moderator']);
		}
		break;
	//Handles printing of project users
	case 'PrintProjectUsers':
		if (Manager::IsProjectAdmin())
		{ Manager::GetProject()->PrintProjectUsers(); }
		break;
	//Handles addition of project users
	case 'addProjectUser':
		if (Manager::IsProjectAdmin() && Manager::CheckRequestsAreSet(['user', 'group']))
		{ Manager::GetProject()->addProjectUser($_REQUEST['user'],$_REQUEST['group']); }
		break;
	//Handles deletion of project users
	case 'deleteProjectUser':
		if (Manager::IsProjectAdmin() && Manager::CheckRequestsAreSet(['user']))
		{ Manager::GetProject()->deleteProjectUser($_REQUEST['user']); }
		break;
	//Handles printing of new project form
	case 'PrintNewProject':
		if(Manager::IsSystemAdmin()){
			Project::PrintNewProjectForm();
		}
        break;
    //Handles creation of a new project
	case 'createProject':
		if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['name','description','active','style','quota','admin'])){
			Project::HandleNewProjectForm();
		}
	//Handles printing of a project edit form
    case 'PrintEditProject':
        if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['pid'])) { 
        	Project::PrintEditProjectForm($_REQUEST['pid']);
        }
        break;
    //Handles the editing of a project
	case 'editProject':
		if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['pid','name','description','active','style','quota'])){
			Project::HandleEditProjectForm($_REQUEST['pid']);
		}
		break;
	//Handles deletion of a project
    case 'deleteProjects':
    	if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['pids'])) {
    		foreach($_REQUEST['pids'] as $pid){ 
        		$p = new Project($pid); 
				$p->DeleteProject();
    		}
    	}
        break;   
    //Handles deactivating a project 
    case 'deactivateProjects':
   		if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['pids'])) {
        	foreach($_REQUEST['pids'] as $pid){
        		$p = new Project($pid); 
        		$p->SetProjectActive(false);
        	}
   		}
        break;
    //Handles activating a project
    case 'activateProjects':
		if (Manager::IsSystemAdmin() && Manager::CheckRequestsAreSet(['pids'])) {
		foreach($_REQUEST['pids'] as $pid){
        		$p = new Project($pid); 
        		$p->SetProjectActive();
        	}
   		}
        break;
    //Handles printing of the project table
    case 'PrintUpdatedProjectsTable':
    	if (Manager::IsSystemAdmin()){
    		Project::PrintManageActiveProjects();
			Project::PrintManageInactiveProjects();
			Project::PrintManageSubmit();
    	}
    	break;
  	default:
		break;
	}
}

?>