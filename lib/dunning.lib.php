<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/dunning.lib.php
 *	\ingroup	dunning
 *	\brief		This file is an example module library
 *				Put some comments here
 */

/**
 * Prepare header for admin page
 *
 * @return array Page tabs
 */
function dunningAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("dunning@dunning");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/dunning/admin/admin_dunning.php", 1);
    $head[$h][1] = $langs->trans("DunningSetup");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/dunning/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@dunning:/dunning/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@dunning:/dunning/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'dunning');

    return $head;
}

/**
 * List all invoices late and unpaid
 *
 * @param DoliDb $db Database connection object
 * @param Societe $company Company filter
 * @param string $monthperiod month invoice validation date filter
 * @param int $yearperiod year invoice validation date filter 
 * @param int $usedelay use delay setup into Setup->Alerts
 * @param int $usedatevalid use date valid as filter
 * @return array List of unpaid invoices objects
 */
function getListOfLateUnpaidInvoices($db, $company = null, $monthperiod=0, $yearperiod=0, $usedelay=0,$usedatevalid=0,$donotsendauto=false)
{
	global $conf;

	$list = array();

	$sql = 'SELECT
	facture.rowid
	FROM ' . MAIN_DB_PREFIX . 'facture as facture ';
	if ($donotsendauto) {
		$sql .= ' LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'facture_extrafields as factextra ON factextra.fk_object=facture.rowid ';
	}
	$sql .= ' WHERE 
	
			-- Multicompany support
	facture.entity = ' . $conf->entity;
	
	if ($donotsendauto) {
		$sql .= ' AND (factextra.fact_no_dunning IS NULL OR factextra.fact_no_dunning=0) ';
	}
	
	if($company->id) {
		$sql .= '
			-- Filter by company
			AND facture.fk_soc = ' . $company->id;
	}
	if(!empty($usedelay)){
		$date_test_valid=dol_now() - $conf->facture->client->warning_delay;
	}else {
		$date_test_valid=dol_now();
	}
	$sql .= '
	-- Standard, replacement and deposit invoices
	AND facture.type IN (0, 1, 3)
	-- Openned or partially paid
	AND facture.fk_statut IN (1, 2)
	-- Not fully paid
	AND facture.paye <> 1
	-- Not forcibly closed
	AND facture.close_note IS NULL
	-- Due date expired';
	if (!empty($usedatevalid)) {
		$sql .= '
			AND facture.date_valid < \'' . $db->idate($date_test_valid).'\'';
	} else {
		$sql .= '
			AND facture.date_lim_reglement < \'' . $db->idate($date_test_valid).'\'';
	}
	
	if (!empty($yearperiod)) {
		$sql .= ' AND YEAR(facture.date_valid)='.$yearperiod;
	}
	if (!empty($monthperiod)) {
		$sql .= ' AND MONTH(facture.date_valid) IN ('.$monthperiod.')';
	}

	dol_syslog('dunning.lib.php:: getListOfLateUnpaidInvoices sql='.$sql);
	$resql = $db->query($sql);

	if($resql) {
		$i = 0;
		while($i < $db->num_rows($resql)) {
			$invoice = new Facture($db);
			$invoice->fetch($db->fetch_object($resql)->rowid);
			array_push($list, $invoice);
			$i++;
		}
	}

	return $list;
}

/**
 * Compute the rest from an invoice
 *
 * @param Facture $invoice The invoice to process
 * @return float
 */
function getRest($invoice)
{
	// TODO: add to upstream invoice object
	$restToPay = $invoice->total_ttc -
		$invoice->getSommePaiement() -
		$invoice->getSumDepositsUsed() -
		$invoice->getSumCreditNotesUsed();
	
	if ($restToPay<0) {
		$restToPay=0;
	}
	return $restToPay;
}

/**
 * Get List of oppened Dunning
 *
 * @param DoliDb $db Database connection object
 * @param array $filter Array of filters to apply on request
 * @param string $sortfield sort field
 * @param string $sortorder sort order
 * @param int $limit page
 * @param int $offset
 * @return float
 */
function getListOfOpennedDunnings($db,$filter=array(),$sortfield='',$sortorder='',$limit=0, $offset=0)
{
	// FIXME: only return openned dunnings
	global $conf;

	$list = array();

	$sql  = 'SELECT
	dunning.rowid
	FROM ' . MAIN_DB_PREFIX . 'dunning as dunning
	LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'societe as societe ON dunning.fk_company=societe.rowid
	LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'societe_commerciaux as salesman ON salesman.fk_soc=societe.rowid
	WHERE
	-- Multicompany support
	dunning.entity = ' . $conf->entity  ;
	
	if (count($filter) > 0) {
		foreach ($filter as $key => $value) {
			if ($key=='id') {
				$sql .= ' AND dunning.rowid = ' . $value;
			} elseif (strpos($key, 'date')!==false) {
				$sql .= ' AND ' . $key . ' = \'' .  $value . '\'';
			} else {
				$sql .= ' AND ' . $key . ' LIKE \'%' . $db->escape($value). '%\'';
			}
		}
	}
	if (!empty($sortfield)) {
		$sql.= " ORDER BY ".$sortfield." ".$sortorder;
	}
	if (! empty($limit)) {
		$sql .= ' ' . $db->plimit($limit + 1, $offset);
	}

	dol_syslog('dunning.lib.php::getListOfOpennedDunnings sql='.$sql);
	$resql = $db->query($sql);

	if ($resql) {
		$i = 0;
		while($i < $db->num_rows($resql)){
			array_push($list, $db->fetch_object($resql)->rowid);
			$i++;
		}
	}else {
		setEventMessage($db->lasterror(),'errors');
	}

	return $list;
}
