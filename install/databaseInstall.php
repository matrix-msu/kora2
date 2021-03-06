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

/**
 * File Name: databaseInstall.php
 * Initial version: Matt Geimer, 2008
 * Purpose: Automatically creates base tables for KORA and writes the connection information to the configuration file
 */
?>
<script language="javascript" type="text/javascript">
/**Arguments: First argument is an indicator of which forms' fields to validate.  Pre-pended to constant ids.
 *Function: Check that the password of the form is at least 8 characters long.  If it is
 			not, print an error message to the appropriate field and return false.
 			If it is at least 8 characters, clear any error message and return true.	
 */
function validate(form)
{
 	var pass = document.getElementById(form+"korapass");
 	var error = document.getElementById(form+"passerror");
	if(pass.value.length < 8) //Print error message in red
	{
		error.innerHTML = "<FONT SIZE='2' COLOR='#FF0000'>Password must be at least 8 characters long.</FONT>";
		return false;
	}
	else //Return true and remove any error message
	{
		error.innerHTML = "";
		return true;
	}
}
</script>

<?php 
include_once('header.php');

if(isset($_REQUEST["submit"])) { 

	//If the user checked Generate Configuration file, test that conf.php is writable
	if(isset($_REQUEST['generateConf'])){
    	$createConfig = $controlUpdate = true;   //control update can only happen if there is a valid conf file
    	$temphandle = @fopen('../includes/conf.php','w');
       	if(!$temphandle)
       		die("'Generate Configuration File' was selected, but conf.php is not writable.<br/>".
       		"Check file permissions for the includes directory.");
       	else
       		fclose($temphandle);
	}
	else{
		//If not generating Config, test that whatever is conf.php is at least there.
		$temphandle = @fopen('../includes/conf.php','r');
       	if(!$temphandle) {
       		die("'Generate Configuration File' was not selected, but conf.php is not accessible.<br/>".
       		"Check file permissions for the includes directory, KORA must be able to read conf.php.");
		}
       	else {
       		fclose($temphandle);
			$controlUpdate = true;   //control update can only happen if there is a valid conf file
		}
		
		$createConfig=false;
	}
	
	//Check if a new database should be made
	if (isset($_REQUEST['newDB']))
	{
		$createDatabase = true;
		//base name for new database
		$newdbname = "kora";
		$dbuser = $_REQUEST['rootDBUser'];
		$dbpass = $_REQUEST['rootDBPass'];
	}
   	else{
   		$createDatabase = false;
   		if (isset($_REQUEST['dbName'])){
   			$newdbname = $_REQUEST['dbName'];
   		}
   		else {
   			die("If using existing database, database name must be supplied.");
   		}
   		$dbuser = $_REQUEST['DBUser'];
   		$dbpass = $_REQUEST['DBPass'];
   	}
   		
   
    //option flags
   $schemeHasFile  = true;
   $dbprefix = false;
   $createKoraDBUser = false;
   $createAdmin = false;
   
   //This should always be true since the form cannot be submitted unless the password is at least 8 characters
   if(isset($_REQUEST['koraAdminPass']) && !empty($_REQUEST['koraAdminPass']))
       $createAdmin = true;
   if(isset($_REQUEST['koraDBPrefix']) && !empty($_REQUEST['koraDBPrefix']))
       $dbprefix = true; 
   if(isset($_REQUEST['koraDBUser']) && isset($_REQUEST['koraDBPass']) && !empty($_REQUEST['koraDBUser']) && !empty($_REQUEST['koraDBPass']))
       $createKoraDBUser = true;
   
   //connect to server
   $dbLink = mysqli_connect($_REQUEST['dbHost'],$dbuser,$dbpass); //the username must be pre-existing
   if(!$dbLink) //connect failed
       die("!! Connection to database server failed, make sure host address, username, and password are correct<br/>");
   
   //create the database
   if ($createDatabase)
   {
      if($dbprefix)
        $newdbname = $_REQUEST['koraDBPrefix']."_".$newdbname;
       if(!mysqli_query($dbLink,'DROP DATABASE IF EXISTS '.$newdbname))
   	die('!! Unable to drop old'.$newdbname.'database.<br/>'.mysqli_error($dbLink));
       if(!mysqli_query($dbLink,'CREATE DATABASE '.$newdbname))
   	die("!! Creation of database failed, make sure username and password are correct and allowed to create databases<br/>");
       echo '++ Created database: '.$newdbname.'<br/>';
   }
   
   //create a user for KORA to use
   if($createKoraDBUser) {
    if(!mysqli_query($dbLink,"GRANT ALL PRIVILEGES on $newdbname.* TO '$_REQUEST[koraDBUser]' IDENTIFIED BY '$_REQUEST[koraDBPass]' "))
           die("!! Unable to createa a database user for KORA: ".mysqli_error($dbLink));
   }
   
   // select the Database
   mysqli_select_db($dbLink,$newdbname);
   
   //Drop all tables from the database - make **sure** the user knows this is going to happen
   //First get a list of the tables in the database
   /* $tables = array();
   $tablesquery = "SHOW TABLES";
   if($tablesresult=mysqli_query($dbLink,$tablesquery)){
   	while($row=mysqli_fetch_row($tablesresult)){
   		$tables[]=$row[0];
   	}
   }
   //If any tables were found in the database, drop them
   if(isset($tables[0])){
   		foreach($tables as $table_name){
  			$sql = "DROP TABLE `$table_name`";
  			if(!$result = mysqli_query($dbLink,$sql)){
    			die( "Error deleting $table_name. MySQL Error: " . mysqli_error() . "<br/>");
   			}
   		}
   } */
   // Table Creation Queries
   $tQueries = array();
   
   $tQueries['user'] = "CREATE TABLE user(
           uid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
           username VARCHAR(32) NOT NULL UNIQUE,
           password CHAR(64) NOT NULL,
           salt INTEGER UNSIGNED NOT NULL,
           email VARCHAR(128) NOT NULL,
           realName VARCHAR(128),
           admin TINYINT NOT NULL,
           organization VARCHAR(128),
           language VARCHAR(6) default 'en_US',
           confirmed TINYINT(1) NOT NULL,
           searchAccount TINYINT(1) UNSIGNED NOT NULL,
           allowPasswordReset TINYINT(1) UNSIGNED NOT NULL,
           resetToken VARCHAR(16),
           PRIMARY KEY(uid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['permGroup'] = "CREATE TABLE permGroup(
                           gid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           pid INTEGER UNSIGNED NOT NULL,
                           name VARCHAR(255) NOT NULL,
                           permissions INTEGER UNSIGNED NOT NULL,
                           PRIMARY KEY(gid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['member'] = "CREATE TABLE member(
                           uid INTEGER UNSIGNED NOT NULL,
                           pid INTEGER UNSIGNED NOT NULL,
                           gid INTEGER UNSIGNED NOT NULL) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['style'] = "CREATE TABLE style(
                           styleid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           description VARCHAR(255),
                           filepath VARCHAR(255),
                           PRIMARY KEY(styleid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['project'] = "CREATE TABLE project(
                           pid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           name VARCHAR(255) NOT NULL,
                           description VARCHAR(255),
                           styleid INTEGER UNSIGNED NOT NULL,
                           admingid INTEGER UNSIGNED NOT NULL,
                           defaultgid INTEGER UNSIGNED NOT NULL,
                           active TINYINT(1) NOT NULL,
						   quota FLOAT NOT NULL,
						   currentsize FLOAT NOT NULL,
                           PRIMARY KEY(pid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['scheme'] = "CREATE TABLE scheme(
                           schemeid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           pid INTEGER UNSIGNED NOT NULL,
                           schemeName VARCHAR(255) NOT NULL,
                           description VARCHAR(255),
                           allowPreset TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                           dublinCoreFields TEXT,
                           dublinCoreOutOfDate TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                           nextid INTEGER UNSIGNED NOT NULL,
                           sequence INTEGER UNSIGNED,
                           crossProjectAllowed TEXT, 
                           legal VARCHAR(255), 
                           publicIngestion TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,                       
                           PRIMARY KEY(schemeid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['dublinCore'] = "CREATE TABLE dublinCore(
                           kid VARCHAR(20) NOT NULL,
                           pid INTEGER UNSIGNED NOT NULL,
                           sid INTEGER UNSIGNED NOT NULL,
                           title VARCHAR(1000),
                           creator VARCHAR(1000),
                           subject VARCHAR(1000),
                           description VARCHAR(1000),
                           publisher VARCHAR(1000),
                           contributor VARCHAR(1000),
                           dateOriginal VARCHAR(1000),
                           dateDigital VARCHAR(1000),
                           type VARCHAR(1000),
                           format VARCHAR(1000),
                           source VARCHAR(1000),
                           language VARCHAR(1000),
                           relation VARCHAR(1000),
                           coverage VARCHAR(1000),
                           rights VARCHAR(1000),
                           contributingInstitution VARCHAR(1000),
                           PRIMARY KEY(kid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['premisScheme0'] = "CREATE TABLE premisScheme0(
                           objectIdentifier VARCHAR(50) NOT NULL,
                           preservationLevel TINYINT,
                           objectCategory VARCHAR(15),
                           creatingApplicationName VARCHAR(30),
                           creatingApplicationVersion VARCHAR(10),
                           dateCreatedByApplication DATE,
                           originalName VARCHAR(50),
                           relationshipType VARCHAR(15),
                           relationshipSubType VARCHAR(15),
                           relatedObjectIdentifier VARCHAR(50),
                           relatedObjectSequence INT DEFAULT '0',
                           relatedEventIdentifier VARCHAR(50),
                           linkingEventIdentifier VARCHAR(50),
                           linkingPermissionStatement VARCHAR(500),
                           PRIMARY KEY (objectIdentifier)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['collection'] = "CREATE TABLE collection(
   					       collid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
   						   schemeid INTEGER UNSIGNED NOT NULL,
   						   name VARCHAR(255) NOT NULL,
   						   description VARCHAR(255),
   						   sequence INTEGER UNSIGNED NOT NULL,
   						   PRIMARY KEY (collid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['control'] = "CREATE TABLE control(
                           name VARCHAR(64) NOT NULL,
                           file VARCHAR(255) NOT NULL,
                           class VARCHAR(64) NOT NULL,
                           xmlPacked TINYINT(1) NOT NULL)
                           CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['fixity'] = "CREATE TABLE fixity(
                           kid VARCHAR(20) NOT NULL, 
                           cid INTEGER UNSIGNED NOT NULL, 
                           path VARCHAR(512),
                           initialHash VARCHAR(128) NOT NULL,
                           initialTime DATETIME NOT NULL, 
                           computedHash VARCHAR(128),
                           computedTime DATETIME NOT NULL,
                           PRIMARY KEY(kid,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['controlPreset'] = "CREATE TABLE controlPreset(
                           presetid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           name VARCHAR(64) NOT NULL,
                           class VARCHAR(64) NOT NULL,
                           project INTEGER UNSIGNED NOT NULL,
                           global TINYINT(1) UNSIGNED NOT NULL,
                           value LONGTEXT NOT NULL,
                           PRIMARY KEY (presetid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   $tQueries['recordPreset'] = "CREATE TABLE recordPreset(
                           recpresetid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                           schemeid INTEGER UNSIGNED NOT NULL,
                           name VARCHAR(255),
                           kid VARCHAR(30),
                           PRIMARY KEY(recpresetid)) CHARACTER SET utf8 COLLATE utf8_general_ci";
   #make sure that whatever key these are given come alpha after 'controlPreset' above.
   $tQueries['preset1'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Remove Preset', 'FileControl', 0, 1, '<allowedMIME />')";
   $tQueries['preset2'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Remove Preset', 'ListControl', 0, 1, '<options />')";
   $tQueries['preset3'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Boolean', 'ListControl', 0, 1, '<options><option>True</option><option>False</option></options>')";
   $tQueries['preset4'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Countries', 'ListControl', 0, 1, '<options><option>United States</option><option>United Nations</option><option>Canada</option><option>Mexico</option><option>Afghanistan</option><option>Albania</option><option>Algeria</option><option>American Samoa</option><option>Andorra</option><option>Angola</option><option>Anguilla</option><option>Antarctica</option><option>Antigua and Barbuda</option><option>Argentina</option><option>Armenia</option><option>Aruba</option><option>Australia</option><option>Austria</option><option>Azerbaijan</option><option>Bahamas</option><option>Bahrain</option><option>Bangladesh</option><option>Barbados</option><option>Belarus</option><option>Belgium</option><option>Belize</option><option>Benin</option><option>Bermuda</option><option>Bhutan</option><option>Bolivia</option><option>Bosnia and Herzegowina</option><option>Botswana</option><option>Bouvet Island</option><option>Brazil</option><option>British Indian Ocean Terr.</option><option>Brunei Darussalam</option><option>Bulgaria</option><option>Burkina Faso</option><option>Burundi</option><option>Cambodia</option><option>Cameroon</option><option>Cape Verde</option><option>Cayman Islands</option><option>Central African Republic</option><option>Chad</option><option>Chile</option><option>China</option><option>Christmas Island</option><option>Cocos (Keeling) Islands</option><option>Colombia</option><option>Comoros</option><option>Congo</option><option>Cook Islands</option><option>Costa Rica</option><option>Cote d`Ivoire</option><option>Croatia (Hrvatska)</option><option>Cuba</option><option>Cyprus</option><option>Czech Republic</option><option>Denmark</option><option>Djibouti</option><option>Dominica</option><option>Dominican Republic</option><option>East Timor</option><option>Ecuador</option><option>Egypt</option><option>El Salvador</option><option>Equatorial Guinea</option><option>Eritrea</option><option>Estonia</option><option>Ethiopia</option><option>Falkland Islands/Malvinas</option><option>Faroe Islands</option><option>Fiji</option><option>Finland</option><option>France</option><option>France, Metropolitan</option><option>French Guiana</option><option>French Polynesia</option><option>French Southern Terr.</option><option>Gabon</option><option>Gambia</option><option>Georgia</option><option>Germany</option><option>Ghana</option><option>Gibraltar</option><option>Greece</option><option>Greenland</option><option>Grenada</option><option>Guadeloupe</option><option>Guam</option><option>Guatemala</option><option>Guinea</option><option>Guinea-Bissau</option><option>Guyana</option><option>Haiti</option><option>Heard &amp; McDonald Is.</option><option>Honduras</option><option>Hong Kong</option><option>Hungary</option><option>Iceland</option><option>India</option><option>Indonesia</option><option>Iran</option><option>Iraq</option><option>Ireland</option><option>Israel</option><option>Italy</option><option>Jamaica</option><option>Japan</option><option>Jordan</option><option>Kazakhstan</option><option>Kenya</option><option>Kiribati</option><option>Korea, North</option><option>Korea, South</option><option>Kuwait</option><option>Kyrgyzstan</option><option>Lao People`s Dem. Rep.</option><option>Latvia</option><option>Lebanon</option><option>Lesotho</option><option>Liberia</option><option>Libyan Arab Jamahiriya</option><option>Liechtenstein</option><option>Lithuania</option><option>Luxembourg</option><option>Macau</option><option>Macedonia</option><option>Madagascar</option><option>Malawi</option><option>Malaysia</option><option>Maldives</option><option>Mali</option><option>Malta</option><option>Marshall Islands</option><option>Martinique</option><option>Mauritania</option><option>Mauritius</option><option>Mayotte</option><option>Micronesia</option><option>Moldova</option><option>Monaco</option><option>Mongolia</option><option>Montserrat</option><option>Morocco</option><option>Mozambique</option><option>Myanmar</option><option>Namibia</option><option>Nauru</option><option>Nepal</option><option>Netherlands</option><option>Netherlands Antilles</option><option>New Caledonia</option><option>New Zealand</option><option>Nicaragua</option><option>Niger</option><option>Nigeria</option><option>Niue</option><option>Norfolk Island</option><option>Northern Mariana Is.</option><option>Norway</option><option>Oman</option><option>Pakistan</option><option>Palau</option><option>Panama</option><option>Papua New Guinea</option><option>Paraguay</option><option>Peru</option><option>Philippines</option><option>Pitcairn</option><option>Poland</option><option>Portugal</option><option>Puerto Rico</option><option>Qatar</option><option>Reunion</option><option>Romania</option><option>Russian Federation</option><option>Rwanda</option><option>Saint Kitts and Nevis</option><option>Saint Lucia</option><option>St. Vincent &amp; Grenadines</option><option>Samoa</option><option>San Marino</option><option>Sao Tome &amp; Principe</option><option>Saudi Arabia</option><option>Senegal</option><option>Seychelles</option><option>Sierra Leone</option><option>Singapore</option><option>Slovakia (Slovak Republic)</option><option>Slovenia</option><option>Solomon Islands</option><option>Somalia</option><option>South Africa</option><option>S.Georgia &amp; S.Sandwich Is.</option><option>Spain</option><option>Sri Lanka</option><option>St. Helena</option><option>St. Pierre &amp; Miquelon</option><option>Sudan</option><option>Suriname</option><option>Svalbard &amp; Jan Mayen Is.</option><option>Swaziland</option><option>Sweden</option><option>Switzerland</option><option>Syrian Arab Republic</option><option>Taiwan</option><option>Tajikistan</option><option>Tanzania</option><option>Thailand</option><option>Togo</option><option>Tokelau</option><option>Tonga</option><option>Trinidad and Tobago</option><option>Tunisia</option><option>Turkey</option><option>Turkmenistan</option><option>Turks &amp; Caicos Islands</option><option>Tuvalu</option><option>Uganda</option><option>Ukraine</option><option>United Arab Emirates</option><option>United Kingdom</option><option>U.S. Minor Outlying Is.</option><option>Uruguay</option><option>Uzbekistan</option><option>Vanuatu</option><option>Vatican (Holy See)</option><option>Venezuela</option><option>Viet Nam</option><option>Virgin Islands (British)</option><option>Virgin Islands (U.S.)</option><option>Wallis &amp; Futuna Is.</option><option>Western Sahara</option><option>Yemen</option><option>Yugoslavia</option><option>Zaire</option><option>Zambia</option><option>Zimbabwe</option></options>')";
   $tQueries['preset5'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Languages', 'ListControl', 0, 1, '<options><option>Abkhaz</option><option>Achinese</option><option>Acoli</option><option>Adangme</option><option>Adygei</option><option>Afar</option><option>Afrihili (Artificial language)</option><option>Afrikaans</option><option>Afroasiatic (Other)</option><option>Akan</option><option>Akkadian</option><option>Albanian</option><option>Aleut</option><option>Algonquian (Other)</option><option>Altaic (Other)</option><option>Amharic</option><option>Apache languages</option><option>Arabic</option><option>Aragonese Spanish</option><option>Aramaic</option><option>Arapaho</option><option>Arawak</option><option>Armenian</option><option>Artificial (Other)</option><option>Assamese</option><option>Athapascan (Other)</option><option>Australian languages</option><option>Austronesian (Other)</option><option>Avaric</option><option>Avestan</option><option>Awadhi</option><option>Aymara</option><option>Azerbaijani</option><option>Bable</option><option>Balinese</option><option>Baltic (Other)</option><option>Baluchi</option><option>Bambara</option><option>Bamileke languages</option><option>Banda</option><option>Bantu (Other)</option><option>Basa</option><option>Bashkir</option><option>Basque</option><option>Batak</option><option>Beja</option><option>Belarusian</option><option>Bemba</option><option>Bengali</option><option>Berber (Other)</option><option>Bhojpuri</option><option>Bihari</option><option>Bikol</option><option>Bislama</option><option>Bosnian</option><option>Braj</option><option>Breton</option><option>Bugis</option><option>Bulgarian</option><option>Buriat</option><option>Burmese</option><option>Caddo</option><option>Carib</option><option>Catalan</option><option>Caucasian (Other)</option><option>Cebuano</option><option>Celtic (Other)</option><option>Central American Indian (Other)</option><option>Chagatai</option><option>Chamic languages</option><option>Chamorro</option><option>Chechen</option><option>Cherokee</option><option>Cheyenne</option><option>Chibcha</option><option>Chinese</option><option>Chinook jargon</option><option>Chipewyan</option><option>Choctaw</option><option>Church Slavic</option><option>Chuvash</option><option>Coptic</option><option>Cornish</option><option>Corsican</option><option>Cree</option><option>Creek</option><option>Creoles and Pidgins (Other)</option><option>Creoles and Pidgins, English-based (Other)</option><option>Creoles and Pidgins, French-based (Other)</option><option>Creoles and Pidgins, Portuguese-based (Other)</option><option>Crimean Tatar</option><option>Croatian</option><option>Cushitic (Other)</option><option>Czech</option><option>Dakota</option><option>Danish</option><option>Dargwa</option><option>Dayak</option><option>Delaware</option><option>Dinka</option><option>Divehi</option><option>Dogri</option><option>Dogrib</option><option>Dravidian (Other)</option><option>Duala</option><option>Dutch</option><option>Dutch, Middle (ca. 1050-1350)</option><option>Dyula</option><option>Dzongkha</option><option>Edo</option><option>Efik</option><option>Egyptian</option><option>Ekajuk</option><option>Elamite</option><option>English</option><option>English, Middle (1100-1500)</option><option>English, Old (ca. 450-1100)</option><option>Esperanto</option><option>Estonian</option><option>Ethiopic</option><option>Ewe</option><option>Ewondo</option><option>Fang</option><option>Fanti</option><option>Faroese</option><option>Fijian</option><option>Finnish</option><option>Finno-Ugrian (Other)</option><option>Fon</option><option>French</option><option>French, Middle (ca. 1400-1600)</option><option>French, Old (ca. 842-1400)</option><option>Frisian</option><option>Friulian</option><option>Fula</option><option>Galician</option><option>Ganda</option><option>Gayo</option><option>Gbaya</option><option>Georgian</option><option>German</option><option>German, Middle High (ca. 1050-1500)</option><option>German, Old High (ca. 750-1050)</option><option>Germanic (Other)</option><option>Gilbertese</option><option>Gondi</option><option>Gorontalo</option><option>Gothic</option><option>Grebo</option><option>Greek, Ancient (to 1453)</option><option>Greek, Modern (1453- )</option><option>Guarani</option><option>Gujarati</option><option>Gwich&apos;in</option><option>G�</option><option>Haida</option><option>Haitian French Creole</option><option>Hausa</option><option>Hawaiian</option><option>Hebrew</option><option>Herero</option><option>Hiligaynon</option><option>Himachali</option><option>Hindi</option><option>Hiri Motu</option><option>Hittite</option><option>Hmong</option><option>Hungarian</option><option>Hupa</option><option>Iban</option><option>Icelandic</option><option>Ido</option><option>Igbo</option><option>Ijo</option><option>Iloko</option><option>Inari Sami</option><option>Indic (Other)</option><option>Indo-European (Other)</option><option>Indonesian</option><option>Ingush</option><option>Interlingua (International Auxiliary Language Association)</option><option>Interlingue</option><option>Inuktitut</option><option>Inupiaq</option><option>Iranian (Other)</option><option>Irish</option><option>Irish, Middle (ca. 1100-1550)</option><option>Irish, Old (to 1100)</option><option>Iroquoian (Other)</option><option>Italian</option><option>Japanese</option><option>Javanese</option><option>Judeo-Arabic</option><option>Judeo-Persian</option><option>Kabardian</option><option>Kabyle</option><option>Kachin</option><option>Kalmyk</option><option>Kal�tdlisut</option><option>Kamba</option><option>Kannada</option><option>Kanuri</option><option>Kara-Kalpak</option><option>Karen</option><option>Kashmiri</option><option>Kawi</option><option>Kazakh</option><option>Khasi</option><option>Khmer</option><option>Khoisan (Other)</option><option>Khotanese</option><option>Kikuyu</option><option>Kimbundu</option><option>Kinyarwanda</option><option>Komi</option><option>Kongo</option><option>Konkani</option><option>Korean</option><option>Kpelle</option><option>Kru</option><option>Kuanyama</option><option>Kumyk</option><option>Kurdish</option><option>Kurukh</option><option>Kusaie</option><option>Kutenai</option><option>Kyrgyz</option><option>Ladino</option><option>Lahnda</option><option>Lamba</option><option>Lao</option><option>Latin</option><option>Latvian</option><option>Letzeburgesch</option><option>Lezgian</option><option>Limburgish</option><option>Lingala</option><option>Lithuanian</option><option>Low German</option><option>Lozi</option><option>Luba-Katanga</option><option>Luba-Lulua</option><option>Luise�o</option><option>Lule Sami</option><option>Lunda</option><option>Luo (Kenya and Tanzania)</option><option>Lushai</option><option>Macedonian</option><option>Madurese</option><option>Magahi</option><option>Maithili</option><option>Makasar</option><option>Malagasy</option><option>Malay</option><option>Malayalam</option><option>Maltese</option><option>Manchu</option><option>Mandar</option><option>Mandingo</option><option>Manipuri</option><option>Manobo languages</option><option>Manx</option><option>Maori</option><option>Mapuche</option><option>Marathi</option><option>Mari</option><option>Marshallese</option><option>Marwari</option><option>Masai</option><option>Mayan languages</option><option>Mende</option><option>Micmac</option><option>Minangkabau</option><option>Miscellaneous languages</option><option>Mohawk</option><option>Moldavian</option><option>Mon-Khmer (Other)</option><option>Mongo-Nkundu</option><option>Mongolian</option><option>Moor�</option><option>Multiple languages</option><option>Munda (Other)</option><option>Nahuatl</option><option>Nauru</option><option>Navajo</option><option>Ndebele (South Africa)</option><option>Ndebele (Zimbabwe)</option><option>Ndonga</option><option>Neapolitan Italian</option><option>Nepali</option><option>Newari</option><option>Nias</option><option>Niger-Kordofanian (Other)</option><option>Nilo-Saharan (Other)</option><option>Niuean</option><option>Nogai</option><option>North American Indian (Other)</option><option>Northern Sami</option><option>Northern Sotho</option><option>Norwegian</option><option>Norwegian (Bokm�l)</option><option>Norwegian (Nynorsk)</option><option>Nubian languages</option><option>Nyamwezi</option><option>Nyanja</option><option>Nyankole</option><option>Nyoro</option><option>Nzima</option><option>Occitan (post-1500)</option><option>Ojibwa</option><option>Old Norse</option><option>Old Persian (ca. 600-400 B.C.)</option><option>Oriya</option><option>Oromo</option><option>Osage</option><option>Ossetic</option><option>Otomian languages</option><option>Pahlavi</option><option>Palauan</option><option>Pali</option><option>Pampanga</option><option>Pangasinan</option><option>Panjabi</option><option>Papiamento</option><option>Papuan (Other)</option><option>Persian</option><option>Philippine (Other)</option><option>Phoenician</option><option>Polish</option><option>Ponape</option><option>Portuguese</option><option>Prakrit languages</option><option>Proven�al (to 1500)</option><option>Pushto</option><option>Quechua</option><option>Raeto-Romance</option><option>Rajasthani</option><option>Rapanui</option><option>Rarotongan</option><option>Romance (Other)</option><option>Romani</option><option>Romanian</option><option>Rundi</option><option>Russian</option><option>Salishan languages</option><option>Samaritan Aramaic</option><option>Sami</option><option>Samoan</option><option>Sandawe</option><option>Sango (Ubangi Creole)</option><option>Sanskrit</option><option>Santali</option><option>Sardinian</option><option>Sasak</option><option>Scots</option><option>Scottish Gaelic</option><option>Selkup</option><option>Semitic (Other)</option><option>Serbian</option><option>Serer</option><option>Shan</option><option>Shona</option><option>Sichuan Yi</option><option>Sidamo</option><option>Sign languages</option><option>Siksika</option><option>Sindhi</option><option>Sinhalese</option><option>Sino-Tibetan (Other)</option><option>Siouan (Other)</option><option>Skolt Sami</option><option>Slave</option><option>Slavic (Other)</option><option>Slovak</option><option>Slovenian</option><option>Sogdian</option><option>Somali</option><option>Songhai</option><option>Soninke</option><option>Sorbian languages</option><option>Sotho</option><option>South American Indian (Other)</option><option>Southern Sami</option><option>Spanish</option><option>Sukuma</option><option>Sumerian</option><option>Sundanese</option><option>Susu</option><option>Swahili</option><option>Swazi</option><option>Swedish</option><option>Syriac</option><option>Tagalog</option><option>Tahitian</option><option>Tai (Other)</option><option>Tajik</option><option>Tamashek</option><option>Tamil</option><option>Tatar</option><option>Telugu</option><option>Temne</option><option>Terena</option><option>Tetum</option><option>Thai</option><option>Tibetan</option><option>Tigrinya</option><option>Tigr�</option><option>Tiv</option><option>Tlingit</option><option>Tok Pisin</option><option>Tokelauan</option><option>Tonga (Nyasa)</option><option>Tongan</option><option>Truk</option><option>Tsimshian</option><option>Tsonga</option><option>Tswana</option><option>Tumbuka</option><option>Tupi languages</option><option>Turkish</option><option>Turkish, Ottoman</option><option>Turkmen</option><option>Tuvaluan</option><option>Tuvinian</option><option>Twi</option><option>Udmurt</option><option>Ugaritic</option><option>Uighur</option><option>Ukrainian</option><option>Umbundu</option><option>Undetermined</option><option>Urdu</option><option>Uzbek</option><option>Vai</option><option>Venda</option><option>Vietnamese</option><option>Volap�k</option><option>Votic</option><option>Wakashan languages</option><option>Walamo</option><option>Walloon</option><option>Waray</option><option>Washo</option><option>Welsh</option><option>Wolof</option><option>Xhosa</option><option>Yakut</option><option>Yao (Africa)</option><option>Yapese</option><option>Yiddish</option><option>Yoruba</option><option>Yupik languages</option><option>Zande</option><option>Zapotec</option><option>Zenaga</option><option>Zhuang</option><option>Zulu</option><option>Zuni</option></options>')";
   $tQueries['preset6'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('US States', 'ListControl', 0, 1, '<options><option>Alabama</option><option>Alaska</option><option>Arizona</option><option>Arkansas</option><option>California</option><option>Colorado</option><option>Connecticut</option><option>Delaware</option><option>Florida</option><option>Georgia</option><option>Hawaii</option><option>Idaho</option><option>Illinois</option><option>Indiana</option><option>Iowa</option><option>Kansas</option><option>Kentucky</option><option>Louisiana</option><option>Maine</option><option>Maryland</option><option>Massachusetts</option><option>Michigan</option><option>Minnesota</option><option>Mississippi</option><option>Missouri</option><option>Montana</option><option>Nebraska</option><option>Nevada</option><option>New Hampshire</option><option>New Jersey</option><option>New Mexico</option><option>New York</option><option>North Carolina</option><option>North Dakota</option><option>Ohio</option><option>Oklahoma</option><option>Oregon</option><option>Pennsylvania</option><option>Rhode Island</option><option>South Carolina</option><option>South Dakota</option><option>Tennessee</option><option>Texas</option><option>Utah</option><option>Vermont</option><option>Virginia</option><option>Washington</option><option>Washington, DC</option><option>West Virginia</option><option>Wisconsin</option><option>Wyoming</option></options>')";
   $tQueries['preset7'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Remove Preset', 'TextControl', 0, 1, '')";
   $tQueries['preset8'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Decimal Number', 'TextControl', 0, 1, '/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/')";
   $tQueries['preset9'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('Integer', 'TextControl', 0, 1, '/^[+-]?[0-9]+([eE][+-]?[0-9]+)?$/')";
   $tQueries['preset10'] = "INSERT INTO controlPreset (name, class, project, global, value) VALUES ('URL or URI', 'TextControl', 0, 1, '/^(http|ftp|https):".'\\\\'.'/'.'\\\\'."//')";   

   // sort by key so the creation messages are in order
   ksort($tQueries);
   
   foreach ($tQueries as $name => $query)
   {
       if(!mysqli_query($dbLink,$query)) die("!! Unable to create table: $name<br/>");
       echo "++ Created table: $name<br/>";
   }
   
   if ($schemeHasFile)
   {
       $query = "ALTER TABLE premisScheme0
                           ADD COLUMN compositionLevel TINYINT DEFAULT '0' NULL
                               AFTER objectCategory,
                           ADD COLUMN messageDigestAlgorithm VARCHAR(10) NULL
                               AFTER compositionLevel,
                           ADD COLUMN messageDigest VARCHAR(64) NULL
                               AFTER messageDigestAlgorithm,
                           ADD COLUMN size VARCHAR(15) NULL
                               AFTER messageDigest,
                           ADD COLUMN formatName VARCHAR(50) NULL
                               AFTER size,
                           ADD COLUMN formatVersion VARCHAR(30) NULL
                               AFTER formatName,
                           ADD COLUMN significantProperties VARCHAR(50) NULL
                               AFTER formatVersion";
       if (!mysqli_query($dbLink,$query)) die('!! Unable to alter table: premis_scheme0<br/>');
       echo '++ Altered table: premis_scheme0<br/>';
   }
   
   //Create koraadmin account for this kora installation
   $kadminpass = "";
   if($createAdmin && strlen($_REQUEST['koraAdminPass']) > 7 )
     $kadminpass = $_REQUEST['koraAdminPass'];
   else {//Redundant since submission form does this check ... left as a possible security patch
      $availalgo = hash_algos();
      $kadminpass = hash($availalgo[time()%count($kadminpass)],time());
      $kadminpass = substr($kadminpass,0,8); 
   }
   $salt = time();
   $sha256 = hash_init('sha256');
   hash_update($sha256,$kadminpass);
   hash_update($sha256,$salt);
   $pwhash = hash_final($sha256);
   $query = "INSERT user (username,password,salt,email,realName,admin,confirmed,searchAccount) VALUES ('koraadmin','$pwhash',$salt,'$_REQUEST[koraAdminEmail]'";
   $query .= ",'Built-in Kora Admin','1','1','0')";
   if(!mysqli_query($dbLink,$query)) die('!!Unable to create koraadmin user,<br/>'.mysqli_error($dbLink));
      echo '++ Created koraadmin user with password: '.$kadminpass.' <br />';
      
   
   //large bunch of text here - all for conf file generation
   if($createConfig) {
      //create conf.php
      $filehandle = fopen('../includes/conf.php','w');
      if(fwrite($filehandle,"<?php  /**
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
along with this program.  If not, see <http://www.gnu.org/licenses/>. */\n") === FALSE) die('!!! Unable to create/write to file - check your file permissions on the install directory!');

      fwrite($filehandle,'$dbuser = "');
      if($createKoraDBUser)
         fwrite($filehandle,$_REQUEST['koraDBUser']);
      else
         fwrite($filehandle,$dbuser);
      fwrite($filehandle,"\";\n".'$dbpass = \'');
      if($createKoraDBUser) 
         fwrite($filehandle,$_REQUEST['koraDBPass']);
      else
         fwrite($filehandle,$dbpass);
      fwrite($filehandle,"';\n".'$dbname = "'.$newdbname.'";'."\n");
      fwrite($filehandle,'$dbhost = "'.$_REQUEST['dbHost'].'";'."\n");
      fwrite($filehandle,"define('baseURI','http://".$_SERVER['SERVER_NAME'].substr($_SERVER['PHP_SELF'],0,-27)."');\n");
      fwrite($filehandle,"define('basePath','".substr($_SERVER['SCRIPT_FILENAME'],0,-27)."');\n");
      fwrite($filehandle,"define('baseEmail', 'kora@"."$_SERVER[SERVER_NAME]');\n");
      fwrite($filehandle,"define('fileDir', 'files/');\n");
      fwrite($filehandle,"define('extractFileDir', fileDir.'extractedFiles/');\n");
      fwrite($filehandle,"define('awaitingApprovalFileDir', fileDir.'awaitingApproval/');\n");
      fwrite($filehandle,"if (!defined('sessionTimeout')) define('sessionTimeout', 30*60); // in seconds\n\n");
      fwrite($filehandle,"//Recaptcha Keys go here!\n");
      fwrite($filehandle,"define('PublicKey', '".$_REQUEST['koraRecapPub']."');\n");
	  fwrite($filehandle,"define('PrivateKey', '".$_REQUEST['koraRecapPri']."');\n\n");
	  fwrite($filehandle,"define('RESULTS_IN_PAGE', 10);\n\n");
      fwrite($filehandle,"require_once('includes.php');\n ?>");
      
      fclose($filehandle);
      echo '++ Created ../includes/conf.php - please review it and adjust as needed. <br />';
   }
   
   //once config is setup, include things
	include_once('../includes/includes.php');
   
   //pre-populate the controls into KORA
   if($controlUpdate) {
      $dir = "../controls/";
        $controlList = array();
        if(is_dir($dir)) {
            if($dh = opendir($dir)) {
                while(($file = readdir($dh)) !== false) {
                    if(filetype($dir.$file) == "file") {
                        $controlfile = explode(".",$file);
                        if(!in_array($controlfile[0], array('index', 'control', 'controlVisitor'))) {
                            $controlList[] = $controlfile[0];                        
                        }
                    }
                }
            }
        }
        
        $dbControls = array();
        $controlList = array_unique($controlList);
        
        foreach($controlList as $control) {
            $controlName = ucfirst($control);
            $controlInstance = new $controlName();
            $dbControls[] = array('name' => $controlInstance->getType(), 'file' => $control.'.php', 'class' => $controlName, 'xmlPacked' => $controlInstance->isXMLPacked() ? '1' : '0');
        }
        // clear the controls list
        mysqli_query($dbLink,'DELETE FROM control');
        // insert the controls into the table
        foreach($dbControls as $c) mysqli_query($dbLink,"INSERT INTO control (name, file, class, xmlpacked) VALUES ('".mysqli_real_escape_string($dbLink,$c['name'])."', '".mysqli_real_escape_string($dbLink,$c['file'])."', '".mysqli_real_escape_string($dbLink,$c['class'])."', '".mysqli_real_escape_string($dbLink,$c['xmlPacked'])."')");
        echo '++ Control list populated <br />';
        
        //This script used to have to be run by the administrator upon first logging in - seems better to have it run by the installer
        $db = new mysqli($_REQUEST['dbHost'], $dbuser, $dbpass, $newdbname); //The upgradeDatabase script expects a db object to be defined for the kora db
        //Use output buffering so including this script doesnt re-display the kora header/frame in the middle of the page.
        ob_start();
        include_once('../upgradeDatabase.php');
        ob_end_clean();
      
   }
   echo '<br />++ INSTALL COMPLETED SUCCESSFULLY<br />';
   
   
}
else if(isset($_REQUEST['oldDB']))
{ ?>
   <form name="installinfo" method="post" action="databaseInstall.php" onsubmit="return validate('old')">
   <table class="table_noborder">
   <tr>
   <td align="right">Database hostname:</td><td><input type="text" name="dbHost" /></td>
   </tr><tr>
   <td align="right">Database name:</td><td><input type="text" name="dbName" /></td>
   </tr><tr>
   <td align="right">MySQL username:</td><td><input type="text" name="DBUser" /></td>
   </tr><tr>
   <td align="right">MySQL password:</td><td><input type="password" name="DBPass" /></td>
   </tr><tr>
   <td align="right">System Admin Password<br /><em>(username 'koraadmin')</em>:</td><td><input type="password" name="koraAdminPass" id = "oldkorapass"/></td>
   <td><div id="oldpasserror"></div></td>
   </tr><tr>
   <td align="right">Email for System Admin:</td><td><input type="text" name="koraAdminEmail" /></td>
   </tr><tr>
   <td align="right">Public ReCAPTCHA Token:</td><td><input type="text" name="koraRecapPub" /></td>
   </tr><tr>
   <td align="right">Private ReCAPTCHA Token:</td><td><input type="text" name="koraRecapPri" /></td>
   </tr><tr>
   <td align="right">Generate Configuration File</td><td><input type="checkbox" name="generateConf" checked/></td>
   </tr><tr>
   <td colspan="2" align="center"><input type="submit" name="submit" value="Install" /></td>
   </tr></table>
   <input type="hidden" name="existingDB" value="existing" />
   </form>
<?php  }
else if(isset($_REQUEST['newDB'])){
   ?>
   
   <h2>Installer</h2>

   <form name="installinfo" method="post" action="databaseInstall.php" onsubmit="return validate('new')">
   <table class="table_noborder">
   <tr>
   <td align="right">Database hostname:</td><td><input type="text" name="dbHost" /></td>
   </tr><tr>
   <td align="right">MySQL root username:</td><td><input type="text" name="rootDBUser" /></td>
   </tr><tr>
   <td align="right">MySQL root password:</td><td><input type="password" name="rootDBPass" /></td>
   </tr><tr>
   <td align="right">KORA database prefix:</td><td><input type="text" name="koraDBPrefix" /></td>
   </tr><tr>
   <td align="right">Database Username for KORA(will create):</td><td><input type="text" name="koraDBUser" /></td>
   </tr><tr>
   <td align="right">Database Password for KORA(if creating user):</td><td><input type="password" name="koraDBPass" /></td>
   </tr><tr>
   <td align="right">System Admin Password<br /><em>(username 'koraadmin')</em>:</td><td><input type="password" name="koraAdminPass" id="newkorapass"/></td>
   <td><div id="newpasserror"></div></td>
   </tr><tr>
   <td align="right">Email for System Admin:</td><td><input type="text" name="koraAdminEmail" /></td>
   </tr><tr>
   <td align="right">Public ReCAPTCHA Token:</td><td><input type="text" name="koraRecapPub" /></td>
   </tr><tr>
   <td align="right">Private ReCAPTCHA Token:</td><td><input type="text" name="koraRecapPri" /></td>
   </tr><tr>
   <td align="right">Generate Configuration File</td><td><input type="checkbox" name="generateConf" checked/></td>
   </tr><tr>
   <td colspan="2" align="right"><input type="submit" name="submit" value="Install" /></td>
   </tr></table>
   <input type="hidden" name="newDB" value="new" />
   </form>
<?php  } 
else{ //Prompt user if they want to create a new Database or use an already existing one
	?>
	<form name="databasesetup" method="post" action="databaseInstall.php">
	Would you like to ...<br/>
	<input type="submit" name="oldDB" value="Use An Existing Database" /><br/>
	Or...<br/>
	<input type="submit" name="newDB" value="Create New Database" />
	</form>
<?php }
include_once('footer.php');
?>

