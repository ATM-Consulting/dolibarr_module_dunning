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
 * 	\file		admin/admin_dunning.php
 * 	\ingroup	dunning
 * 	\brief		This file is the dunning module's setup page
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/dunning.lib.php';
require_once '../class/dunning.class.php';

// Translations
$langs->load("dunning@dunning");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
$value = GETPOST('value','alpha');
$scandir = GETPOST('scandir','alpha');
$type = 'dunning';
$setuplangs = GETPOST('setuplangs');
 if (empty($setuplangs)) {
 	$setuplangs = $langs->defaultlang;
 }


/*
 * Actions
 */
switch ($action) {
	case 'specimen':
		$modele = GETPOST('module', 'alpha');

		$facture = new Facture($db);
		$facture->initAsSpecimen();

		// FIXME: factor upstream
		// Search template files
		$file = '';
		$classname = '';
		$filefound = 0;
		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
		foreach ($dirmodels as $reldir) {
			$file = dol_buildpath($reldir . "core/modules/dunning/doc/pdf_" . $modele . ".modules.php", 0);
			if (file_exists($file)) {
				$filefound = 1;
				$classname = "pdf_" . $modele;
				break;
			}
		}

		if ($filefound) {
			require_once $file;

			$module = new $classname($db);

			if ($module->write_file($facture, $langs) > 0) {
				header("Location: " . DOL_URL_ROOT . "/document.php?modulepart=dunning&file=SPECIMEN.pdf");
				return;
			} else {
				setEventMessage($module->error, 'errors');
				dol_syslog($module->error, LOG_ERR);
			}
		} else {
			setEventMessage($langs->trans("ErrorModuleNotFound"), 'errors');
			dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
		}
		break;
	case 'set':
		addDocumentModel($value, $type, $label, $scandir);
		break;
	case 'del':
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			if ($conf->global->DUNNING_ADDON_PDF == "$value") {
				dolibarr_del_const($db, 'DUNNING_ADDON_PDF', $conf->entity);
			}
		}
		break;
	case 'setdoc':
		if (dolibarr_set_const($db, "DUNNING_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity)) {
			// La constante qui a ete lue en avant du nouveau set
			// on passe donc par une variable pour avoir un affichage coherent
			$conf->global->DUNNING_ADDON_PDF = $value;
		}
		// Activate model
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			addDocumentModel($value, $type, $label, $scandir);
		}
		break;
	case 'set_DUNNING_TEXT_BEFORE':
		
		if (! empty($conf->global->MAIN_MULTILANGS)) {
			$varname = "DUNNING_TEXT_BEFORE".$setuplangs;
		} else {
			$varname = "DUNNING_TEXT_BEFORE";
		}
		
		$res = dolibarr_set_const(
			$db,
			$varname,
			GETPOST('DUNNING_TEXT_BEFORE'),
			'chaine',
			0,
			'',
			$conf->entity
		);
		printEventMessage($res);
		break;
	case 'set_DUNNING_TEXT_AFTER':
		
		if (! empty($conf->global->MAIN_MULTILANGS)) {
			$varname = "DUNNING_TEXT_AFTER".$setuplangs;
		} else {
			$varname = "DUNNING_TEXT_AFTER";
		}
		
		$res = dolibarr_set_const(
			$db,
			$varname,
			GETPOST('DUNNING_TEXT_AFTER'),
			'chaine',
			0,
			'',
			$conf->entity
		);
		printEventMessage($res);
		break;
		
	case 'updateMaskType':
		$masktype = GETPOST ( 'value' );
	
		if ($masktype)
			$res = dolibarr_set_const ( $db, 'DUNNING_ADDON', $masktype, 'chaine', 0, '', $conf->entity );
	
		printEventMessage($res);
		break;
		
	case 'updateMask':
		$mask = GETPOST ( 'maskdunning' );
	
		$res = dolibarr_set_const ( $db, 'DUNNING_UNIVERSAL_MASK', $mask, 'chaine', 0, '', $conf->entity );
	
		printEventMessage($res);
		break;
}

function printEventMessage($res)
{
	global $langs;

	if ($res > 0) {
		setEventMessage($langs->trans("SetupSaved"));
	} else {
		setEventMessage($langs->trans("Error"), 'errors');
	}
}

/*
 * View
 */
$dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
$form = new Form($db);

