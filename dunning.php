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
 *	\file		dunning.php
 *	\ingroup	dunning
 *	\brief		Element page
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

global $db, $user, $langs;

// Access control
// Restrict access to users with invoice reading permissions
restrictedArea($user, 'dunning');
if (
	$user->societe_id > 0 // External user
) {
	accessforbidden();
}

require_once 'class/dunning.class.php';
require_once 'class/dunninginvoice.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once 'lib/dunning.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once dol_buildpath('/dunning/core/modules/dunning/modules_dunning.php');

global $conf;

// Load translation files required by the page
$langs->load("dunning@dunning");
$langs->load('bills');

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm');
$removedfilefromemail=GETPOST('removedfile');
if (!empty($removedfilefromemail)) $action='remove_file';

$dunning = new Dunning($db);
$form = new Form($db);

if($id) {
	$dunning->fetch($id);
	$company = new Societe($db);
	$company->fetch($dunning->fk_company);
} else {
	// FIXME: be nicer
	exit("Please provide a dunning ID");
}

/*
 * ACTIONS
 */
switch ($action) {
	case 'builddoc':
		// Save last template used to generate document
		if (GETPOST('model')) {
			$dunning->setDocModel($user, GETPOST('model', 'alpha'));
		}

		// Define output language
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id')) {
			$newlang = GETPOST('lang_id');
		}
		if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
			$newlang = $company->default_lang;
		}
		if (!empty($newlang)) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result = dunning_pdf_create($db, $dunning, $dunning->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
		if ($result <= 0) {
			dol_print_error($db, $result);
			exit();
		}
		break;

	case 'remove_file':
		
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		
		// Set tmp user directory
		$vardir=$conf->user->dir_output."/".$user->id;
		$upload_dir_tmp = $vardir.'/temp';
		
		// TODO Delete only files that was uploaded from email form
		dol_remove_file_process($_POST['removedfile'],0,1);
		$action='presend';
		break;
		
		
		
	case 'confirm_delete':
		if  ($confirm == 'yes' && $user->rights->dunning->write){
			$result = $dunning->delete($user);
			if ($result > 0) {
				header('Location: '.dol_buildpath('/dunning/index.php',1));
				exit;
			} else {
				setEventMessage($dunning->error,'errors');
			}
		}
		break;
		
	case 'remove_invoice':
			$dunninginvoice=new Dunninginvoice($db);
			$dunninginvoice->fk_dunning=$id;
			$dunninginvoice->fk_invoice=GETPOST('invoiceid','int');
			$result = $dunninginvoice->delete($user);
			if ($result < 0) {
				setEventMessage($dunning->error,'errors');
			}
			break;
			
			
}
/*
 * Send mail
 */

if ($action == 'send' && ! $_POST['addfile'] && ! $_POST['removedfile'] && ! $_POST['cancel']) {
	$langs->load('mails');

	if ($_POST['sendto'])
	{
		// Le destinataire a ete fourni via le champ libre
		$sendto = $_POST['sendto'];
		$sendtoid = 0;
	}

	if (dol_strlen($sendto))
	{
		$langs->load("commercial");

		$from = $_POST['fromname'] . ' <' . $_POST['frommail'] .'>';
		$replyto = $_POST['replytoname']. ' <' . $_POST['replytomail'].'>';
		$message = $_POST['message'];
		$sendtocc = $_POST['sendtocc'];
		$deliveryreceipt = $_POST['deliveryreceipt'];
		$subject = $_POST['subject'];

		// Create form object
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		$formmail = new FormMail($db);

		$attachedfiles=$formmail->get_attached_files();
		$filepath = $attachedfiles['paths'];
		$filename = $attachedfiles['names'];
		$mimetype = $attachedfiles['mimes'];

		// Send mail
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		$mailfile = new CMailFile($subject,$sendto,$from,$message,$filepath,$mimetype,$filename,$sendtocc,'',$deliveryreceipt,-1);
		if (!empty($mailfile->error))
		{
			setEventMessage($mailfile->error,'errors');
		}
		else
		{
			$result=$mailfile->sendfile();
			if ($result)
			{
				$error=0;
				
				$result=$dunning->createAction($from,$sendto,$sendtoid,$sendtocc,$subject,$message,$user);
				if ($result<0) {
					$error++;
					setEventMessage($dunning->error,'errors');
				}

				if (empty($error))
				{
					// Appel des triggers
					include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
					$interface=new Interfaces($db);
					$result=$interface->run_triggers('DUNNING_SENTBYMAIL',$dunning,$user,$langs,$conf);
					if ($result < 0) {
						$error++;
						setEventMessage($interface->error,'errors');
					}
				}
				// Fin appel triggers
				
				if (empty($error))
				{
					// Redirect here
					// This avoid sending mail twice if going out and then back to page
					$mesg=$langs->trans('MailSuccessfulySent',$mailfile->getValidAddress($from,2),$mailfile->getValidAddress($sendto,2));
					setEventMessage($mesg,'mesgs');
					$action='';
				}
			}
			else
			{
				$langs->load("other");
				if ($mailfile->error)
				{
					$mesg=$langs->trans('ErrorFailedToSendMail',$from,$sendto);
					setEventMessage($mailfile->error.'<BR>'.$mesg,'errors');
				}
				else
				{
					setEventMessage('No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS','errors');
				}
				
				$action = 'presend';
			}
		}
	}
}
			
