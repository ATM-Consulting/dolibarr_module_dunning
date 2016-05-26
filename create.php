<?php
/* Dunning management
 * Copyright (C) 2014 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2014 Florian HENRY <florian.henry@open-concept.pro>
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
 *    \file         create.php
 *    \ingroup      dunning
 *    \brief        Creation page
 */


// Load environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include("../main.inc.php");
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include("../../main.inc.php");
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include("../../../main.inc.php");
}
if (!$res) {
	die("Main include failed");
}

global $db, $langs, $user;

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'facture');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}

require_once 'class/dunning.class.php';
require_once 'class/dunninginvoice.class.php';
require_once 'lib/dunning.lib.php';
require_once 'core/modules/dunning/modules_dunning.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';


// Load translation files required by the page
$langs->load('dunning@dunning');
$langs->load('bills');

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$company_id = GETPOST('company', 'int');
$search = GETPOST('search_x','int');
$search_month=GETPOST('search_month', 'alpha');
$search_year=GETPOST('search_year', 'int');
$usedelay=GETPOST('search_usedelay','int');

// Do we click on purge search criteria ?
if (GETPOST ( "button_removefilter_x" )) {
	$search_month='';
	$search_year='';
	$usedelay='';
}


// Objects
$dunning = new Dunning($db);
$company = new Societe($db);

// Load objects
if($id) {
	$dunning->fetch($id);
}
if($company_id) {
	$company->fetch($company_id);
}

/*
 * ACTIONS
 */
