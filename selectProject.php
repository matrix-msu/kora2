<?php
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

// Initial Version: Brian Beck, 2008

require_once('includes/conf.php');
require_once('includes/utilities.php');

requireLogin();

// clear the currentProject Session Variable to unselect any previously
// selected project
unset($_SESSION['currentProject']);
unset($_SESSION['currentProjectName']);
unset($_SESSION['currentScheme']);
unset($_SESSION['currentSchemeName']);

// get the list of (active) projects the user has access to
if (isSystemAdmin()) {
    $projectQuery = $db->query('SELECT pid, name, description FROM project WHERE active=1 ORDER BY name ASC');
} else {
    $pidQuery = $db->query('SELECT DISTINCT pid FROM member WHERE uid='.escape($_SESSION['uid']));
    
    if($pidQuery->num_rows == 1){
    	// automatically select the project.
    	$pid = $pidQuery->fetch_assoc();
    	header('Location: projectIndex.php?project_id='.$pid['pid']);
    	die();
    }
    
    $pids = '';
    while ($a = $pidQuery->fetch_assoc()) {
        $pids .= escape($a['pid']).', ';
    }
    /* Adding the trailing 0 serves two purposes: It gets rid of the trailing comma
     problem if there are results, and adds a value if there are no results to make
     for a valid SQL query.  WHERE pid in () is invalid, but WHERE pid in (0) will
     be a valid query that returns 0 results.  Because pid is auto-incrementing,
     the pid can never be 0. */
    $pids .= "'0'";
    
    $projectQuery = $db->query("SELECT pid, name, description FROM project WHERE pid in ($pids) AND active=1 ORDER BY name ASC");
}

include_once('includes/header.php');

echo "<h2>".gettext('Project Selection')."</h2>\n";
if ($projectQuery->num_rows == 0) {
    echo gettext('You are currently not a member of any active projects').'.';
} else {
	$projects = array();
	$pids = array();
?>
	<form name="pSelect" action="projectIndex.php" method="post">
	<?php echo gettext('Quick Jump');?>: <select name="project_id" size="1" onChange="pSelect.submit();"> <option><?php echo gettext('select a project');?></option>
<?php 
    // Note: The way these arrays are put together relies on the fact that duplicate
    //       project names are forbidden.  If that restriction changes, this code will
    //       need to change too.
    while ($a = $projectQuery->fetch_assoc()) {
        echo '<option value="'.$a['pid'].'">'.htmlEscape($a['name']).'</option>';
        $projects["$a[name]"] = htmlEscape($a['description']);
        $pids["$a[name]"] = $a['pid'];
    }
?>
	</select>
	</form> <br />
	<table class="table"> <tr><th><?php echo gettext('Project Name');?></th><th><?php echo gettext('Description');?></th></tr>
<?php 
    foreach(array_keys($projects) as $key) {
    	echo "<tr><td>";
    	echo '<a href="projectIndex.php?project_id='.$pids["$key"].'">'.htmlEscape($key)."</a></td>";
    	echo "<td>$projects[$key]</td></tr>";
    }
    echo "</table>";
}

include_once('includes/footer.php');
?>
