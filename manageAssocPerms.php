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

// Initial Version: Brian Beck, 2008
// Refactor: Joe Deming, Anthony D'Onofrio 2013

require_once('includes/includes.php');

Manager::Init();

Manager::RequireScheme();
Manager::RequirePermissions(PROJECT_ADMIN | CREATE_SCHEME | EDIT_LAYOUT, 'selectScheme.php?pid='.Manager::GetProject()->GetPID().'&sid='.Manager::GetScheme()->GetSID());

Manager::PrintHeader();

echo '<h2>'.gettext('Grant Associatior Permissions').'</h2>';
echo '<p>'.gettext('This form allows you to grant association access to other schemes.  Select a project from the left drop-down menu, followed by a scheme from the right drop-down menu, then click "Add" to grant permission to that scheme to search this scheme.').'</p><br/>';



?>
<div id="apschemesetallowedassoc"></div>
<br/><br/>
<div id="apschemeallowedassoc"></div>
<?php
Manager::PrintFooter();
?>

