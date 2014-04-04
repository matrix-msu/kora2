<?php 
require_once("includes/utilities.php");
requireLogin();

if (isset($_POST["pid"])) // If a PID has been selected already, output the HTML options for SID only
{   
    $pid = $_POST["pid"];
    
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
    
    if (isSystemAdmin()) // System admin, show all active projects
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