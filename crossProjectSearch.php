<?php
use KORA\Manager;
use KORA\KoraSearch;
/*
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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Initial Version: Brian Beck, 2008
// Refactor: Joe Deming, Anthony D'Onofrio 2013
require_once('includes/includes.php');

Manager::Init();

Manager::RequireLogin();

Manager::PrintHeader();

echo '<h2>'.gettext('Cross-Project Search').'</h2>';

KoraSearch::PrintCrossProjectSearchForm();

Manager::PrintFooter();
?>
