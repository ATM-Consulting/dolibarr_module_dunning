<?php
/* Copyright (C) 2013 Florian Henry  <florian.henry@open-concept.pro>
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
 * \file /agefodd/scripts/createtaskadmin.php
 * \brief Generate script
 */
if (! defined('NOTOKENRENEWAL'))
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
if (! defined('NOREQUIREMENU'))
	define('NOREQUIREMENU', '1');
if (! defined('NOREQUIREHTML'))
	define('NOREQUIREHTML', '1');
if (! defined('NOREQUIREAJAX'))
	define('NOREQUIREAJAX', '1');
if (! defined ( 'NOLOGIN' ))
	define ( 'NOLOGIN', '1' );

$res = @include ("../../main.inc.php"); // For root directory
if (! $res)
	$res = @include ("../../../main.inc.php"); // For "custom" directory
if (! $res)
	die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once '../class/dunning.class.php';
require_once '../class/dunninginvoice.class.php';
require_once '../lib/dunning.lib.php';
require_once '../core/modules/dunning/modules_dunning.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once (DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php");

$userlogin = GETPOST('login');
$key = GETPOST('key', 'alpha');

// Security test
if ($key != $conf->global->WEBSERVICES_KEY) {
	print - 1;
	exit();
}

$user = new User($db);
$result = $user->fetch('', $userlogin);
if ($result < 0) {
	print 'user unknow';
	print - 1;
	exit();
}
$user->getrights();

$currentwarningdelay=$conf->facture->client->warning_delay;
$res = dolibarr_set_const ( $db, 'MAIN_DELAY_CUSTOMER_BILLS_UNPAYED', '50', 'chaine', 0, '', $conf->entity );
$conf->facture->client->warning_delay=50*24*60*60;


$dunning = new Dunning($db);
$company = new Societe($db);

$result = $dunning->fetch_thirdparty_with_unpaiyed_invoice($user, 1, 1);
if ($result < 0) {
	print 'ERROR' . $dunning->error;
	print - 1;
	exit();
}

