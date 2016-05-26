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
 *	\file		index.php
 *	\ingroup	dunning
 *	\brief		List page
 */

// Load environment
$res = 0;
if (! $res && file_exists("../main.inc.php")) {
	$res = @include("../main.inc.php");
}
if (! $res && file_exists("../../main.inc.php")) {
	$res = @include("../../main.inc.php");
}
if (! $res && file_exists("../../../main.inc.php")) {
	$res = @include("../../../main.inc.php");
}
if (! $res) {
	die("Main include failed");
}

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'facture');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}

require_once 'class/dunning.class.php';
require_once 'lib/dunning.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once 'core/modules/dunning/modules_dunning.php';

// Load translation files required by the page
$langs->load("dunning@dunning");

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$display_all=GETPOST('displayall', 'int');
$search_ref=GETPOST('search_ref', 'alpha');
$search_date=dol_mktime ( 0, 0, 0, GETPOST ( 'search_datemonth', 'int' ), GETPOST ( 'search_dateday', 'int' ), GETPOST ( 'search_dateyear', 'int' ) );
$search_soc=GETPOST('search_soc', 'alpha');
$search_mode=GETPOST('search_mode', 'alpha');
if ($search_mode==-1) $search_mode='';
$search_sale=GETPOST('search_sale','alpha');
$option = GETPOST('option');
$sendmail=GETPOST('sendmail');
if (!empty($sendmail)) $action='sendmail';

// Do we click on search criteria ?
if (GETPOST ( "button_search_x" )) {
	$action='';
}

$sortorder = GETPOST ( 'sortorder', 'alpha' );
$sortfield = GETPOST ( 'sortfield', 'alpha' );
$page = GETPOST ( 'page', 'int' );

if (empty($sortfield)) $sortfield='societe.nom';
if (empty($sortorder)) $sortorder='asc';

$diroutputpdf=$conf->dunning->dir_output . '/merged';

$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

/*
 * ACTIONS
 */

// Do we click on purge search criteria ?
if (GETPOST ( "button_removefilter_x" )) {
	$search_ref = '';
	$search_soc = '';
	$search_date = '';
	$search_mode = '';
	$search_sale='';
}



$filter_search_title ='';
$filter = array ();
if (! empty ( $search_id )) {
	$filter ['dunning.ref'] = $search_ref;
	$filter_search_title .= '&search_ref=' . $search_ref;
}
if (! empty ( $search_soc )) {
	$filter ['societe.nom'] = $search_soc;
	$filter_search_title .= '&search_soc=' . $search_soc;
}
if (! empty ( $search_date )) {
	$filter ['dunning.dated'] = $db->idate($search_date);
	$filter_search_title .= '&search_datemonth=' . dol_print_date ( $search_date, '%m' ) . '&search_dateday=' . dol_print_date ( $search_date, '%d' ) . '&search_dateyear=' . dol_print_date ( $search_date, '%Y' );
}
if (! empty ( $search_mode )) {
	$filter ['dunning.mode_creation'] = $search_mode;
	$filter_search_title .= '&search_mode=' . $search_mode;
}
if (! empty ( $search_sale )) {
	$filter ['salesman.fk_user'] = $search_sale;
	$filter_search_title .= '&search_sale=' . $search_sale;
}