/*
 * VIEW
 */
$title = $langs->trans('Module105301Name');

llxHeader('',$title);

$head = array();
$head[0][0] = $_SERVER['PHP_SELF'].'?id='.$id;
$head[0][1] = $langs->trans('DunningRecord');
$head[0][2] = 'dunning';
dol_fiche_head($head, 'dunning', $langs->trans('Dunning'), 0, 'dunning@dunning');


// Confirmation to delete invoice
if ($action == 'delete')
{
	$text=$langs->trans('ConfirmDeleteDunning');
	$formconfirm=$form->formconfirm($_SERVER['PHP_SELF'].'?id='.$dunning->id,$langs->trans('Delete'),$text,'confirm_delete','','',1);
	// Print form confirm
	print $formconfirm;
}


// Dunning record details
// TODO: Navigation arrows ($form->showrefnav)
echo '<table class="border allwidth">',
	// Ref
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('Ref'),
	'</td>',
	'<td class="table-val-border-col">',
	$dunning->ref,
	'</td>',
	'</tr>',
	// Date
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('Date'),
	'</td>',
	'<td class="table-val-border-col">',
	dol_print_date($dunning->dated, 'day'),
	'</td>',
	'</tr>',
	// Thirdparty
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('Customer'),
	'</td>',
	'<td class="table-val-border-col">',
	$company->getNomUrl(1, 'customer'),
	'</td>',
	'</tr>',
	// Amount
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('Amount'),
	'</td>',
	'<td class="table-val-border-col right">',
	price($dunning->amount, 0, $langs, 1, -1, -1, $conf->currency),
	'</td>',
	'</tr>',
	// Rest
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('Rest'),
	'</td>',
	'<td class="table-val-border-col right">',
	'<b>',
	price($dunning->getRest(), 0, $langs, 1, -1, -1, $conf->currency),
	'</b>',
	'</td>',
	'</tr>',
	'</table>';

// Dunning invoices list
$list = $dunning->getInvoices();
echo '<form name="dunning" action="', $_SERVER["PHP_SELF"], '" method="post">';
echo '<table class="noborder noshadow">',
	'<tr class="liste_titre">',
	'<th>',
	$langs->trans('Ref'),
	'</th>',
	'<th class="center">',
	$langs->trans('Date'),
	'</th>',
	'<th class="center">',
	$langs->trans('Late'), ' (', $langs->trans('days'), ')',
	'</th>',
	'<th class="right">',
	$langs->trans('Amount'),
	'</th>',
	'<th class="right">',
	$langs->trans('Rest'),
	'</th>',
	'<th class="left">',
	'</th>',
	'</tr>';

$var = true;
foreach ($list as $invoice) {
	
	$var = ! $var;
	echo '<tr '
		,$bc[$var]
		,'>',
		'<td>',
		$invoice->getNomUrl(1),
		'</td>',
		'<td class="center">',
		dol_print_date($invoice->date, 'day'),
		'</td>',
		'<td class="center">',
		num_between_day($invoice->date_lim_reglement, dol_now()),
		'</td>',
		'<td class="right">',
		price($invoice->total_ttc),
		'</td>',
		'<td class="right">',
		price(getRest($invoice), 1, $langs, 1, 2, 2),
		'</td>',
		'<td class="right">',
		'<a href="'.$_SERVER['PHP_SELF'].'?id='.$dunning->id.'&amp;invoiceid='.$invoice->id.'&amp;action=remove_invoice">'.img_picto($langs->trans('DunningRemoveInvoice'), 'delete').'</a>',
		'</td>',
		'</tr>';
}
echo '</table>';
echo '</form>';


dol_fiche_end();

print '<div class="tabsAction">';
if ($user->rights->dunning->write) {
	print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$dunning->id.'&amp;action=delete">'.$langs->trans('Delete').'</a></div>';
} else {
	print '<div class="inline-block divButAction"><span class="butActionRefused" title="'.$langs->trans("NotEnoughPermissions").'">'.$langs->trans('Delete').'</span></div>';
}
print '</div>';