$now = dol_now();
//var_dump($dunning);
foreach ( $dunning->lines as $line ) {
	
	$company->fetch(key($line));
	
	// Build array with past month
	$array_month = array ();
	for($i = 1; $i <= dol_print_date($now, '%m'); $i ++) {
		$array_month [$i] = $i;
	}
	
	$search_month = implode(',', $array_month);
	$search_year = dol_print_date($now, '%Y');
	//print '$search_month='.$search_month;
	//print '$$search_year='.$search_year;
	$list = getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year, 1, 1, true);
	
	if (dol_print_date($now, '%m')==1) {
		$array_month [10] = 10;
		$array_month [11] = 11;
		$array_month [12] = 12;
		$search_month = implode(',', $array_month);
		$search_year=dol_print_date($now, '%Y')-1;
		
		//print '$search_month='.$search_month;
		//print '$$search_year='.$search_year;
		
		$list = array_merge($list,getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year, 1, 1, true));
	}
	if (dol_print_date($now, '%m')==2) {
		$array_month [11] = 11;
		$array_month [12] = 12;
		$search_month = implode(',', $array_month);
		$search_year=dol_print_date($now, '%Y')-1;
	
		//print '$search_month='.$search_month;
		//print '$$search_year='.$search_year;
	
		$list = array_merge($list,getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year, 1, 1, true));
	}
	if (dol_print_date($now, '%m')==3) {
		$array_month [12] = 12;
		$search_month = implode(',', $array_month);
		$search_year=dol_print_date($now, '%Y')-1;
	
		//print '$search_month='.$search_month;
		//print '$$search_year='.$search_year;
	
		$list = array_merge($list,getListOfLateUnpaidInvoices($db, $company, $search_month, $search_year, 1, 1, true));
	}
	
	if (is_array($list) && count($list) > 0) {
		
		$invoices = array ();
		
		foreach ( $list as $invoices_object ) {
			
			$addtoautodunning = true;
			
			// Do not include invoice from manual dunning already send
			$dunningstatic = new Dunning($db);
			$listduning = $dunningstatic->fetch_by_invoice($invoices_object->id, '');
			if ($listduning < 0) {
				print 'ERROR $dunningstatic->fetch_by_invoice: ' . $dunningstatic->error;
			}
			//var_dump($invoices_object);
			if (is_array($listduning) && count($listduning) > 0) {
				$datelastsend = $listduning [0]->getLastActionEmailSend('daytextshort');
				
				//print '$datelastsend: ' . $datelastsend. ' $listduning [0]->datep'.$listduning [0]->datep;
				
				if (!empty($datelastsend) || !empty($listduning [0]->datep)) {
					$addtoautodunning = false;
				}
			}
			
			if ($addtoautodunning) {
				$invoices [] = $invoices_object->id;
			}
			
			//$invoices [] = $invoices_object->id;
		}
		
		if (count($invoices) > 0) {
			$newdunning = new Dunning($db);
			$newdunning->mode_creation = 'auto';
			$newdunning->model_pdf = $conf->global->DUNNING_ADDON_PDF;
			
			$obj = empty($conf->global->DUNNING_ADDON) ? 'mod_dunning_simple' : $conf->global->DUNNING_ADDON;
			$path_rel = dol_buildpath('/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php');
			if (! empty($conf->global->DUNNING_ADDON) && is_readable($path_rel)) {
				dol_include_once('/dunning/core/modules/dunning/' . $conf->global->DUNNING_ADDON . '.php');
				$modDunning = new $obj();
				$defaultref = $modDunning->getNextValue($company, $newdunning);
			}
			
			$newdunning->ref = $defaultref;
			
			$result = $newdunning->create($user, $company, $invoices);
			if ($result < O) {
				print 'ERROR $dunning->create: ' . $newdunning->error;
				$error ++;
			}
			
			// Create the PDF on dunning creation
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
				if (empty($company->default_lang)) {
					$newlang = 'fr_FR';
				} else {
					$newlang = $company->default_lang;
				}
			}
			if (! empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
				$outputlangs->load("dunning@dunning");
			}
			$result = dunning_pdf_create($db, $newdunning, $newdunning->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
			if ($result <= 0) {
				$error ++;
				print 'ERROR $unning_pdf_create: ' . $newdunning->error;
				exit();
			}
		
			$dunningbymail = new Dunning($db);
			$dunningbymail->fetch($newdunning->id);
			$companymail = new Societe($db);
			$companymail->fetch($dunningbymail->fk_company);
		
			// Define output language
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
				if (empty($companymail->default_lang)) {
					$newlang = 'fr_FR';
				} else {
					$newlang = $companymail->default_lang;
				}
			}
			if (! empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
				$outputlangs->load("dunning@dunning");
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
			$sendto='';
			if (empty($invoice->contact->email)) {
				print 'ERROR :'.$langs->trans('CannotSendMailDunningInvoiceContact',$dunningbymail->ref);
			}else {
				$sendto = $invoice->contact->getFullName($outputlangs) . ' <' . $invoice->contact->email . '>';
			}
		
			if (empty($user->email)) {
				print 'ERROR :'.$langs->trans('CannotSendMailDunningEmailFrom',$dunningbymail->ref);
				$error ++;
			}
		
			if (! $error) {
				$from = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
				$replyto = $user->getFullName($outputlangs) . ' <' . $user->email . '>';
				$message = str_replace("__SIGNATURE__",$user->signature,$outputlangs->transnoentities('SendReminderDunningRef'));
				$sendtocc = $from;
				$email_to_send=array();
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
						//$result = 1;
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
								print 'OK :'.$mesg;
								$action = '';
							}
						} else {
							$langs->load("other");
							if ($mailfile->error) {
								$mesg = $langs->trans('ErrorFailedToSendMail', $from, $sendto);
								
								print 'ERROR :'.$mailfile->error."\n".$mesg;
								
							} else {
								print 'ERROR :'.'No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';

							}
						}
					}
				}
			}
		}
	}
}
$res = dolibarr_set_const ( $db, 'MAIN_DELAY_CUSTOMER_BILLS_UNPAYED', $currentwarningdelay/24/60/60, 'chaine', 0, '', $conf->entity );
$conf->facture->client->warning_delay=$currentwarningdelay*24*60*60;