if ($action == "builddoc")
{
	if (is_array($_POST['toGenerate']))
	{		
		$arrayofinclusion=array();
		foreach($_POST['toGenerate'] as $tmppdf) $arrayofinclusion[]=preg_quote($tmppdf.'.pdf','/');
		$dunnings = dol_dir_list($conf->dunning->dir_output,'all',1,implode('|',$arrayofinclusion),'\.meta$|\.png','date',SORT_DESC);
	
		$now=dol_now();
		
		// liste les fichiers
		$files = array();
		$factures_bak = $dunnings ;
		foreach($_POST['toGenerate'] as $basename){
			foreach($dunnings as $dunningfile){
				if(strstr($dunningfile["name"],$basename)){
					$files[] = $conf->dunning->dir_output.'/'.$basename.'/'.$dunningfile["name"];
				}
				
				$dunningdatep=new Dunning($db);
				$resultdatep=$dunningdatep->fetch(0,$dunningfile['level1name']);
				if ($resultdatep<0) {
					setEventMessage($dunningdatep->error,'errors');
				} else {
					$dunningdatep->datep=$now;
					$resultdatep=$dunningdatep->update($user,1);
					if ($resultdatep<0) {
						setEventMessage($dunningdatep->error,'errors');
					} else {
						
						$company = new Societe($db);
						$result=$company->fetch($dunningdatep->fk_company);
						if ($result<0) {
							setEventMessage($company->error,'errors');
						} else {
							//Recreate PDF to update dunning date if necessary
							$outputlangs = $langs;
							$newlang = '';
							if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
								$newlang = $company->default_lang;
							}
							if (!empty($newlang)) {
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
							$result = dunning_pdf_create($db, $dunningdatep, $dunningdatep->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							//print '$result='.$result;
							if ($result <= 0) {
								dol_print_error($db, $result);
								exit();
							}
						}
						
					}
				}
			}
		}
	
		// Define output language (Here it is not used because we do only merging existing PDF)
		$outputlangs = $langs;
		$newlang='';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id')) $newlang=GETPOST('lang_id');
		if (! empty($newlang))
		{
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
	
		// Create empty PDF
		$pdf=pdf_getInstance();
		if (class_exists('TCPDF'))
		{
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
	
		if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);
	
		// Add all others
		foreach($files as $file)
		{
			// Charge un document PDF depuis un fichier.
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++)
			{
			$tplidx = $pdf->importPage($i);
			$s = $pdf->getTemplatesize($tplidx);
			$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
			}
	
			// Create output dir if not exists
			dol_mkdir($diroutputpdf);
	
			// Save merged file
			$filename=strtolower(dol_sanitizeFileName($langs->transnoentities("DunningContactFileName")));
				
					if ($pagecount)
					{
						$now=dol_now();
						$file=$diroutputpdf.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.pdf';
						$pdf->Output($file,'F');
						if (! empty($conf->global->MAIN_UMASK))
							@chmod($file, octdec($conf->global->MAIN_UMASK));
						}
					else
					{
						setEventMessage($langs->trans('NoPDFAvailableForChecked'),'errors');
					}
	}
	else
	{
		setEventMessage($langs->trans('NoDunningSelected'),'errors');
	}
}
elseif ($action=='remove_file') {

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$langs->load("other");
	$upload_dir = $diroutputpdf;
	$file = $upload_dir . '/' . GETPOST('file');
	$ret=dol_delete_file($file,0,0,0,'');
	if ($ret) setEventMessage($langs->trans("FileWasRemoved", GETPOST('urlfile')));
	else setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('urlfile')), 'errors');
	$action='';

}
elseif($action == 'sendmail') {
	
	$langs->load('mails');
	$langs->load("commercial");
	
	
	// For each dunning selected
	foreach ($_POST['sendmaildunning'] as $id_dunning) {
		
		$error = 0;
		
		$dunningbymail = new Dunning($db);
		$dunningbymail->fetch($id_dunning);
		$companymail = new Societe($db);
		$companymail->fetch($dunningbymail->fk_company);
		
		// Define output language
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang))
			$newlang = $companymail->default_lang;
		if (! empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
			$outputlangs->load('dunning@dunning');
		}
		
		// Get the first contact of the first invoice
		$invoices = $dunningbymail->getInvoices();
		if (count($invoices) > 0) {
			$invoice = $invoices[0];
			$arrayidcontact = $invoice->getIdContact('external', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $invoice->fetch_contact($arrayidcontact[0]);
			}
			if (! empty($invoice->contact)) {
				$custcontact = $invoice->contact->getFullName($outputlangs, 1);
			}
		}
		
		if (empty($invoice->contact->email)) {
			setEventMessage($langs->trans('CannotSendMailDunningInvoiceContact',$dunningbymail->ref), 'errors');
		}else {
			$sendto = $invoice->contact->getFullName($outputlangs) . ' <' . $invoice->contact->email . '>';
		}
		
		if (empty($user->email)) {
			setEventMessage($langs->trans('CannotSendMailDunningEmailFrom',$dunningbymail->ref), 'errors');
			$error ++;
		}

		if (! $error) {
			$from = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
			$replyto = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
			$message = str_replace("__SIGNATURE__",$user->signature,$outputlangs->transnoentities('SendReminderDunningRef'));
			$sendtocc = '';
			
			//Determine for akteos the Requester contact of the session/invoice
			if (($companymail->typent_code!='TE_OPCA') && ($companymail->typent_code!='TE_PAY')) {
				dol_include_once('/agefodd/class/agefodd_session_element.class.php');
				dol_include_once('/agefodd/class/agsession.class.php');
				$agf_fin=new Agefodd_session_element($db);
				$email_to_send=array($invoice->contact->id);
				foreach($invoices as $invoice) {
					$agf_fin->fetch_element_by_id($invoice->id,'fac');
					if (is_array($agf_fin->lines) && count($agf_fin->lines)>0) {
						foreach($agf_fin->lines as $line) {
							$session=new Agsession($db);
							$session->fetch($line->fk_session_agefodd);
							if (!empty($session->fk_socpeople_requester)) {
								$contact_requester=new Contact($db);
								$contact_requester->fetch($session->fk_socpeople_requester);
								if (!empty($contact_requester->email) && (!in_array($contact_requester->id,$email_to_send))) {
									if (!empty($sendto)) $sendto .= ", ";
									$sendto .= $contact_requester->getFullName($outputlangs) . ' <' . $contact_requester->email . '>';
									$email_to_send[]=$contact_requester->id;
								}
							}
						}
					}
				}
			}
			
			
			$deliveryreceipt = 0;
			$subject = $mysoc->name . '-' . $outputlangs->transnoentities('SendReminderDunningTopic');
			
			// Create form object
			include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
			$formmail = new FormMail($db);
			
			$filename = dol_sanitizeFileName($dunningbymail->ref);
			$filedir = $conf->dunning->dir_output . '/' . $filename;
			$file = $filedir . '/' . $dunningbymail->ref . '.pdf';
			
			$attachedfiles = $formmail->get_attached_files();
			$filepath = array($file);
			$filename = array($dunningbymail->ref . '.pdf');
			$mimetype = array(dol_mimetype($dunningbymail->ref . '.pdf'));
			
			// Send mail
			if (! empty($sendto) && !empty($from)) {
				
				require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
				$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1);
				if (! empty($mailfile->error)) {
					setEventMessage($mailfile->error, 'errors');
				} else {
					$result = $mailfile->sendfile();
					if ($result) {
						$error = 0;
						
						$result = $dunningbymail->createAction($from, $sendto, $sendtoid, $sendtocc, $subject, $message, $user);
						if ($result < 0) {
							$error ++;
							setEventMessage($dunningbymail->error, 'errors');
						}
						
						if (empty($error)) {
							// Appel des triggers
							include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
							$interface = new Interfaces($db);
							$result = $interface->run_triggers('DUNNING_SENTBYMAIL', $dunningbymail, $user, $langs, $conf);
							if ($result < 0) {
								$error ++;
								setEventMessage($interface->error, 'errors');
							}
						}
						// Fin appel triggers
						
						if (empty($error)) {
							// Redirect here
							// This avoid sending mail twice if going out and then back to page
							$mesg = $langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($sendto, 2));
							setEventMessage($mesg, 'mesgs');
							$action = '';
						}
					} else {
						$langs->load("other");
						if ($mailfile->error) {
							$mesg = $langs->trans('ErrorFailedToSendMail', $from, $sendto);
							setEventMessage($mailfile->error . '<BR>' . $mesg, 'errors');
						} else {
							setEventMessage('No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS', 'errors');
						}
					}
				}
			}
		}
	}
	$action = '';
}

