<?php


/*
 * @version $Id: ocsng.function.php 4213 2006-12-25 19:56:49Z moyo $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2006 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

   function diff_key() {
       $argCount  = func_num_args();
       $diff_arg_prefix = 'diffArg';
       $diff_arg_names = array();
       for ($i=0; $i < $argCount; $i++) {
           $diff_arg_names[$i] = 'diffArg'.$i;
           $$diff_arg_names[$i] = array_keys((array)func_get_arg($i));
       }
       $diffArrString = '';
       if (!empty($diff_arg_names)) $diffArrString =  '$'.implode(', $', $diff_arg_names);
       eval("\$result = array_diff(".$diffArrString.");");
       return $result;
   }

function ldapImportUser ($login,$sync)
{
	ldapImportUserByServerId($login, $sync,$_SESSION["ldap_server"]);
}
function ldapImportUserByServerId($login, $sync,$ldap_server) {
	global $DB, $LANG;

	$config_ldap = new AuthLDAP();
	$res = $config_ldap->getFromDB($ldap_server);
	$ldap_users = array ();
	
	// we prevent some delay...
	if (!$res) {
		return false;
	}
	
	//Connect to the directory
	$ds = connect_ldap($config_ldap->fields['ldap_host'], $config_ldap->fields['ldap_port'], $config_ldap->fields['ldap_rootdn'], $config_ldap->fields['ldap_pass'], $config_ldap->fields['ldap_use_tls']);
	if ($ds) {
		//Get the user's dn
		$user_dn = ldap_search_user_dn($ds, $config_ldap->fields['ldap_basedn'], $config_ldap->fields['ldap_login'], $login, $config_ldap->fields['ldap_condition']);
		if ($user_dn) {
			$user = new User();
			//Get informations from LDAP
			$user->getFromLDAP($config_ldap->fields, $user_dn, $login, "");
			//Add the auth method
			$user->fields["auth_method"] = AUTH_LDAP;
			$user->fields["id_auth"] = $ldap_server;
			if (!$sync) {
				//Save informations in database !
				$input = $user->fields;
				unset ($user->fields);

				$user->fields["ID"] = $user->add($input);
				return $user->fields["ID"];
			} else
					$user->update($user->fields);
		}
	} else {
		return false;
	}

}

function ldapChooseDirectory($target) {
	global $DB, $LANG;

	echo "<form action=\"$target\" method=\"post\">";
	echo "<div align='center'>";
	echo "<p >" . $LANG["ldap"][5] . "</p>";
	echo "<table class='tab_cadre'>";
	echo "<tr class='tab_bg_2'><th colspan='2'>" . $LANG["ldap"][4] . "</th></tr>";
	$query = "SELECT * FROM glpi_auth_ldap ORDER BY name ASC";
	$result = $DB->query($query);
	//If more than one ldap server
	if ($DB->numrows($result) > 1) {
		echo "<tr class='tab_bg_2'><td align='center'>" . $LANG["common"][16] . "</td><td align='center'>";
		echo "<select name='ldap_server'>";
		while ($ldap = $DB->fetch_array($result))
			echo "<option value=" . $ldap["ID"] . ">" . $ldap["name"] . "</option>";

		echo "</select></td></tr>";
		echo "<tr class='tab_bg_2'><td align='center' colspan='2'><input class='submit' type='submit' name='ldap_showusers' value='" . $LANG["buttons"][2] . "'></td></tr>";

	} elseif ($DB->numrows($result) == 1) {
		//If only one server, do not show the choose ldap server window
		$ldap = $DB->fetch_array($result);
		$_SESSION["ldap_server"]=$ldap["ID"];
		glpi_header($_SERVER['PHP_SELF']);
	}
	else
		//No ldap server
		echo "<tr class='tab_bg_2'><td align='center' colspan='2'>" . $LANG["ldap"][7] . "</td></tr>";

	echo "</table></div></form>";
}

//Get the list of LDAP users to add/synchronize
function getAllLdapUsers($id_auth, $sync = 0) {
	global $DB, $LANG;

	$config_ldap = new AuthLDAP();
	$res = $config_ldap->getFromDB($id_auth);
	$ldap_users = array ();

	// we prevent some delay...
	if (!$res) {
		return false;
	}

	$ds = connect_ldap($config_ldap->fields['ldap_host'], $config_ldap->fields['ldap_port'], $config_ldap->fields['ldap_rootdn'], $config_ldap->fields['ldap_pass'], $config_ldap->fields['ldap_use_tls']);
	if ($ds) {
		if (!$sync)
		$attrs = array (
			$config_ldap->fields['ldap_login']
		);
		else
		//Search for ldap login AND modifyTimestamp, which indicates the last update of the object in directory
			$attrs = array (
			$config_ldap->fields['ldap_login'], "modifyTimestamp"
		);

		// Tenter une recherche pour essayer de retrouver le DN
		$filter = "(".$config_ldap->fields['ldap_login']."=*)";
		if (!empty ($config_ldap->fields['ldap_condition']))
			$filter = "(& $filter ".$config_ldap->fields['ldap_condition'].")";
	
		$sr = ldap_search($ds, $config_ldap->fields['ldap_basedn'],$filter , $attrs);
		$info = ldap_get_entries($ds, $sr);
		for ($ligne = 0; $ligne < $info["count"]; $ligne++)
		{
			//If ldap add
//			if (isset($info[$ligne][$config_ldap->fields['ldap_login']]))
			if (!$sync)
				$ldap_users[$info[$ligne][$config_ldap->fields['ldap_login']][0]] = $info[$ligne][$config_ldap->fields['ldap_login']][0];
			else
			//If ldap synchronisation
				$ldap_users[$info[$ligne][$config_ldap->fields['ldap_login']][0]] = ldapStamp2UnixStamp($info[$ligne]['modifytimestamp'][0]);

		}	
	} else {
		return false;
	}
	
	$glpi_users = array ();
	$sql = "SELECT name, date_mod FROM glpi_users";
	$result = $DB->query($sql);
	if ($DB->numrows($result) > 0)
		while ($user = $DB->fetch_array($result))
		{
			//Ldap add : fill the array with the login of the user 
			if (!$sync)
				$glpi_users[$user['name']] = $user['name'];
			else
			{
			//Ldap synchronisation : look if the user exists in the directory and compares the modifications dates (ldap and glpi db)
				if (!empty ($ldap_users[$user['name']]))
				{
					if ($ldap_users[$user['name']] - strtotime($user['date_mod']) > 0)
						$glpi_users[] = $user['name'];
				}		
		}
		}
	//If add, do the difference between ldap users and glpi users
	if (!$sync)
		return diff_key($ldap_users,$glpi_users);
	else
		return $glpi_users;
	
}
function showLdapUsers($target, $check, $start, $sync = 0) {
	global $DB, $CFG_GLPI, $LANG;

	$ldap_users = getAllLdapUsers($_SESSION["ldap_server"], $sync);
	$numrows = sizeof($ldap_users);

	if (!$sync) {
		$action = "toimport";
		$form_action = "import_ok";
	} else {
		$action = "tosync";
		$form_action = "sync_ok";
	}

	if ($numrows > 0) {
		$parameters = "check=$check";
		printPager($start, $numrows, $target, $parameters);

		// delete end 
		array_splice($ldap_users, $start + $CFG_GLPI["list_limit"]);
		// delete begin
		if ($start > 0)
			array_splice($ldap_users, 0, $start);

		echo "<div align='center'>";
		echo "<form method='post' name='ldap_form' action='" . $target . "'>";
		echo "<a href='" . $target . "?check=all' onclick= \"if ( markAllRows('ldap_form') ) return false;\">" . $LANG["buttons"][18] . "</a>&nbsp;/&nbsp;<a href='" . $target . "?check=none' onclick= \"if ( unMarkAllRows('ldap_form') ) return false;\">" . $LANG["buttons"][19] . "</a>";
		echo "<table class='tab_cadre'>";
		echo "<tr><th>" . $LANG["buttons"][37] . "</th><th>" . $LANG["Menu"][14] . "</th></tr>";

		foreach ($ldap_users as $user) {

			echo "<tr align='center' class='tab_bg_2'>";
			echo "<td><input type='checkbox' name='" . $action . "[" . $user . "]' " . ($check == "all" ? "checked" : "") ."></td>";
			echo "<td colspan='4'>" . $user . "</td>";
			echo "</tr>";
		}
		echo "<tr class='tab_bg_1'><td colspan='5' align='center'>";
		echo "<input class='submit' type='submit' name='" . $form_action . "' value='" . $LANG["buttons"][37] . "'>";
		echo "</td></tr>";
		echo "</table>";
		echo "</form></div>";
		printPager($start, $numrows, $target, $parameters);
	} else
		echo "<div align='center'><strong>" . $LANG["ldap"][3] . "</strong></div>";
}

//Test a connection to the ldap directory
function testLDAPConnection($id_auth) {
	$config_ldap = new AuthLDAP();
	$res = $config_ldap->getFromDB($id_auth);
	$ldap_users = array ();

	// we prevent some delay...
	if (!$res) {
		return false;
	}

	$ds = connect_ldap($config_ldap->fields['ldap_host'], $config_ldap->fields['ldap_port'], $config_ldap->fields['ldap_rootdn'], $config_ldap->fields['ldap_pass'], $config_ldap->fields['ldap_use_tls']);
	if ($ds)
		return true;
	else
		return false;
}

//Display refresh button in the user page
function showLdapRefreshButton($target, $ID) {
	global $LANG, $DB;

	//Look it the user's auth method is LDAP
	$sql = "SELECT auth_method, id_auth FROM glpi_users WHERE ID=" . $ID;
	$result = $DB->query($sql);
	if ($DB->numrows($result) > 0) {
		$data = $DB->fetch_array($result);

		//Look it the auth server still exists !
		$sql = "SELECT name FROM glpi_auth_ldap WHERE ID=" . $data["id_auth"];
		$result = $DB->query($sql);
		if ($DB->numrows($result) > 0) {

			if (haveRight("user", "w") && $data["auth_method"] == AUTH_LDAP) {
				echo "<div align='center'>";
				echo "<form method='post' action=\"$target\">";
				echo "<table class='tab_cadre'><tr class='tab_bg_2'><td>";
				echo "<input type='hidden' name='ID' value='" . $ID . "'>";
				echo "<input class=submit type='submit' name='force_ldap_resynch' value='" . $LANG["ocsng"][24] . "'>";
				echo "</td><tr></table>";
				echo "</form>";
			}
		}
	}
}

//Get authentication method of a user, by looking in database
function getAuthMethodFromDB($ID) {
	global $DB;
	$sql = "SELECT auth_method FROM glpi_users WHERE ID=" . $ID;
	$result = $DB->query($sql);
	if ($DB->numrows($result) > 0) {
		$data = $DB->fetch_array($result);
		return $data["auth_method"];
	} else
		return NOT_YET_AUTHENTIFIED;
}

//converts LDAP timestamps over to Unix timestamps
function ldapStamp2UnixStamp($ldapstamp) {
   $year=substr($ldapstamp,0,4);
   $month=substr($ldapstamp,4,2);
   $day=substr($ldapstamp,6,2);
   $hour=substr($ldapstamp,8,2);
   $minute=substr($ldapstamp,10,2);
   $seconds=substr($ldapstamp,12,2);
   $stamp=gmmktime($hour,$minute,$seconds,$month,$day,$year);
   return $stamp;
}

?>