if($action === 'create') {
	$invoices = GETPOST('invoices', 'array');
	
	$dunning->dated = dol_mktime ( 0, 0, 0, GETPOST ( 'dunningmonth', 'int' ), GETPOST ( 'dunningday', 'int' ), GETPOST ( 'dunningyear', 'int' ) );
	
	$dunning->mode_creation='manual';
	$dunning->model_pdf=$conf->global->DUNNING_ADDON_PDF;
	
	$obj = empty ( $conf->global->DUNNING_ADDON ) ? 'mod_dunning_simple' : $conf->global->DUNNING_ADDON;
	$path_rel = dol_buildpath ( '/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php' );
	if (! empty ( $conf->global->DUNNING_ADDON ) && is_readable ( $path_rel )) {
		dol_include_once ( '/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php' );
		$modDunning= new $obj ();
		$defaultref = $modDunning->getNextValue ( $company, $dunning );
	}
	
	$dunning->ref=$defaultref;
	
	$result = $dunning->create($user, $company, $invoices);
	if ($result<O) {
		setEventMessage($dunning->error,'errors');
	} else {
		
		
		//Create the PDF on dunning creation
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
			$newlang = $company->default_lang;
		}
		if (!empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result = dunning_pdf_create($db, $dunning, $dunning->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
		//print '$result='.$result;
		if ($result <= 0) {
			dol_print_error($db, $result);
			exit();
		}
		
		
		Header ('Location:'.dol_buildpath('/dunning/dunning.php?id='.$dunning->id,1));
	}
	
	
} elseif ($action=='masscreation') {
	$error=0;
	$customerlist=GETPOST('companiesid', 'array');

	if (is_array($customerlist) && count($customerlist)>0) {
		foreach($customerlist as $cust) {
			$invoices=array();
			if($cust) {
				$company->fetch($cust);
			}
			
			$list = getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year, $usedelay,$usedelay);
			if (is_array($list) && count($list)>0) {
				foreach($list as $invoices_object) {
					
					$invoices[]=$invoices_object->id;
				}
				
				$dunning->mode_creation='manual';
				$dunning->model_pdf=$conf->global->DUNNING_ADDON_PDF;
				
				
				$obj = empty ( $conf->global->DUNNING_ADDON ) ? 'mod_dunning_simple' : $conf->global->DUNNING_ADDON;
				$path_rel = dol_buildpath ( '/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php' );
				if (! empty ( $conf->global->DUNNING_ADDON ) && is_readable ( $path_rel )) {
					dol_include_once ( '/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php' );
					$modDunning= new $obj ();
					$defaultref = $modDunning->getNextValue ( $company, $dunning );
				}
				
				$dunning->ref=$defaultref;
				
				$result = $dunning->create($user, $company, $invoices);
				if ($result<O) {
					setEventMessage($dunning->error,'errors');
					$error++;
				}
				
				//Create the PDF on dunning creation
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
					$newlang = $company->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$result = dunning_pdf_create($db, $dunning, $dunning->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				if ($result <= 0) {
					$error++;
					dol_print_error($db, $result);
					exit();
				}
				
				
				
			}
			if ($result<O) {
				setEventMessage($dunning->error,'errors');
				$error++;
			}
		}
	}
	if (empty($error)) {
		Header ('Location:'.dol_buildpath('/dunning/index.php',1));
	}
}

/*
 * VIEW
 */
$form = new Form($db);
$formother = new FormOther ( $db );

$title = $langs->trans('NewDunning');

llxHeader('', $title);



echo '<form name="dunning" action="', $_SERVER["PHP_SELF"], '" method="post">';


// We need a company to continue processing
if ($company_id <= 0) {
		
	
		
	$result = $dunning->fetch_thirdparty_with_unpaiyed_invoice($user,$usedelay,$usedelay);
	if ($result<0) {
		setEventMessage($dunning->error,'errors');
	} else {
		print_fiche_titre($langs->trans('NewDunningMass'));
		
		echo $langs->trans ( 'DunningIvociePeriod' ) .': '
			,$langs->trans ( 'Month' ) . ':<input class="flat" type="text" size="4" name="search_month" value="' . $search_month . '">'
			,$langs->trans ( 'Year' ) . ':' . $formother->selectyear ( $search_year ? $search_year : - 1, 'search_year', 1, 20, 5 );
		
			if (!empty($usedelay)){
				$checked='checked="checked"';
			} else {
				$checked='';
			}
			echo $langs->trans ( 'UseDelay', $conf->facture->client->warning_delay/60/60/24 ) . ':<input type="checkbox" '.$checked.' name="search_usedelay" value="1"/>'
			,'<input class="liste_titre" name="search" type="image" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" title="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '">'
			,'<input type="image" class="liste_titre" name="button_removefilter" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/searchclear.png" value="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '">';
		
		echo  '<script type="text/javascript">
			$(function () {
				$(\'#select-all\').click(function(event) {   
				    // Iterate each checkbox
				    $(\':checkbox\').each(function() {
				    	this.checked = true;                    
				    });
			    });
			    $(\'#unselect-all\').click(function(event) {   
				    // Iterate each checkbox
				    $(\':checkbox\').each(function() {
				    	this.checked = false;                    
				    });
			    });
			});
			 </script>';
		
		echo '<table class="liste allwidth">';
		echo '<th class="liste_titre">'
			,'<label id="select-all">'
			,$langs->trans('All')
			,'</label>'
			,'/'
			,'<label id="unselect-all">'
			,$langs->trans('None')
			,'</label>'			
			,'</th>';
		print_liste_field_titre($langs->trans('Customer'));
		print_liste_field_titre($langs->trans('Bill'));
	
		$var = true;
		foreach($dunning->lines as $line) {
			
			
			
			$company->fetch(key($line));
			
			$list = getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year,$usedelay);
			$invoicelist_text='<table>';
			$is_invoice_late=false;
			if (is_array($list) && count($list>0)) {
				foreach($list as $invoicelate) {
					$is_invoice_late=true;
					$invoicelist_text.= '<tr><td>';
					
					$dunningstatic= new Dunning($db);
					$listduning=$dunningstatic->fetch_by_invoice($invoicelate->id);
					if ($listduning<0) {
						setEventMessage($dunningstatic->error,'errors');
					}
					if (is_array($listduning) && count($listduning)>0) {
						$datelastsend=$listduning[0]->getLastActionEmailSend('daytextshort');
						if (!empty($datelastsend)) {
							$invoicelist_text.=img_picto($langs->trans('AlreadySend'), 'warning');
						}
					}
					
					$invoicelist_text.=$invoicelate->getNomURL(1).
					'</td><td width="150px">'.
					$langs->trans('TotalHT').
					': '.
					price($invoicelate->total_ht, 0, $langs, 1, -1, -1, $conf->currency).
					'</td><td width="150px">'.
					$langs->trans('TotalTTC').
					': '.
					price($invoicelate->total_ttc, 0, $langs, 1, -1, -1, $conf->currency).
					'</td><td width="200px">'.$langs->trans('DateValidation').':'.dol_print_date($invoicelate->date_validation,'daytextshort').
					'</td><td width="200px">'.$langs->trans('Date Facture').':'.dol_print_date($invoicelate->date,'daytextshort');
					
					
					dol_include_once('/agefodd/class/agefodd_session_element.class.php');
					dol_include_once('/agefodd/class/agsession.class.php');
					$agf_fin=new Agefodd_session_element($db);
					$agf_fin->fetch_element_by_id($invoicelate->id,'fac');
					if (is_array($agf_fin->lines) && count($agf_fin->lines)>0) {
						$invoicelist_text.='</td><td width="300px">';
						
						foreach($agf_fin->lines as $line) {
							$session=new Agsession($db);
							$session->fetch($line->fk_session_agefodd);
							$invoicelist_text.=$session->getNomUrl(1);
						}
						
					}

					$invoicelist_text.='</td></tr>';
				}
			}
			$invoicelist_text.='</table>';
			
			if ($is_invoice_late) {
				$var = ! $var;
				echo '<tr '
					,$bc[$var]
					,'>',
					'<td align="center">',
					'<input name="companiesid[]" value="', $company->id, '" type="checkbox">',
					'</td>',
					'<td width="20%">',
					$company->getNomUrl(1),
					'</td>',
					'<td>'
					,$invoicelist_text
					,'</td>'
					,'</tr>';
			}
		}
		echo '</table>';
		
		// Massive Create button
		echo '<p class="center">',
		'<button type="submit" class="button" name="action" value="masscreation">',
		$langs->trans('NewDunningMass'),
		'</button>',
		'</p>';
		
	}

	print_fiche_titre($langs->trans('NewDunningUnique'));
	echo '<table class="border allwidth">';
	echo '<tr>',
	'<td class="fieldrequired">',
	$langs->trans('Customer'),
	'</td>',
	'<td>',
	$form->select_company('', 'company', 's.client = 1 OR s.client = 3', 1),
	'</td>',
	'</tr>',
	'</table>';

	// Create button
	echo '<p class="center">',
	'<button type="submit" class="button" name="action" value="company">',
	$langs->trans('OK'),
	'</button>',
	'</p>';

	echo '</form>';

	// Page end
	llxFooter();
	$db->close();
	exit();
}

