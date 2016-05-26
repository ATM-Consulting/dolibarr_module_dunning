<?php
/* Dunning management
 * Copyright (C) 2014 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2014 Florian HENRY <florian.henry@open-concept.pro>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 * Dunning invoice CRUD
 */
class Dunninginvoice extends CommonObject
{
	public $element = 'dunninginvoice'; //!< Id that identify managed objects
	public $table_element = 'dunning_invoice'; //!< Name of table without prefix where object is stored

	public $id;

	public $fk_dunning;
	public $fk_invoice;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create object into database
	 *
	 * @param Dunning $dunning The linked dunning object
	 * @param int $fk_invoice The linked invoice ID
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, Id of created object if OK
	 */
	function create($dunning, $fk_invoice, $notrigger = 0)
	{
		global $langs,$conf;
		
		$error = 0;

		// Clean parameters
		if (isset($this->fk_dunning)) {
			$this->fk_dunning = trim($this->fk_dunning);
		}
		if (isset($this->fk_invoice)) {
			$this->fk_invoice = trim($this->fk_invoice);
		}

		// Check parameters
		if(get_class($dunning) !== 'Dunning') {
			$this->error = "ErrorBadParameter";
			dol_syslog(__METHOD__ . " Trying to create a dunning invoice with a bad dunning parameter", LOG_ERR);
			return -1;
		}
		if(!is_int($fk_invoice)) {
			$this->error = "ErrorBadParameter";
			dol_syslog(__METHOD__ . " Trying to create a dunning invoice with a bad fk_invoice parameter", LOG_ERR);
			return -1;
		}
		// TODO: Put here code to add control on parameters values

		// Set parameters
		$this->fk_dunning = $dunning->id;
		$this->fk_invoice = $fk_invoice;

		// Insert request
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "(";

		$sql .= "fk_dunning,";
		$sql .= "fk_invoice";

		$sql .= ") VALUES (";

		$sql .= " " . (!isset($this->fk_dunning) ? 'NULL' : "'" . $this->fk_dunning . "'") . ",";
		$sql .= " " . (!isset($this->fk_invoice) ? 'NULL' : "'" . $this->fk_invoice . "'") . "";

		$sql .= ")";

		$this->db->begin();

		dol_syslog(get_class($this) . "::create sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);

			if (!$notrigger) {
				$this->dunning=$dunning;
				
				$usercretor=new User($this->db);
				$usercretor->fetch($dunning->fk_user_author);
				
				//// Call triggers
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('DUNNINGINVOICE_CREATE',$this,$usercretor,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::create " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		}
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int $id Id object
	 * @return int <0 if KO, >0 if OK
	 */
	function fetch($id)
	{
		global $langs;
		$sql = "SELECT";
		$sql .= " t.rowid,";

		$sql .= " t.fk_dunning,";
		$sql .= " t.fk_invoice";


		$sql .= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
		$sql .= " WHERE t.rowid = " . $id;

		dol_syslog(get_class($this) . "::fetch sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;

				$this->fk_dunning = $obj->fk_dunning;
				$this->fk_invoice = $obj->fk_invoice;


			}
			$this->db->free($resql);

			return 1;
		}
		$this->error = "Error " . $this->db->lasterror();
		dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
		return -1;
	}

	/**
	 * Update object into database
	 *
	 * @param User $user User that modifies
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	function update($user = null, $notrigger = 0)
	{
		$error = 0;

		// Clean parameters

		if (isset($this->fk_dunning)) {
			$this->fk_dunning = trim($this->fk_dunning);
		}
		if (isset($this->fk_invoice)) {
			$this->fk_invoice = trim($this->fk_invoice);
		}

		// Check parameters
		// TODO: Put here code to add control on parameters values

		// Update request
		$sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";

		$sql .= " fk_dunning=" . (isset($this->fk_dunning) ? $this->fk_dunning : "null") . ",";
		$sql .= " fk_invoice=" . (isset($this->fk_invoice) ? $this->fk_invoice : "null") . "";

		$sql .= " WHERE rowid=" . $this->id;

		$this->db->begin();

		dol_syslog(get_class($this) . "::update sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (!$error) {
			if (!$notrigger) {
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('DUNNINGINVOICE_MODIFY',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::update " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user User that deletes
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!$error) {
			if (!$notrigger) {
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('DUNNINGINVOICE_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		if (!$error) {
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
			$sql .= " WHERE fk_dunning=" . $this->fk_dunning;
			$sql .= " AND fk_invoice=" . $this->fk_invoice;

			dol_syslog(get_class($this) . "::delete sql=" . $sql);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error " . $this->db->lasterror();
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::delete " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		}
		$this->db->commit();
		return 1;
	}
}
