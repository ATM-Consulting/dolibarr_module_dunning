<?php
/*
 * Copyright (C) 2014 Florian Henry <florian.henry@open-concept.pro>
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
 */

/**
 * \file    class/dunninginvoice.class.php
 * \ingroup dunning
 * \brief   CRUD (Create/Read/Update/Delete) for dunning_invoice
 *          Initialy built by build_class_from_table on 2014-02-18 14:46
 */

/**
 * Class of triggers Dunning
 */
class InterfaceDunning{
	var $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db handler
	 */
	function __construct($db) {

		$this->db = $db;
		
		$this->name = preg_replace ( '/^Interface/i', '', get_class ( $this ) );
		$this->family = "dunning";
		$this->description = "Trigger on dunning";
		$this->version = 'dolibarr'; // 'development', 'experimental', 'dolibarr' or version
		$this->picto = 'technic';
	}

	/**
	 * Return name of trigger file
	 *
	 * @return string Name of trigger file
	 */
	function getName() {

		return $this->name;
	}

	/**
	 * Return description of trigger file
	 *
	 * @return string Description of trigger file
	 */
	function getDesc() {

		return $this->description;
	}

	/**
	 * Return version of trigger file
	 *
	 * @return string Version of trigger file
	 */
	function getVersion() {

		global $langs;
		$langs->load ( "admin" );
		
		if ($this->version == 'development')
			return $langs->trans ( "Development" );
		elseif ($this->version == 'experimental')
			return $langs->trans ( "Experimental" );
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else
			return $langs->trans ( "Unknown" );
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
	 *
	 * @param string $action code
	 * @param Object $object
	 * @param User $user user
	 * @param Translate $langs langs
	 * @param conf $conf conf
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	function run_trigger($action, $object, $user, $langs, $conf) {

		if ($action == 'DUNNINGINVOICE_CREATE') {
			
			dol_syslog ( "Trigger '" . $this->name . "' for action '$action' launched by " . $user->id . ". id=" . $object->id );
			
			if ($object->dunning->mode_creation=='manual') {
				
				$sql = "SELECT rowid FROM  ".MAIN_DB_PREFIX."facture_extrafields WHERE fk_object=".$object->fk_invoice;
				
				dol_syslog(get_class($this) . "::DUNNINGINVOICE_CREATE sql=" . $sql, LOG_DEBUG);
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->errors[] = "Error " . $this->db->lasterror();
					return -1;
				} else {
					$obj = $this->db->fetch_object($resql);
					if (!empty($obj->rowid)) {
						$sql = "UPDATE ".MAIN_DB_PREFIX."facture_extrafields SET fact_no_dunning=1 WHERE fk_object=".$object->fk_invoice;
						
						dol_syslog(get_class($this) . "::DUNNINGINVOICE_CREATE sql=" . $sql, LOG_DEBUG);
						$resql = $this->db->query($sql);
						if (!$resql) {
							$this->errors[] = "Error " . $this->db->lasterror();
							return -1;
						}
					}else {
						$sql = "INSERT INTO ".MAIN_DB_PREFIX."facture_extrafields(fk_object,fact_no_dunning) VALUES (".$object->fk_invoice.",1)";
						
						dol_syslog(get_class($this) . "::DUNNINGINVOICE_CREATE sql=" . $sql, LOG_DEBUG);
						$resql = $this->db->query($sql);
						if (!$resql) {
							$this->errors[] = "Error " . $this->db->lasterror();
							return -1;
						}
					}
				}
				
				
				$sql = "UPDATE ".MAIN_DB_PREFIX."facture_extrafields SET fact_no_dunning=1 WHERE fk_object=".$object->fk_invoice;
				
				dol_syslog(get_class($this) . "::DUNNINGINVOICE_CREATE sql=" . $sql, LOG_DEBUG);
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->errors[] = "Error " . $this->db->lasterror();
					return -1;
				}
				
				return 1;
			} 
		}
		
		
		return 0;
	}
}