// Thirdparty
echo '<tr>',
'<td class="fieldrequired">',
$langs->trans('Customer'),
'</td>',
'<td>',
$company->getNomUrl(1),
'<input type="hidden" name="company" value="', $company_id, '">',
'</td>',
'</tr>';

// Date
echo '<tr>',
'<td class="fieldrequired">',
$langs->trans('Date'),
'</td>',
'<td>';
$form->select_date('', 'dunning', '', '', '', 'dunning', 1, 1);
echo '</td>',
'</tr>';

echo '</table>';

// Unpaid invoices list
$list = getListOfLateUnpaidInvoices($db, $company);
if ($list) {
	echo '<table class="liste">',
	'<tr class="liste_titre">',
	'<th>',
	$langs->trans('Use'),
		// TODO: add all/none selector
	'</th>',
	'<th>',
	$langs->trans('Ref'),
	'</th>',
	'<th>',
	$langs->trans('Date'),
	'</th>',
	'<th>',
	$langs->trans('Late'), ' (', $langs->trans('days'), ')',
	'</th>',
	'<th class="right">',
	$langs->trans('Amount'),
	'</th>',
	'<th class="right">',
	$langs->trans('Rest'),
	'</th>',
	'</tr>';

	foreach ($list as $invoice) {
		echo '<tr>',
		'<td>',
		'<input name="invoices[]" value="', $invoice->id, '" type="checkbox" checked="checked">',
		'</td>',
		'<td>',
		$invoice->getNomUrl(1),
		'</td>',
		'<td>',
		dol_print_date($invoice->date, 'day'),
		'</td>',
		'<td>',
		num_between_day($invoice->date_lim_reglement, dol_now()),
		'</td>',
		'<td class="right">',
		price($invoice->total_ttc),
		'</td>',
		'<td class="right">',
		price(getRest($invoice), 1, $langs, 1, 2, 2),
		'</td>',
		'</tr>';
	}

	// TODO: add a total

	echo '</table>';

	// Create button
	echo '<p class="center">',
	'<button type="submit" class="button" name="action" value="create">',
	$langs->trans('Create'),
	'</button>',
	'</p>';
} else {
	/*
	 * This customer don't have unpaid invoices
	 * Let's get him back to client selection
	 */
	echo '<p>',
	'<em>',
	$langs->trans('NoUnpaidInvoice'),
	'</em>',
	'</p>',
	'<input type="hidden" name="company" value="0">',
	'<p class="center">',
	'<button type="submit" class="button" name="action" value="reset">',
	$langs->trans('OK'),
	'</button>',
	'</p>';
}

echo '</form>';

// Page end
llxFooter();
$db->close();
