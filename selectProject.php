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

// Refactor: Joe Deming, Anthony D'Onofrio 2013

require_once('includes/includes.php');

Manager::Init();

Manager::RequireLogin();

//DO THIS HERE TO REDIRECT BEFORE ANY OUTPUT, REDIRECT A USER TO THE ONLY PROJECT THEY'RE ALLOWED IF ONLY ALLOWED 1
if (sizeof(Manager::GetUser()->GetAuthorizedProjects()) == 1)
{
	header('Location: selectScheme.php?pid='.Manager::GetUser()->GetAuthorizedProjects()[0]);
	die();
}

if(Manager::GetProject())
	Manager::PrintHeader();
else
	Manager::PrintHeader(true);

if(isset($_REQUEST['err']) && $_REQUEST['err']=='1'){
	Manager::PrintErrDiv(gettext('You either tried to view a non existant record, or do not have permission to view the requested record.'));
}

echo "<h2>".gettext('Project Selection')."</h2>\n";

$authProjs = Manager::GetUser()->GetAuthorizedProjects();
if (sizeof($authProjs) == 0) {
	echo gettext('You are currently not a member of any active projects').'.';
} else {
?>
	<table class="table"> <tr><th><?php echo gettext('Project Name');?></th><th><?php echo gettext('Description');?></th><th><?php echo gettext('PID');?></th></tr>
<?php 
	foreach($authProjs as $apid)
	{
		$aproj = new Project($apid);
		echo "<tr><td>";
		echo '<a href="selectScheme.php?pid='.$aproj->GetPID().'">'.htmlEscape($aproj->GetName())."</a></td>";
		echo "<td>".htmlEscape($aproj->GetDesc())."</td><td>{$aproj->GetPID()}</td></tr>";
		
	}
	echo "</table>";
}

Manager::PrintFooter();

?>