/*
 * VIEW
 */
$title = $langs->trans('Module105301Name');

$form=new Form($db);
$formother=new FormOther($db);
$formfile = new FormFile($db);

llxHeader('',$title);

// Count total nb of records
$nbtotalofrecords = 0;
if (empty ( $conf->global->MAIN_DISABLE_FULL_SCANLIST )) {
	$alldunning = getListOfOpennedDunnings($db,$filter,$sortfield,$sortorder,0,0);
	$nbtotalofrecords=count($alldunning);
}
$list = getListOfOpennedDunnings($db,$filter,$sortfield,$sortorder,$limit,$offset);


print_barre_liste ( $title, $page, $_SERVEUR ['PHP_SELF'], $filter_search_title, $sortfield, $sortorder, '', count($list), $nbtotalofrecords );


if (empty($display_all)) {
echo '<a href="'.$_SERVER['PHP_SELF'].'?displayall=1">',
		$langs->trans('DisplayAllDunning'),
		'</a>';
} else {
	echo '<a href="'.$_SERVER['PHP_SELF'].'">',
	$langs->trans('HideDunningWithNoRestToPay'),
	'</a>';
}



if($list) {
	
	echo '<form method="post" action="' . $_SERVER ['PHP_SELF'] . '" name="search_form">' . "\n";
	
	echo $langs->trans ( 'SalesRepresentatives' ) . ': ';
	echo $formother->select_salesrepresentatives ( $search_sale, 'search_sale', $user );
	
	
	echo '<input type="hidden" name="displayall" value="'.$display_all.'">';
	echo '<table class="liste allwidth">',
	'<tr class="liste_titre">';

	// Table headers
	print_liste_field_titre($langs->trans('Ref'), $_SERVEUR ['PHP_SELF'], "dunning.ref", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Date'), $_SERVEUR ['PHP_SELF'], "dunning.dated", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Customer'), $_SERVEUR ['PHP_SELF'], "societe.nom", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('CreationMode'), $_SERVEUR ['PHP_SELF'], "dunning.mode_creation", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Amount'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Rest'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('LastSendEmailDate'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('LastPrintDate'), $_SERVEUR ['PHP_SELF'], "dunning.datep", "", $filter_search_title, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('PDFMerge'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, 'align=middle', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('SendEmail'), $_SERVEUR ['PHP_SELF'], "", "", $filter_search_title, 'align=middle', $sortfield, $sortorder);
	echo '</tr>';
	
	'<tr class="liste_titre">';
	
	// Table headers
	echo '<td class="liste_titre">';
	echo '<input type="text" class="flat" name="search_ref" value="' . $search_ref . '" size="4">';
	echo '</td>';
	
	echo '<td class="liste_titre">';
	echo $form->select_date ( $search_date, 'search_date', 0, 0, 1, 'search_form' );
	echo '</td>';
	
	
	echo '<td class="liste_titre">';
	echo '<input type="text" class="flat" name="search_soc" value="' . $search_soc . '" size="20">';
	echo '</td>';
	
	
	echo '<td class="liste_titre">';
	echo $form->selectarray ( 'search_mode', array('manual'=>$langs->trans('DunningCreaMode_manual'),'auto'=>$langs->trans('DunningCreaMode_auto')), $search_mode, 1 );
	echo '</td>';
	
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo'<td class="liste_titre"></td>';
	echo '<td class="liste_titre" align="right"><input class="liste_titre" type="image" name="button_search" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" value="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "Search" ) ) . '">';
	echo '&nbsp; ';
	echo '<input type="image" class="liste_titre" name="button_removefilter" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/searchclear.png" value="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '" title="' . dol_escape_htmltag ( $langs->trans ( "RemoveFilter" ) ) . '">';
	echo '</td>';
	echo '</tr>';

	
	$var = true;
	$amount_total=0;
	$rest_to_pay_total=0;
	// List available dunnings
	foreach ($list as $id) {

		$dunning = new Dunning($db);
		$dunning->fetch($id);
		
		$rest_to_pay=$dunning->getRest();
		//According filter display all dunning or only the one that have a rest to pay available
		if (!empty($display_all)) {
			$display_line=true;
		}else {
			$display_line=($rest_to_pay!=0);
		}
		
		if ($display_line) {
			$var = ! $var;
		
			$company = new Societe($db);
			$company->fetch($dunning->fk_company);
			
			$filename=dol_sanitizeFileName($dunning->ref);
			$filedir=$conf->dunning->dir_output . '/' . dol_sanitizeFileName($dunning->ref);

			$amount_total+=$dunning->amount;
			$rest_to_pay_total+=$rest_to_pay;

			echo '<tr '
			,$bc[$var]
			,'>',
			'<td>',
			$dunning->getNameUrl(),
			$formfile->getDocumentsLink($dunning->element, $filename, $filedir),
			'</td>',
			'<td>',
			dol_print_date($dunning->dated, 'day'),
			'</td>',
			'<td>',
			$company->getNomUrl(1, 'customer'),
			'</td>',
			'<td>',
			$langs->trans('DunningCreaMode_'.$dunning->mode_creation),
			'</td>',
			'<td>',
			price($dunning->amount, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )),
			'</td>',
			'<td>',
			price($rest_to_pay, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )),
			'</td>',
			'<td>',
			$dunning->getLastActionEmailSend('daytextshort'),
			'</td>',
			'<td>',
			dol_print_date($dunning->datep, 'daytextshort'),
			'</td>';
			
			if (! empty($formfile->numoffiles))
				$dunningcheckboxmerge= '<input id="cb'.$dunning->id.'" class="flat checkformerge" type="checkbox" name="toGenerate[]" value="'.$dunning->ref.'">';
			else
				$dunningcheckboxmerge= '&nbsp;';
			
			echo '<td align="middle">',
			$dunningcheckboxmerge,
			'</td>';
			
			echo '<td align="middle">',
			
			
			$no_email_invoice=false;
			$no_email_requester=false;
			
			// Get the first contact of the first invoice
			$invoices = $dunning->getInvoices();
			if (count($invoices) > 0) {
				$invoice = $invoices[0];
				$arrayidcontact = $invoice->getIdContact('external', 'BILLING');
				if (count($arrayidcontact) > 0) {
					$usecontact = true;
					$result = $invoice->fetch_contact($arrayidcontact[0]);
				}
				if (empty($invoice->contact->email) || (!isValidEmail($invoice->contact->email))) {
					print img_picto($langs->transnoentities('CannotSendMailDunningInvoiceContact'), 'warning');
					$no_email_invoice=true;
				}
			}

			//Check email tosend
			//Determine for akteos the Requester contact of the session/invoice
			if (($company->typent_code!='TE_OPCA') && ($company->typent_code!='TE_PAY')) {
				$no_email_requester=false;
				dol_include_once('/agefodd/class/agefodd_session_element.class.php');
				dol_include_once('/agefodd/class/agsession.class.php');
				
				$agf_fin=new Agefodd_session_element($db);
				$email_to_send=array($invoice->contact->id);
				foreach($invoices as $invoice) {
					$agf_fin->fetch_element_by_id($invoice->id,'fac');
					if (is_array($agf_fin->lines) && count($agf_fin->lines)>0) {
						foreach($agf_fin->lines as $line) {
							$session=new Agsession($db);
							$session->fetch($line->fk_session_agefodd);
							if (!empty($session->fk_socpeople_requester)) {
								$contact_requester=new Contact($db);
								$contact_requester->fetch($session->fk_socpeople_requester);
								if (empty($contact_requester->email) || (!isValidEmail($contact_requester->email))) {
									print img_picto($langs->transnoentities('CannotSendMailDunningRequesterContact'), 'warning');
									$no_email_requester=true;
									//print '$contact_requester->email='.$contact_requester->email;
								}
							}
						}
					}
				}
			}else {
				$no_email_requester=true;
			}
			
			if ($no_email_invoice && $no_email_requester) {
				print img_picto($langs->transnoentities('CannotSendMailDunningNoContact'), 'error');
			} else {
				echo '<input id="mail'.$dunning->id.'" class="flat" type="checkbox" name="sendmaildunning[]" value="'.$dunning->id.'">';
			}
			
			
			echo '</td>',
			'</tr>';
		}
	}
	
	print '<tr class="liste_total">';
	print '<td>' . $langs->trans ( 'Total' ) . '</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td>'.price($amount_total, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )).'</td>';
	print '<td>'.price($rest_to_pay_total, 1, $langs, 1, 2, 2, $langs->getCurrencySymbol ( $conf->currency )).'</td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '<td></td>';
	print '</tr>';
	echo '</table>';
	
	
	// Action button "Send Mail"
	echo '<p class="right">',
	'<input type="submit" value="'.$langs->trans('SendEmail').'" name="sendmail" class="butAction">',
	'</p>';
	
	
	$genallowed=1;
	$delallowed=1;
	
	echo '<br>';
	echo '<input type="hidden" name="option" value="'.$option.'">';
	$formfile->show_documents('unpaid','',$diroutputpdf,$_SERVER ['PHP_SELF'],$genallowed,$delallowed,'',1,0,0,48,1,$param,$langs->trans("PDFMerge"),$langs->trans("PDFMerge"));
	echo  '</form>';
	
	//TODO : Hack to update link on document beacuse merge unpaid is always link to unpaid invoice ...
	echo '<script type="text/javascript">
		jQuery(document).ready(function () {
                    	jQuery(function() {
                        	$("a[data-ajax|=\'false\'][href*=\'unpaid\']") 
								.each(function()
								   { 
								      this.href = this.href.replace(/unpaid/, 
								         "dunning");
									  this.href =this.href.replace(/file=/, 
								         "file=merged/")
								   });
                        });
                    });
		</script>';
	
	echo '</form>';
} else {
	// No openned dunning
	echo '<p>',
	$langs->trans('NoOpennedDunning');
	'</p>'
	;
}

// Action button "New"
echo '<p class="right">',
	'<a href="create.php" class="butAction">',
	$langs->trans('NewDunning'),
	'</a>',
	'</p>';

// Page end
llxFooter();
$db->close();