$page_name = "DunningSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = dunningAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module105301Name"),
    0,
    "dunning@dunning"
);

// Setup page
print_titre($langs->trans("DunningPDFModels"));

// Load array def with activated templates
// FIXME: should be factored upstream!
$def = array();
$sql = "SELECT nom";
$sql .= " FROM " . MAIN_DB_PREFIX . "document_model";
$sql .= " WHERE type = '" . $type . "'";
$sql .= " AND entity = " . $conf->entity;
$resql = $db->query($sql);
if ($resql) {
	$i = 0;
	$num_rows = $db->num_rows($resql);
	while ($i < $num_rows) {
		$array = $db->fetch_array($resql);
		array_push($def, $array[0]);
		$i++;
	}
} else {
	dol_print_error($db);
}

echo '<table class="noborder allwidth">',
	'<tr class="liste_titre">',
	'<td>', $langs->trans("Name"), '</td>',
	'<td>', $langs->trans("Description"), '</td>',
	'<td class="center" style="width: 60px">', $langs->trans("Status"), '</td>',
	'<td class="center" style="width: 60px">', $langs->trans("Default"), '</td>',
	'<td class="center" style="width: 32px">', $langs->trans("ShortInfo"), '</td>',
	'<td class="center" style="width: 32px">', $langs->trans("Preview"), '</td>',
	"</tr>\n";

clearstatcache();

// FIXME: should be factored upstream!
$var = true;
foreach ($dirmodels as $reldir) {
	foreach (array('', '/doc') as $valdir) {
		$dir = dol_buildpath($reldir . "core/modules/dunning" . $valdir);

		if (is_dir($dir)) {
			$handle = opendir($dir);
			if (is_resource($handle)) {
				while (($file = readdir($handle)) !== false) {
					$filelist[] = $file;
				}
				closedir($handle);
				arsort($filelist);

				foreach ($filelist as $file) {
					if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
						if (file_exists($dir . '/' . $file)) {
							$name = substr($file, 4, dol_strlen($file) - 16);
							$classname = substr($file, 0, dol_strlen($file) - 12);
							require_once $dir . '/' . $file;
							$module = new $classname($db);

							$modulequalified = 1;
							if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) {
								$modulequalified = 0;
							}
							if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) {
								$modulequalified = 0;
							}

							if ($modulequalified) {
								$var = !$var;
								echo '<tr ', $bc[$var],'>',
									'<td style="width: 100px">',
									(empty($module->name) ? $name : $module->name),
									'</td>',
									"<td>\n";
								if (method_exists($module, 'info')) {
									echo $module->info($langs);
								} else {
									echo $module->description;
								}
								echo '</td>';

								// Active
								if (in_array($name, $def)) {
									echo '<td class="center">', "\n",
										'<a href="', $_SERVER["PHP_SELF"], '?action=del&value=', $name, '">',
										img_picto($langs->trans("Enabled"), 'switch_on'),
										'</a>',
										'</td>';
								} else {
									echo '<td class="center">', "\n",
										'<a href="', $_SERVER["PHP_SELF"],
										'?action=set&value=', $name, '&scandir=', $module->scandir,
										'&label=', urlencode(
											$module->name
										),
										'">',
										img_picto($langs->trans("Disabled"), 'switch_off'),
										'</a>',
										"</td>";
								}

								// Default
								echo '<td class="center">';
								
								if ($conf->global->DUNNING_ADDON_PDF == "$name") {
									echo img_picto($langs->trans("Default"), 'on');
								} else {
									echo '<a href="', $_SERVER["PHP_SELF"],
										'?action=setdoc&value=', $name, '&scandir=', $module->scandir,
										'&label=', urlencode(
											$module->name
										),
										'" alt="', $langs->trans("Default"), '">',
										img_picto(
											$langs->trans("Disabled"),
											'off'
										),
										'</a>';
								}
								echo '</td>';

								// Infos
								$htmltooltip = '' . $langs->trans("Name") . ': ' . $module->name;
								$htmltooltip .= '<br>' . $langs->trans(
										"Type"
									) . ': ' . ($module->type ? $module->type : $langs->trans("Unknown"));
								$htmltooltip .= '<br><br><u>' . $langs->trans("FeaturesSupported") . ':</u>';
								$htmltooltip .= '<br>' . $langs->trans("Logo") . ': ' . yn($module->option_logo, 1, 1);
								$htmltooltip .= '<br>' . $langs->trans("MultiLanguage") . ': ' . yn(
										$module->option_multilang,
										1,
										1
									);

								// FIXME: CSS center image
								echo '<td class="center">',
									$form->textwithpicto('', $htmltooltip, 1, 0),
									'</td>';

								// Preview
								echo '<td class="center">';
								if ($module->type == 'pdf') {
									echo '<a href="', $_SERVER["PHP_SELF"], '?action=specimen&module=', $name, '">',
										img_object($langs->trans("Preview"), 'bill'),
										'</a>';
								} else {
									echo img_object($langs->trans("PreviewNotAvailable"), 'generic');
								}
								echo'</td>',
									"</tr>\n";
							}
						}
					}
				}
			}
		}
	}
}
echo '</table>';



