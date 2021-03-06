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

//Ajax calls for ListControl
if(Manager::CheckRequestsAreSet(['action', 'source','pid','cid']) && $_REQUEST['source'] == 'ListControl'){
	if(Manager::GetUser()->HasProjectPermissions(EDIT_LAYOUT)){
		$action = $_REQUEST['action'];
		$ctrlopts = Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid']);
		
		//Handle updating of list options for LC
		if($action == 'updateListOpts') {
			if(Manager::CheckRequestsAreSet(['options']))
				$ctrlopts->updateListOpts($_REQUEST['options']);
			else
				$ctrlopts->updateListOpts(array());
		} 
		//Handle updating of default value for LC
		else if ($action == 'updateDefValue' && Manager::CheckRequestsAreSet(['defVal'])) {
			$ctrlopts->updateDefValue($_REQUEST['defVal']);
		} 
		//Handle updating of presets for LC
		else if ($action == 'updatePresets' && Manager::CheckRequestsAreSet(['selPre'])) {
			$ctrlopts->updatePresets($_REQUEST['selPre']);
		} 
		//Handle saving of new preset for LC
		else if ($action == 'saveNewPreset' && Manager::CheckRequestsAreSet(['name'])) {
			$ctrlopts->saveNewPreset($_REQUEST['name']);
		} //TODO: AUTOFILL NEEDS TO BE REDONE
		/*else if ($action == 'setAutoFill' && Manager::CheckRequestsAreSet(['afCid'])) {
			$ctrlopts->setAutoFillControl($_REQUEST['afCid']);
		} else if ($action == 'addAutoFillRule' && Manager::CheckRequestsAreSet(['fillVal','params','numRules','sid'])) {
			$ctrlopts->addAutoFillRule($_REQUEST['fillVal'],$_REQUEST['params'],$_REQUEST['numRules'],$_REQUEST['pid'],$_REQUEST['sid']);
		}*/ 
		//Handle printing of control options for LC
		else if ($action == 'showDialog') {
    		Manager::GetControl($_REQUEST['pid'], $_REQUEST['cid'])->PrintControlOptions();
    	}
	}
}
?>