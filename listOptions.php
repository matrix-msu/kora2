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

// Refactor: Joe Deming, Anthony D'Onofrio 2013
 
require_once("includes/includes.php");
Manager::Init();
Manager::RequireLogin();

if (Manager::CheckRequestsAreSet(["pid"])) // If a PID has been selected already, output the HTML options for SID only
{   
    $pid = $_REQUEST["pid"];
    
    if ($pid == "null") return null;
    
    global $db;
    // Escape the PID for safety
    $pid = $db->real_escape_string($pid);
    
    // Grab the list of schemes for this PID
    $projectQuery = "SELECT schemeid, schemename FROM scheme WHERE pid=$pid;";
    $projectQuery = $db->query($projectQuery);
    
    $htmlString = '<option value="null">-All Schemes-</option>'; // Put in the null option (to search across schemes)
    $htmlString .= "\n";
    while($project = $projectQuery->fetch_assoc()) // Populate the scheme list with valid schemes
    {
        $htmlString .= '<option value="'.$project['schemeid'].'">'.htmlEscape($project['schemename'])."</option>\n";
    }
    
    echo $htmlString;
}
    
    
else // A PID has NOT been selected, output only options for PID, leaving SID blank
{
    global $db;
    
    if (Manager::IsSystemAdmin()) // System admin, show all active projects
    {
        $projectQuery = 'SELECT pid, name FROM project WHERE active=1 ORDER BY name';
    } 
    else // Not a system admin, show projects they have permission for
    {
        $projectQuery  = 'SELECT project.pid AS pid, project.name AS name FROM member LEFT JOIN project USING (pid)';
        $projectQuery .= ' WHERE member.uid='.$_SESSION['uid'].' AND project.active=1 ORDER BY project.name';
    }
    
    $projectQuery = $db->query($projectQuery); // Gets a list of valid PIDs
    
    $htmlString = '<option value="null">-All Projects-</option>'; // Put in the null option (to search across projects)
    $htmlString .= "\n";
    while($project = $projectQuery->fetch_assoc())
    {
        // Populate the list from the database
        $htmlString .= '<option value="'.$project['pid'].'">'.htmlEscape($project['name'])."</option>\n";
    }
    
    echo $htmlString;
}
?>