// dunning numbering module

echo '<br>';
print_titre ( $langs->trans ( "DunningNumberingRules" ) );
echo '<table class="noborder" width="100%">';
echo '<tr class="liste_titre">';
echo '<td width="100px">' . $langs->trans ( "Name" ) . '</td>';
echo '<td>' . $langs->trans ( "Description" ) . '</td>';
echo '<td>' . $langs->trans ( "Example" ) . '</td>';
echo '<td align="center" width="60px">' . $langs->trans ( "Activated" ) . '</td>';
echo '<td align="center" width="80px">' . $langs->trans ( "Infos" ) . '</td>';
echo "</tr>\n";

clearstatcache ();

$dirmodels = array_merge ( array (
	'/'
) );

foreach ( $dirmodels as $reldir ) {

	$dir = dol_buildpath ( "/dunning/core/modules/dunning/" );

	if (is_dir ( $dir )) {
		$handle = opendir ( $dir );
		if (is_resource ( $handle )) {
			$var = true;
				
			while ( ($file = readdir ( $handle )) !== false ) {
				if (preg_match ( '/^(mod_.*)\.php$/i', $file, $reg )) {
					$file = $reg [1];
					$classname = substr ( $file, 4 );
						
					require_once ($dir . $file . ".php");
						
					$module = new $file ();
						
					// Show modules according to features level
					if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2)
						continue;
					if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1)
						continue;
						
					if ($module->isEnabled ()) {
						$var = ! $var;
						print '<tr ' . $bc [$var] . '><td>' . $module->nom . "</td><td>\n";
						print $module->info ();
						print '</td>';

						// Show example of numbering module
						print '<td nowrap="nowrap">';
						$tmp = $module->getExample ();
						if (preg_match ( '/^Error/', $tmp )) {
							$langs->load ( "errors" );
							print '<div class="error">' . $langs->trans ( $tmp ) . '</div>';
						} elseif ($tmp == 'NotConfigured')
						print $langs->trans ( $tmp );
						else
							print $tmp;
						print '</td>' . "\n";

						print '<td align="center">';
						if ($conf->global->DUNNING_ADDON == 'mod_' . $classname) {
							print img_picto ( $langs->trans ( "Activated" ), 'switch_on' );
						} else {
							print '<a href="' . $_SERVER ["PHP_SELF"] . '?action=updateMaskType&amp;value=mod_' . $classname . '" alt="' . $langs->trans ( "Default" ) . '">' . img_picto ( $langs->trans ( "Disabled" ), 'switch_off' ) . '</a>';
						}
						print '</td>';

						$dunning = new Dunning ( $db );
						$dunning->initAsSpecimen ();

						// Info
						$htmltooltip = '';
						$htmltooltip .= '' . $langs->trans ( "Version" ) . ': <b>' . $module->getVersion () . '</b><br>';
						$nextval = $module->getNextValue ( $mysoc, $dunning );
						if ("$nextval" != $langs->trans ( "NotAvailable" )) 						// Keep " on nextval
						{
							$htmltooltip .= '' . $langs->trans ( "NextValue" ) . ': ';
							if ($nextval) {
								$htmltooltip .= $nextval . '<br>';
							} else {
								$htmltooltip .= $langs->trans ( $module->error ) . '<br>';
							}
						}

						print '<td align="center">';
						print $form->textwithpicto ( '', $htmltooltip, 1, 0 );
						print '</td>';

						print '</tr>';
					}
				}
			}
			closedir ( $handle );
		}
	}
}

