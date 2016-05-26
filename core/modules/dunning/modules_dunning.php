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
 *    \file       core/modules/dunning/modules_dunning.php
 *    \ingroup    dunning
 *    \brief      PDF generation master class
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commondocgenerator.class.php';

/**
 *    Dunning document generator
 */
abstract class ModeleDunning extends CommonDocGenerator
{
	/**
	 *  Return list of active generation modules
	 *
	 * @param   DoliDB $db Database handler
	 * @param   int $maxfilenamelength Max length of value to show
	 * @return  array                        List of templates
	 */
	static function liste_modeles($db, $maxfilenamelength = 0)
	{
		global $conf;

		$type = 'dunning';
		$liste = array();

		include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
		$liste = getListOfModels($db, $type, $maxfilenamelength);

		return $liste;
	}
}

/**
 *    Dunning numbering
 */
abstract class ModeleNumRefDunning
{
	// FIXME: Implement
	public $error = '';

	/**
	 * Return if a module can be used or not
	 *
	 * @return    boolean     true if module can be used
	 */
	function isEnabled()
	{
		return true;
	}

	/**
	 * Return the numbering module's description text
	 *
	 * @return    string      Description
	 */
	function info()
	{
		global $langs;
		$langs->load("dunning@dunning");
		return $langs->trans("NoDescription");
	}

	/**
	 * Returns a numbering sample
	 *
	 * @return    string      Sample
	 */
	function getExample()
	{
		global $langs;
		$langs->load("dunning@dunning");
		return $langs->trans("NoExample");
	}

	/**
	 * Tests if already used numbers wouldn't cause conflicts
	 * preventing the module from working
	 *
	 * @return    boolean     false if conflict, true if ok
	 */
	function canBeActivated()
	{
		return true;
	}

	/**
	 * Returns next available value
	 *
	 * @param   Societe $company Company object
	 * @param   Dunning $dunning Dunning object
	 * @return  string                Value
	 */
	function getNextValue($company, $dunning)
	{
		global $langs;
		return $langs->trans("NotAvailable");
	}

	/**
	 * Returns numbering module version
	 *
	 * @return    string      Version
	 */
	function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') {
			return $langs->trans("VersionDevelopment");
		}
		if ($this->version == 'experimental') {
			return $langs->trans("VersionExperimental");
		}
		if ($this->version == 'dolibarr') {
			return DOL_VERSION;
		}
		return $langs->trans("NotAvailable");
	}
}

/**
 *  Create a document on disk according to template
 *
 * @param   DoliDB $db Database handler
 * @param   Dunning $object Dunning Object
 * @param   string $modele Force template to use ('' to not force)
 * @param   Translate $outputlangs objet lang a utiliser pour traduction
 * @param   int $hidedetails Hide details of lines
 * @param   int $hidedesc Hide description
 * @param   int $hideref Hide ref
 * @return  int                            <0 if KO, >0 if OK
 */
function dunning_pdf_create($db, $object, $modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
{
	global $conf, $user, $langs;

	$langs->load("dunning@dunning");

	$error = 0;

	// Increase limit for PDF build
	$err = error_reporting();
	error_reporting(0);
	@set_time_limit(120);
	error_reporting($err);

	$srctemplatepath = '';

	// Selects document model to use
	if (!dol_strlen($modele)) {
		if (!empty($conf->global->DUNNING_ADDON_PDF)) {
			$modele = $conf->global->DUNNING_ADDON_PDF;
		} else {
			$modele = 'shrimp';
		}
	}

	// If selected model is a template filename (then $modele="modelname:filename")
	$tmp = explode(':', $modele, 2);
	if (!empty($tmp[1])) {
		$modele = $tmp[0];
		$srctemplatepath = $tmp[1];
	}

	// Search template files
	$file = '';
	$classname = '';
	$filefound = 0;
	$dirmodels = array('/');
	if (is_array($conf->modules_parts['models'])) {
		$dirmodels = array_merge($dirmodels, $conf->modules_parts['models']);
	}
	foreach ($dirmodels as $reldir) {
		foreach (array('doc', 'pdf') as $prefix) {
			$file = $prefix . "_" . $modele . ".modules.php";

			// We check the module's path
			$file = dol_buildpath($reldir . "core/modules/dunning/doc/" . $file, 0);
			if (file_exists($file)) {
				$filefound = 1;
				$classname = $prefix . '_' . $modele;
				break;
			}
		}
		if ($filefound) {
			break;
		}
	}

	// Load the model
	if ($filefound) {
		require_once $file;

		$obj = new $classname($db);

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output = $outputlangs->charset_output;
		if ($obj->write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) > 0) {
			$outputlangs->charset_output = $sav_charset_output;

			// We delete old preview
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			dol_delete_preview($object);

			// Success in building document. We build meta file.
			dol_meta_create($object);

			// Triggers call
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($db);
			$result = $interface->run_triggers('DUNNNING_BUILDDOC', $object, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$errors = $interface->errors;
			}
			// End triggers call

			return 1;
		} else {
			$outputlangs->charset_output = $sav_charset_output;
			dol_print_error($db, "dunning_pdf_create Error: " . $obj->error);
			return -1;
		}

	} else {
		dol_print_error('', $langs->trans("Error") . " " . $langs->trans("ErrorFileDoesNotExists", $file));
		return -1;
	}
}
