<?php
/*
 * Copyright (C) 2010-2012	Regis Houssin		<regis@dolibarr.fr>
 * Copyright (C) 2010		Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012 Florian Henry <florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 * \file dunning/core/modules/dunning/mod_dunning_simple.php
 * \ingroup dunning
 */
dol_include_once ( '/dunning/core/modules/dunning/modules_dunning.php' );

/**
 * Class to manage the numbering module Simple for project references
 */
class mod_dunning_simple extends ModeleNumRefDunning {
	var $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'
	var $prefix = 'DUN_';
	var $error = '';
	var $nom = "Simple";

	/**
	 * Return description of numbering module
	 *
	 * @return string Text with description
	 */
	function info() {

		global $langs;
		return $langs->trans ( "SimpleNumRefModelDesc", $this->prefix );
	}

	/**
	 * Return an example of numbering module values
	 *
	 * @return string Example
	 */
	function getExample() {

		return $this->prefix . "0501-0001";
	}

	/**
	 * Test si les numeros deja en vigueur dans la base ne provoquent pas de
	 * de conflits qui empechera cette numerotation de fonctionner.
	 *
	 * @return boolean false si conflit, true si ok
	 */
	function canBeActivated() {

		global $conf, $langs;
		
		$coyymm = '';
		$max = '';
		
		$posindice = 8;
		$sql = "SELECT MAX(SUBSTRING(ref FROM " . $posindice . ")) as max";
		$sql .= " FROM " . MAIN_DB_PREFIX . "dunning";
		$sql .= " WHERE ref LIKE '" . $this->prefix . "____-%'";
		$sql.= " AND entity = ".$conf->entity;
		$resql = $db->query ( $sql );
		if ($resql) {
			$row = $db->fetch_row ( $resql );
			if ($row) {
				$coyymm = substr ( $row [0], 0, 6 );
				$max = $row [0];
			}
		}
		if (! $coyymm || preg_match ( '/' . $this->prefix . '[0-9][0-9][0-9][0-9]/i', $coyymm )) {
			return true;
		} else {
			$langs->load ( "errors" );
			$this->error = $langs->trans ( 'ErrorNumRefModel', $max );
			return false;
		}
	}

	/**
	 * Return next value
	 *
	 * @param Societe $objsoc party
	 * @param dunning $object
	 * @return string if OK, 0 if KO
	 */
	function getNextValue($objsoc, $object) {

		global $db, $conf;
		
		$date = empty ( $object->datec ) ? dol_now () : $object->datec;
		
		// $yymm = strftime("%y%m",time());
		$yymm = strftime ( "%y%m", $date );
		
		// D'abord on recupere la valeur max
		$posindice = 10;
		$sql = "SELECT MAX(SUBSTRING(ref FROM " . $posindice . ")) as max";
		$sql .= " FROM " . MAIN_DB_PREFIX . "dunning";
		$sql .= " WHERE ref like '" . $this->prefix . $yymm ."-%'";
		$sql.= " AND entity = ".$conf->entity;
		
		$resql = $db->query ( $sql );
		dol_syslog ( "mod_dunning_simple::getNextValue sql=" . $sql );
		if ($resql) {
			$obj = $db->fetch_object ( $resql );
			if ($obj)
				$max = intval ( $obj->max );
			else
				$max = 0;
		} else {
			dol_syslog ( "mod_dunning_simple::getNextValue sql=" . $sql );
			return - 1;
		}
		
		
		$num = sprintf ( "%04s", $max + 1 );
		
		dol_syslog ( "mod_dunning_simple::getNextValue return " . $this->prefix . $yymm . "-" . $num );
		return $this->prefix . $yymm . "-" . $num;
	}
}

?>