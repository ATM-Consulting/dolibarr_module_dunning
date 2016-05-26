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
 * \file    class/dunning.class.php
 * \ingroup dunning
 * \brief   CRUD (Create/Read/Update/Delete) for dunning
 *          Initialy built by build_class_from_table on 2014-02-18 13:54
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once 'dunninginvoice.class.php';

/**
 * Dunning CRUD
 */
class Dunning extends CommonObject
{
	public $element = 'dunning'; //!< Id that identify managed objects
	public $table_element = 'dunning'; //!< Name of table without prefix where object is stored

	public $id;

	public $entity;
	public $ref;
	public $datec = '';
	public $dated = '';
	public $datep = '';
	public $amount;
	public $fk_company;
	public $fk_user_author;
	public $note_private;
	public $note_public;
	public $model_pdf;
	public $mode_creation;
	
	public $lines;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create object in database
	 *
	 * @param User $user User that creates
	 * @param Societe $company The associated company
	 * @param array $invoices Attached invoices
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, Id of created object if OK
	 */
	public function create($user, $company, $invoices, $notrigger = 0)
	{
		global $conf;

		$error = 0;

		// Clean parameters
		if (isset($this->ref)) {
			$this->ref = trim($this->ref);
		}
		if (isset($this->amount)) {
			$this->amount = trim($this->amount);
		}
		if (isset($this->note_private)) {
			$this->note_private = trim($this->note_private);
		}
		if (isset($this->note_public)) {
			$this->note_public = trim($this->note_public);
		}
		if (isset($this->model_pdf)) {
			$this->model_pdf = trim($this->model_pdf);
			if (empty($this->model_pdf)) {
				$this->model_pdf='shrimp';
			}
		}
		if (isset($this->mode_creation)) {
			$this->mode_creation = trim($this->mode_creation);
			if (empty($this->mode_creation)) {
				$this->mode_creation='manual';
			}
		}

		// Check parameters
		if(get_class($user) !== 'User') {
			$this->error = "ErrorBadParameter";
			dol_syslog(__METHOD__ . " Trying to create a dunning with a bad user parameter", LOG_ERR);
			return -1;
		}
		if(get_class($company) !== 'Societe') {
			$this->error = "ErrorBadParameter";
			dol_syslog(__METHOD__ . " Trying to create a dunning with a bad company parameter", LOG_ERR);
			return -1;
		}
		if(!is_array($invoices)) {
			$this->error = "ErrorBadParameter";
			dol_syslog(__METHOD__ . " Trying to create a dunning with a bad invoices parameter", LOG_ERR);
			return -1;
		}
		// TODO: Put here code to add control on parameters values

		// Set parameters
		$this->entity = $conf->entity;
		$this->amount=0;
		// FIXME: autoref numbering module
		$this->datec = dol_now();
		if (dol_strlen($this->dated) == 0) $this->dated = dol_now();
		foreach($invoices as $id) {
			$invoice = new Facture($this->db);
			$invoice->fetch($id);
			$this->amount += $invoice->total_ttc -
				$invoice->getSommePaiement() -
				$invoice->getSumCreditNotesUsed() -
				$invoice->getSumDepositsUsed();
			unset($invoice);
		}
		$this->fk_company = $company->id;
		$this->fk_user_author = $user->id;

		// Insert request
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "(";

		$sql .= "entity,";
		$sql .= "ref,";
		$sql .= "datec,";
		$sql .= "dated,";
		$sql .= "datep,";
		$sql .= "amount,";
		$sql .= "fk_company,";
		$sql .= "fk_user_author,";
		$sql .= "note_private,";
		$sql .= "note_public,";
		$sql .= "model_pdf,";
		$sql .= "mode_creation";
		

		$sql .= ") VALUES (";

		$sql .= " " . (!isset($this->entity) ? 'NULL' : "'" . $this->entity . "'") . ",";
		$sql .= " " . (!isset($this->ref) ? 'NULL' : "'" . $this->db->escape($this->ref) . "'") . ",";
		$sql .= " " . (!isset($this->datec) || dol_strlen($this->datec) == 0 ? 'NULL' : $this->db->idate(
				$this->datec
			)) . ",";
		$sql .= " " . (!isset($this->dated) || dol_strlen($this->dated) == 0 ? 'NULL' : $this->db->idate(
				$this->dated
			)) . ",";
		$sql .= " " . (!isset($this->datep) || dol_strlen($this->datep) == 0 ? 'NULL' : $this->db->idate(
			$this->datep
		)) . ",";
		$sql .= " " . (!isset($this->amount) ? 'NULL' : "'" . $this->amount . "'") . ",";
		$sql .= " " . (!isset($this->fk_company) ? 'NULL' : "'" . $this->fk_company . "'") . ",";
		$sql .= " " . (!isset($this->fk_user_author) ? 'NULL' : "'" . $this->fk_user_author . "'") . ",";
		$sql .= " " . (!isset($this->note_private) ? 'NULL' : "'" . $this->db->escape($this->note_private) . "'") . ",";
		$sql .= " " . (!isset($this->note_public) ? 'NULL' : "'" . $this->db->escape($this->note_public) . "'") . ",";
		$sql .= " " . (!isset($this->model_pdf) ? 'NULL' : "'" . $this->db->escape($this->model_pdf) . "'") . ",";
		$sql .= " " . (!isset($this->mode_creation) ? 'NULL' : "'" . $this->db->escape($this->mode_creation) . "'") . "";

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
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('DUNNING_CREATE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
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

		// Create related dunninnginvoice
		$dunninginvoice = new Dunninginvoice($this->db);
		foreach($invoices as $id) {
			$ret = $dunninginvoice->create($this, (int) $id);
			if($ret < 0) {
				$this->error = "";
				dol_syslog(__METHOD__ . " Unable to create the related dunning invoices", LOG_ERR);
				return -1;
			}
		}

		return $this->id;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int $id Id object
	 * @param string $ref ref
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch($id,$ref='')
	{
		$sql = "SELECT";
		$sql .= " t.rowid,";

		$sql .= " t.entity,";
		$sql .= " t.ref,";
		$sql .= " t.datec,";
		$sql .= " t.dated,";
		$sql .= " t.datep,";
		$sql .= " t.amount,";
		$sql .= " t.fk_company,";
		$sql .= " t.fk_user_author,";
		$sql .= " t.note_private,";
		$sql .= " t.note_public,";
		$sql .= " t.model_pdf,";
		$sql .= " t.mode_creation";

		$sql .= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
		$sql .= " WHERE ";
		if (!empty($id)) {
			$sql .= "t.rowid = " . $id;
		}
		if (!empty($ref)) {
			$sql .= "t.ref = '" . $ref."'";
		}

		dol_syslog(get_class($this) . "::fetch sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;

				$this->entity = $obj->entity;
				$this->ref = $obj->ref;
				$this->datec = $this->db->jdate($obj->datec);
				$this->dated = $this->db->jdate($obj->dated);
				$this->datep = $this->db->jdate($obj->datep);
				$this->amount = $obj->amount;
				$this->fk_company = $obj->fk_company;
				$this->fk_user_author = $obj->fk_user_author;
				$this->note_private = $obj->note_private;
				$this->note_public = $obj->note_public;
				$this->model_pdf = $obj->model_pdf;
				$this->mode_creation = $obj->mode_creation;

			}
			$this->db->free($resql);

			return 1;
		}
		$this->error = "Error " . $this->db->lasterror();
		dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
		return -1;
	}
	
	/**
	 * Load object in memory from the database
	 *
	 * @param int $id Id object
	 * @param int $usedelay use delay setup into Setup->Alerts
 	 * @param int $usedatevalid use date valid as filter
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch_thirdparty_with_unpaiyed_invoice($user,$usedelay=0,$usedatevalid=0)
	{
		global $conf;

		$sql = "SELECT DISTINCT s.nom, s.rowid as socid";
		$sql.= ", sum(pf.amount) as am";
		//$sql.= ",f.total_ttc";
		if (! $user->rights->societe->client->voir) $sql .= ", sc.fk_soc, sc.fk_user ";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
		if (! $user->rights->societe->client->voir) $sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc=s.rowid AND sc.fk_user = " .$user->id;
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."facture as f ON f.fk_soc = s.rowid";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid=pf.fk_facture ";
		$sql.= " WHERE f.entity = ".$conf->entity;
		$sql.= " AND f.type IN (0,1,3) AND f.fk_statut = 1";
		$sql.= " AND f.paye = 0";
		//$sql.= " AND s.rowid = 1414";
		
		if(!empty($usedelay)){
			$date_test_valid=dol_now() - $conf->facture->client->warning_delay;
		}else {
			$date_test_valid=dol_now();
		}
		
		if (!empty($usedatevalid)) {
			$sql .= " AND f.date_valid < '" . $this->db->idate($date_test_valid)."'";
		} else {
			$sql.=" AND f.date_lim_reglement < '".$this->db->idate($date_test_valid)."'";
		}
		
		$sql.=" GROUP BY s.nom, s.rowid, f.total_ttc";
		$sql.=" HAVING ((sum(pf.amount)<f.total_ttc) OR (sum(pf.amount) IS NULL))";
	
		dol_syslog(get_class($this) . "::fetch_thirdparty_with_unpaiyed_invoice sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num=$this->db->num_rows($resql);
			
			$this->lines=array();
			
			while ($obj = $this->db->fetch_object($resql)) {
				
				$this->lines[]=array($obj->socid=>$obj->nom);
			}

			$this->db->free($resql);
	
			return $num;
		}
		$this->error = "Error " . $this->db->lasterror();
		dol_syslog(get_class($this) . "::fetch_thirdparty_with_unpaiyed_invoice " . $this->error, LOG_ERR);
		return -1;
	}

	/**
	 * Update object into database
	 *
	 * @param User $user User that modifies
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($user = null, $notrigger = 0)
	{
		$error = 0;

		// Clean parameters
		if (isset($this->entity)) {
			$this->entity = trim($this->entity);
		}
		if (isset($this->ref)) {
			$this->ref = trim($this->ref);
		}
		if (isset($this->amount)) {
			$this->amount = trim($this->amount);
		}
		if (isset($this->fk_company)) {
			$this->fk_company = trim($this->fk_company);
		}
		if (isset($this->fk_user_author)) {
			$this->fk_user_author = trim($this->fk_user_author);
		}
		if (isset($this->note_private)) {
			$this->note_private = trim($this->note_private);
		}
		if (isset($this->note_public)) {
			$this->note_public = trim($this->note_public);
		}
		if (isset($this->model_pdf)) {
			$this->model_pdf = trim($this->model_pdf);
		}
		if (isset($this->mode_creation)) {
			$this->mode_creation = trim($this->mode_creation);
		}

		// Check parameters
		// TODO: Put here code to add control on parameters values

		// Update request
		$sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";

		$sql .= " entity=" . (isset($this->entity) ? $this->entity : "null") . ",";
		$sql .= " ref=" . (isset($this->ref) ? "'" . $this->db->escape($this->ref) . "'" : "null") . ",";
		$sql .= " datec=" . (dol_strlen($this->datec) != 0 ? "'" . $this->db->idate($this->datec) . "'" : 'null') . ",";
		$sql .= " dated=" . (dol_strlen($this->dated) != 0 ? "'" . $this->db->idate($this->dated) . "'" : 'null') . ",";
		$sql .= " datep=" . (dol_strlen($this->datep) != 0 ? "'" . $this->db->idate($this->datep) . "'" : 'null') . ",";
		$sql .= " amount=" . (isset($this->amount) ? $this->amount : "null") . ",";
		$sql .= " fk_company=" . (isset($this->fk_company) ? $this->fk_company : "null") . ",";
		$sql .= " fk_user_author=" . (isset($this->fk_user_author) ? $this->fk_user_author : "null") . ",";
		$sql .= " note_private=" . (isset($this->note_private) ? "'" . $this->db->escape(
					$this->note_private
				) . "'" : "null") . ",";
		$sql .= " note_public=" . (isset($this->note_public) ? "'" . $this->db->escape(
					$this->note_public
				) . "'" : "null") . ",";
		$sql .= " model_pdf=" . (isset($this->model_pdf) ? "'" . $this->db->escape(
					$this->model_pdf
				) . "'" : "null") . ",";
		$sql .= " mode_creation=" . (isset($this->mode_creation) ? "'" . $this->db->escape(
					$this->mode_creation
				) . "'" : "null") . "";

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
				//$result=$interface->run_triggers('DUNNING_MODIFY',$this,$user,$langs,$conf);
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
		}
		$this->db->commit();
		return 1;
	}


	/**
	 * Delete object in database
	 *
	 * @param User $user User that deletes
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!$error) {
			if (!$notrigger) {
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('DUNNING_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		if (!$error) {
			
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . 'actioncomm';
			$sql .= " WHERE fk_element=" . $this->id;
			$sql .= " AND elementtype='dunning'";
			
			dol_syslog(get_class($this) . "::delete sql=" . $sql);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error " . $this->db->lasterror();
			}
			
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . 'dunning_invoice';
			$sql .= " WHERE fk_dunning=" . $this->id;
			
			dol_syslog(get_class($this) . "::delete sql=" . $sql);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error " . $this->db->lasterror();
			}
			
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
			$sql .= " WHERE rowid=" . $this->id;

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

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		global $conf;
		// FIXME: There must be a simpler way
		$now = dol_now();
		$arraynow = dol_getdate($now);
		$nownotime = dol_mktime(0, 0, 0, $arraynow['mon'], $arraynow['mday'], $arraynow['year']);

		$this->id = 0;

		$this->entity = 1;
		$this->ref = 'SPECIMEN';
		$this->datec = '';
		$this->dated = $nownotime;
		$this->datep = $nownotime;
		$this->amount = 999.99;
		$this->fk_company = 1;
		$this->fk_user_author = '';
		$this->note_private = 'This is a private note';
		$this->note_public = 'This is a public note';
		$this->model_pdf = $conf->global->DUNNING_ADDON_PDF;
		$this->model_creation = 'manual';
	}

	/**
	 * Get an HTML link to the dunning page with it's ref
	 *
	 * @return string
	 */
	public function getNameUrl()
	{
		// FIXME: implement absolute URL link
		return '<a href="'.dol_buildpath('/dunning',1).'/dunning.php?id=' . $this->id . '">' . $this->ref . '</a>';
	}
	
	/**
	 * Get an HTML link to the dunning page with it's ref
	 *
	 * @return string
	 */
	public function getNomUrl()
	{
		// FIXME: implement absolute URL link
		return $this->getNameUrl();
	}

	/**
	 * Get the dunning status
	 *
	 * @return string
	 */
	public function getStatus()
	{
		if ($this->getRest() > 0){
			return 'Open';
		}
		return 'Closed';
	}

	/**
	 * Get the dunning rest amount
	 *
	 * @return float
	 */
	public function getRest()
	{
		$rest = 0;

		// List invoices
		$invoices = $this->getInvoices();

		foreach($invoices as $invoice) {
			$rest += getRest($invoice);
		}

		return $rest;
	}

	/**
	 * Get an array of related invoices
	 *
	 * @return array
	 */
	public function getInvoices()
	{
		$list = array();

		$sql = 'SELECT
		fk_invoice
		FROM ' . MAIN_DB_PREFIX . 'dunning_invoice
		WHERE
		fk_dunning = ' . $this->id . ';';

		$resql = $this->db->query($sql);

		if($resql) {
			$i = 0;
			while($i < $this->db->num_rows($resql)) {
				$invoice = new Facture($this->db);
				$invoice->fetch($this->db->fetch_object($resql)->fk_invoice);
				array_push($list, $invoice);
				$i++;
			}
		}

		return $list;
	}
	
	/**
	 * Get last email send for this dunning
	 *
	 * @return string
	 */
	public function getLastActionEmailSend($date_format) {
		
		global $conf;
		
		$sql='SELECT MAX(a.datep) as lastsend';
		$sql.=' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sql.=' WHERE a.entity = '.$conf->entity;
		$sql.=' AND a.fk_soc = '. $this->fk_company;
		$sql.=' AND a.fk_element = ' . $this->id;
		$sql.=" AND a.elementtype = 'dunning'";
		
		dol_syslog(get_class($this) . "::getLastActionEmailSend sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num=$this->db->num_rows($resql);
				
			$this->lines=array();
				
			$obj = $this->db->fetch_object($resql);
			
			$datelastsend=$this->db->jdate($obj->lastsend);
		
			$this->db->free($resql);
		
			return dol_print_date($datelastsend,$date_format);
		}
		$this->error = "Error " . $this->db->lasterror();
		dol_syslog(get_class($this) . "::getLastActionEmailSend " . $this->error, LOG_ERR);
		return -1;
	}
	
	/**
	 * Create action in actioncomm for email sending
	 *
	 *
	 * @param string $from from email
	 * @param string $sendto send to email
	 * @param string $sendtoid send to id contact email
	 * @param string $sendtocc copy to email
	 * @param string $subject subject email
	 * @param string $message message email
	 * @param User $user user do action
	 * 
	 * @return int <0 if KO, >0 if OK
	 */
	public function createAction($from,$sendto,$sendtoid,$sendtocc,$subject,$message,$user) {
		
		global $langs;
		$langs->load('dunning@dunning');
		
		$actiontypecode='AC_DUNNING_S';
		$actionmsg=$langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto.",".$sendtocc."\n";
		if ($message)
		{
			$actionmsg.=$langs->transnoentities('MailTopic').": ".$subject."\n";
			$actionmsg.=$langs->transnoentities('TextUsedInTheMessageBody').":\n";
			$actionmsg.=$message;
		}
		
		
		require_once (DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php');
		require_once (DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php');
		require_once (DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php');
		$contactforaction = new Contact ( $this->db );
		$societeforaction = new Societe ( $this->db );
		if ($sendtoid > 0)
			$contactforaction->fetch ( $sendtoid );
		if (!empty($this->fk_company))
			$societeforaction->fetch ( $this->fk_company );
		
		// Insertion action
		$actioncomm = new ActionComm ( $this->db );
		$actioncomm->type_code = $actiontypecode;
		$actioncomm->code = $actiontypecode;
		$actioncomm->label = $langs->transnoentities('DunningSendByMail');
		$actioncomm->note = $actionmsg;
		$actioncomm->datep = dol_now();
		$actioncomm->datef = dol_now();
		$actioncomm->durationp = 0;
		$actioncomm->punctual = 1;
		$actioncomm->percentage = - 1; // Not applicable
		$actioncomm->contact = $contactforaction;
		$actioncomm->societe = $societeforaction;
		$actioncomm->author = $user; // User saving action
		// $actioncomm->usertodo = $user; // User affected to action
		$actioncomm->userdone = $user; // User doing action
		$actioncomm->fk_element = $this->id;
		$actioncomm->elementtype = $this->element;
		$ret = $actioncomm->add ( $user ); // User qui saisit l'action
		if ($ret < 0) {
			$this->error=$actioncomm->error;
			return -1;			
		} else {
			return 1;
		}
	}
	
	/**
	 * find dunning for a invoice
	 *
	 * @param int $invoiceid invoice id
	 * @param int $dunning_type Dunning type 'auto', or 'manual'
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch_by_invoice($invoiceid, $dunning_type='') {
		
		$list = array();
		
		$sql = 'SELECT ';
		$sql .= 'fk_dunning ';
		$sql .= 'FROM ' . MAIN_DB_PREFIX . 'dunning_invoice';
		$sql .= ' WHERE ';
		$sql .= 'fk_invoice = ' .$invoiceid;
		
		$resql = $this->db->query($sql);
		
		if($resql) {
			$i = 0;
			while($i < $this->db->num_rows($resql)) {
				$dunning = new Dunning($this->db);
				$dunning->fetch($this->db->fetch_object($resql)->fk_dunning);
				if (!empty($dunning_type)) {
					if ($dunning->mode_creation==$dunning_type) {
						array_push($list, $dunning);
					}
				} else {
					array_push($list, $dunning);
				}
				$i++;
			}
		}else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetch_by_invoice " . $this->error, LOG_ERR);
			return -1;
		}
		
		return $list;
	}
}