print '</table><br>';


//WYSIWYG Editor
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
print_titre($langs->trans("DunningPDFTexts"));

if (! empty($conf->global->MAIN_MULTILANGS)) {
	
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
	$formadmin=new FormAdmin($db);
	
	echo '<form action="' . $_SERVER["PHP_SELF"]. '" method="post">'
		,$formadmin->select_language($setuplangs,'setuplangs',0)
		,'<input type="submit" value="',
		$langs->trans('Select')
		,'">'
		,'</form>';
	
	$currenttext_before= (isset($conf->global->{'DUNNING_TEXT_BEFORE'.$setuplangs}) ? $conf->global->{'DUNNING_TEXT_BEFORE'.$setuplangs} : '');
	$currenttext_after= (isset($conf->global->{'DUNNING_TEXT_AFTER'.$setuplangs}) ? $conf->global->{'DUNNING_TEXT_AFTER'.$setuplangs} : '');
}




echo '<form action="' . $_SERVER["PHP_SELF"]. '" method="post">',
	'<input type="hidden" name="setuplangs" value="',$setuplangs,'">',
	'<table class="noborder allwidth">',
	'<tr class="liste_titre">',
	'<th>',
	$langs->trans("Parameter"),
	'</th>',
	'<th class="center">',
	$langs->trans("Text"),
	'</th>',
	'<th>',
	'&nbsp;',
	'</th>',
	'</tr>',
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('BeforeInvoiceList'),
	'</td>',
	'<td class="table-val-border-col">';
$doleditor = new DolEditor(
	'DUNNING_TEXT_BEFORE',
	$currenttext_before
);
$doleditor->Create();
echo //getCustomCKEditorToolbarJS($doleditor->htmlname),
	'</td>',
	'<td class="center">',
	'<button type="submit" class="button" name="action" value="set_DUNNING_TEXT_BEFORE">',
	 $langs->trans("Modify"),
	'</button>',
	'</td>',
	'</tr>',
	'<tr>',
	'<td class="table-key-border-col">',
	$langs->trans('AfterInvoiceList'),
	'</td>',
	'<td class="table-val-border-col">';
$doleditor = new DolEditor(
	'DUNNING_TEXT_AFTER',
	$currenttext_after
);
$doleditor->Create();
echo //getCustomCKEditorToolbarJS($doleditor->htmlname),
	'</td>',
	'<td class="center">',
	'<button type="submit" class="button" name="action" value="set_DUNNING_TEXT_AFTER">',
	$langs->trans("Modify"),
	'</button>',
	'</td>',
	'</tr>',
	'</table>',
	'</form>';

// Page end
dol_fiche_end();
llxFooter();
$db->close();

/**
 * Javascript to replace CKEditor toolbar with an optimized toolbar featuring only TCPDF::writeHTML() supported elements
 *
 * @param string $element_name The name of the html element supporting the CKEditor
 * @return string
 * @todo Factorize upstream
 */
function getCustomCKEditorToolbarJS($element_name) {
	return '<script type="text/javascript">' .
		"CKEDITOR.replace( '". $element_name . "',
		{
			toolbar:
			[
				{ name: 'est b', items : [ 'Source' ]},
				{ name: 'clipboard', items : [ 'Cut','Copy','Paste','PasteText','-','Undo','Redo' ] },
				{ name: 'editing', items : [ 'Find','Replace','-','SelectAll','-','SpellChecker', 'Scayt' ] },
				'/',
				{ name: 'basicstyles', items : [ 'Bold','Italic','Underline','Strike','Subscript','Superscript','-','RemoveFormat' ] },
				{ name: 'paragraph', items : [ 'NumberedList','BulletedList','-','Outdent','Indent','-','Blockquote',
				'-','JustifyLeft','JustifyCenter','JustifyRight' ] },
				{ name: 'links', items : [ 'Link','Unlink' ] },
				{ name: 'insert', items : [ 'Image','Table','HorizontalRule' ] }, /* FIXME: 'SpecialChar' needs UTF-8, 'PageBreak' would work if PDF was not badly designed */
				'/',
				{ name: 'styles', items : [ 'Styles','Format','FontSize' ] },
				{ name: 'colors', items : [ 'TextColor','BGColor' ] },
				{ name: 'tools', items : [ 'Maximize' ] }
			]
		});
	</script>";

}