// Files management
$formfile = new FormFile($db);
$filename = dol_sanitizeFileName($dunning->ref);
$filedir = $conf->dunning->dir_output . '/' . $filename;
$sourceurl = $_SERVER['PHP_SELF'] . '?id=' . $dunning->id;

$genallowed = $user->rights->dunning->write;
$delallowed = $user->rights->dunning->write;
echo $formfile->showdocuments(
	'dunning',
	$filename,
	$filedir,
	$sourceurl,
	$genallowed,
	$delallowed,
	$dunning->model_pdf,
	1,
	0,
	0,
	28,
	0,
	'',
	'',
	'',
	$company->default_lang
);

if (is_file($filedir.'/'.$dunning->ref.'.pdf') && $action!='presend') {
	print '<div class="tabsAction">';
	print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$dunning->id.'&amp;action=presend&amp;mode=init">'.$langs->trans('SendByMail').'</a></div>';
	print '</div>';
}

// List of actions on element
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
$formactions=new FormActions($db);
$somethingshown=$formactions->showactions($dunning,'dunning',$dunning->fk_company);

/*
 * Send Mail
 */
if ($action=='presend') {
	
	/*
	 * Affiche formulaire mail
	*/
	
	// By default if $action=='presend'
	$titreform='SendReminderBillByMail';
	$topicmail= 'SendReminderDunningTopic';
	$bodymail='SendReminderDunningRef';
	
	$filename = dol_sanitizeFileName($dunning->ref);
	$filedir = $conf->dunning->dir_output . '/' . $filename;
	$file=$filedir.'/'.$dunning->ref.'.pdf';
	
	// Define output language
	$outputlangs = $langs;
	$newlang='';
	if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$company->default_lang;
	if (! empty($newlang))
	{
		$outputlangs = new Translate("",$conf);
		$outputlangs->setDefaultLang($newlang);
		$outputlangs->load('dunning@dunning');
	}
	
	print '<br>';
	print_titre($langs->trans($titreform));
	
	// Cree l'objet formulaire mail
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
	$formmail = new FormMail($db);
	$formmail->fromtype = 'user';
	$formmail->fromid   = $user->id;
	$formmail->fromname = $user->getFullName($outputlangs);
	$formmail->frommail = $user->email;
	$formmail->withfrom=1;
	$liste=array();
	
	foreach ($company->thirdparty_and_contact_email_array(1) as $key=>$value)	$liste[$key]=$value;
	$formmail->withtocc=$liste;
	$formmail->withtoccc=$conf->global->MAIN_EMAIL_USECCC;
	$formmail->withtopic=$mysoc->name.'-'.$outputlangs->transnoentities($topicmail);
	$formmail->withfile=2;
	$formmail->withbody=$outputlangs->transnoentities($bodymail);
	$formmail->withdeliveryreceipt=1;
	$formmail->withcancel=1;
	// Tableau des substitutions
	$formmail->substit['__DUNNINGREF__']=$dunning->ref;
	$formmail->substit['__SIGNATURE__']=$user->signature;
	$formmail->substit['__PERSONALIZED__']='';
	$formmail->substit['__CONTACTCIVNAME__']='';
	
	//Get the first contact of the first invoice
	$invoices = $dunning->getInvoices();
	if (count($invoices)>0) {
		$invoice= $invoices[0];
		$arrayidcontact=$invoice->getIdContact('external','BILLING');
		if (count($arrayidcontact) > 0)
		{
			$usecontact=true;
			$result=$invoice->fetch_contact($arrayidcontact[0]);
		}
		if (!empty($invoice->contact)) {
			$custcontact=$invoice->contact->getFullName($outputlangs,1);
		}
	}
	
	if(!empty($custcontact) && !empty($invoice->contact->email)) {
		$sendto = $invoice->contact->getFullName($langs) . ' <' . $invoice->contact->email . '>';
	}
	

	//Determine for akteos the Requester contact of the session/invoice
	if (($company->typent_code!='TE_OPCA') && ($company->typent_code!='TE_PAY')) {
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
	

	if (empty($sendto)) {
		$formmail->withto=$user->email;
	}else {
		$formmail->withto=$sendto;
	}
	
	
	// Tableau des parametres complementaires du post
	$formmail->param['action']='send';
	$formmail->param['id']=$dunning->id;
	$formmail->param['returnurl']=$_SERVER["PHP_SELF"].'?id='.$dunning->id;
	
	// Init list of files
	if (GETPOST("mode")=='init')
	{
		$formmail->clear_attached_files();
		$formmail->add_attached_files($file,basename($file),dol_mimetype($file));
	}
	
	$formmail->show_form();
	
	print '<br>';
	
}

// Page end
llxFooter();
$db->close();
