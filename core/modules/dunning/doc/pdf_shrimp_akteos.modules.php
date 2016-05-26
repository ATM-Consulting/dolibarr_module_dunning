<?php
/* Dunning management
 * Copyright (C) 2009-2010  Erick Bullier       <eb.dev@ebiconsulting.fr>
 * Copyright (C) 2012-2013  Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2014       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
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
 * or see http://www.gnu.org/
 */

/**
 *    \file       core/modules/dunning/doc/pdf_shrimp.modules.php
 *    \ingroup    dunning
 *    \brief      Class to generate dunning documents
 */
require_once dol_buildpath('/dunning/class/dunning.class.php');
require_once dol_buildpath('/dunning/core/modules/dunning/modules_dunning.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

/**
 *    Dunnning PDF document class
 */
class pdf_shrimp_akteos extends ModeleDunning
{
	/**
	 * The module's version
	 * @var string
	 */
	public $version = '0.0.1';

	/**
	 * Minimum PHP version for the module to work properly
	 * @var array
	 */
	public $phpmin = array(5, 2, 0);

	/**
	 * Type of document
	 * @var string
	 */
	public $type = 'pdf';

	/**
	 * The PDF library document
	 * @var TCPDF|FPDI
	 */
	protected $pdf;

	/**
	 * The document metrics unit
	 * @var string
	 */
	protected $unit;

	/**
	 * The document width
	 * @var float
	 */
	protected $width;

	/**
	 * The document height
	 * @var float
	 */
	protected $height;

	/**
	 * Issuer company
	 * @var Societe
	 */
	protected $issuer;

	/**
	 * Recipient company
	 * @var Societe
	 */
	protected $recipient;

	/**
	 * Footer color
	 * @var array
	 */
	protected $footer_color;

	/**
	 * Text color
	 * @var array
	 */
	protected $text_color;

	/**
	 * Head color
	 * @var array
	 */
	protected $head_color;

	/**
	 * Related dunning
	 * @var Dunning
	 */
	protected $dunning;

	/**
	 * Language object
	 * @var Translate
	 */
	protected $outputlangs;

	/**
	 * \brief   Constructor
	 *
	 * @param   DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		$langs->load("dunning@dunning");

		$this->db = $db;
		$this->name = "shrimp_akteos";
		$this->description = $langs->trans('PDFShrimpDescription');
		$this->outputlangs=$langs;

		// Options
		$this->option_logo = 1; // Affiche logo
		$this->option_multilang = 1; // Dispo en plusieurs langues
		$this->option_freetext = 1; // Support add of a personalised text

		// Paper
		$this->setFormat(pdf_getFormat());
		$this->orientation = 'P';

		// Margins
		// FIXME: should take units into account. mm assumed.
		$this->left_margin = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->right_margin = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->top_margin = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->bottom_margin = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

		// Colors
		$this->text_color = array(0, 0, 0); // Black
		$this->head_color = array(128, 128, 128); // Grey
		$this->footer_color = array(128, 128, 128); // Grey

		// Fonts
		$this->default_font_size = pdf_getPDFFontSize($this->outputlangs);

		// Pages
		$this->pagenb = 0;

		// PDF
		$this->pdf = pdf_getInstance($this->getFormat(), $this->unit, $this->orientation);
		$this->pdf->SetMargins($this->left_margin, $this->top_margin, $this->right_margin);
		$this->pdf->SetAutoPageBreak(false, $this->bottom_margin);
		$this->setXYTopLeftCorner();

		// Get source company
		$this->issuer = $mysoc;

		// Country code fixup
		// FIXME: Is this still needed ?
		if (!$this->issuer->country_code) {
			$this->issuer->country_code = substr(
				$langs->defaultlang,
				-2
			);
		}
	}

	/**
	 * Get the document format as a [width, height] array
	 *
	 * @return array
	 */
	private function getFormat()
	{
		return array($this->width, $this->height);
	}

	/**
	 * Set the document format
	 *
	 * @param array $format A ('width'=>w,'height'=>h,'unit'=>u) document format associative array
	 * @return void
	 */
	private function setFormat($format)
	{
		$this->width = $format['width'];
		$this->height = $format['height'];
		$this->unit = $format['unit'];
	}

	/**
	 * Set the PDF X cordinate relative to the left margin
	 *
	 * @param float $x_offset Positive X offset
	 * @return void
	 */
	private function setXLeftMargin($x_offset = 0.0)
	{
		$this->pdf->setX($this->left_margin + $x_offset);
	}

	/**
	 * Set the PDF X coordinate relative to the right margin
	 *
	 * @param float $x_offset Negative X offset
	 * @return void
	 */
	private function setXRightMargin($x_offset = 0.0)
	{
		$this->pdf->setX(-$this->right_margin - $x_offset);
	}

	/**
	 * Set the PDF Y coordinate relative to the top margin
	 *
	 * @param float $y_offset Positive X offset
	 * @return void
	 */
	private function setYTopMargin($y_offset = 0.0)
	{
		$this->pdf->setY($this->top_margin + $y_offset);
	}

	/**
	 * Set the PDF XY coordinates relative to the top left corner including margins
	 *
	 * @param float $x_offset Positive X offset
	 * @param float $y_offset Positive Y offset
	 * @return void
	 */
	private function setXYTopLeftCorner($x_offset = 0.0, $y_offset = 0.0)
	{
		$this->pdf->setXY($this->left_margin + $x_offset, $this->top_margin + $y_offset);
	}

	/**
	 * Set the PDF XY coordinates relative to the top right corner including margins
	 *
	 * @param float $x_offset Negative X offset
	 * @param float $y_offset Positive Y offset
	 * @return void
	 */
	private function setXYTopRightCorner($x_offset = 0.0, $y_offset = 0.0)
	{
		$this->pdf->setXY(-$this->right_margin - $x_offset, $this->top_margin + $y_offset);
	}

	/**
	 * Get the printable width
	 *
	 * @return float Printable width
	 */
	private function getPrintableWidth()
	{
		return $this->width - $this->left_margin - $this->right_margin;
	}

	/**
	 * Get the remaining width from current X position
	 *
	 * @return float Remaining width from current X position
	 */
	private function getRemainingWidth()
	{
		return $this->width - $this->pdf->getX() - $this->right_margin;
	}

	/**
	 * \brief   Write document file on disk
	 *
	 * @param Dunning $dunning Dunning object
	 * @param Translate $outputlangs Lang object for output language
	 * @return int          1=ok, 0=ko
	 */
	public function write_file($dunning, $outputlangs)
	{
		global $user, $langs, $conf;

		$this->dunning = $dunning;

		$this->outputlangs = $outputlangs;
		if (!is_object($this->outputlangs)) {
			$this->outputlangs = $langs;
		}
		
		$this->outputlangs->load("dunning@dunning");
		$this->outputlangs->load("bills");
		$this->outputlangs->load("companies");
		$this->outputlangs->load("dict");

		// Destinations
		// Definition of $dir and $file
		if ($this->dunning->specimen) {
			$dest_dir = $conf->dunning->dir_output;
			$dest_file = $dest_dir . "/SPECIMEN.pdf";
		} else {
			$objectref = dol_sanitizeFileName($this->dunning->ref);
			$dest_dir = $conf->dunning->dir_output . "/" . $objectref;
			$dest_file = $dest_dir . "/" . $objectref . ".pdf";
		}
	
		// Create target dir if need be
		if (!file_exists($dest_dir)) {
			if (dol_mkdir($dest_dir) < 0) {
				$this->error = $langs->trans("ErrorCanNotCreateDir", $dest_dir);
				return 0;
			}
		}

		if (file_exists($dest_dir)) {
			// We get rid of the predefined headers and footers
			// FIXME: we should extend these methods to provide our own customized header and footer rather than re-inventing the wheel
			if (class_exists('TCPDF')) {
				$this->pdf->setPrintHeader(false);
				$this->pdf->setPrintFooter(false);
			}

			$this->pdf->Open();

			$this->pdf->SetTitle(
				$this->outputlangs->convToOutputCharset(
					$this->outputlangs->transnoentities('Dunning') . " " . $this->dunning->ref
				)
			);
			$this->pdf->SetSubject($this->outputlangs->transnoentities($this->name));
			$this->pdf->SetCreator("Dolibarr " . DOL_VERSION);
			$this->pdf->SetAuthor($this->outputlangs->convToOutputCharset($user->fullname));
			$this->pdf->SetKeyWords(
				$this->outputlangs->convToOutputCharset(
					$this->dunning->ref
				) . " " . $this->outputlangs->transnoentities("Document")
			);
			if ($conf->global->MAIN_DISABLE_PDF_COMPRESSION) {
				$this->pdf->SetCompression(false);
			}

			// Company
			$this->recipient = new Societe($this->db);
			$this->recipient->fetch($this->dunning->fk_company);

			$this->printPage();
			
			$this->mergeInvoicePDF();

			$this->pdf->Close();
			$this->pdf->Output($dest_file, 'F');

			// Set file permissions
			if (!empty($conf->global->MAIN_UMASK)) {
				@chmod($dest_file, octdec($conf->global->MAIN_UMASK));
			}

			return 1; // No error
		} else {
			$this->error = $langs->trans("ErrorConstantNotDefined", "AGF_OUTPUTDIR");
			return 0;
		}
		$this->error = $langs->trans("ErrorUnknown");
		return 0; // Default error
	}

	/**
	 * \brief   Prints the page body
	 *
	 * @return void
	 */
	private function printPage()
	{
		global $conf;
		// Init fonts and colors
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->SetTextColorArray($this->text_color);

		$footer_height = $this->newPage();

		/*
		 * Page body
		 */

		if (! empty($conf->global->MAIN_MULTILANGS)) {
			$currenttext_before= $conf->global->{'DUNNING_TEXT_BEFORE'.$this->outputlangs->defaultlang};
			$currenttext_after= $conf->global->{'DUNNING_TEXT_AFTER'.$this->outputlangs->defaultlang};
		}else {
			$currenttext_before= $conf->global->DUNNING_TEXT_BEFORE;
			$currenttext_after= $conf->global->DUNNING_TEXT_AFTER;
		}
		
		// Letter text first part
		$this->setXLeftMargin();
		$this->pdf->setY($this->pdf->getY() + 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->writeHTMLCell(
			$this->width - $this->left_margin - $this->right_margin,
			0,
			$this->left_margin,
			$this->pdf->getY(),
			$currenttext_before,
			0,
			1
		);
		
		// Invoice list
		$this->setXLeftMargin();
		$this->pdf->setY($this->pdf->getY() + 2);
		$this->printTable($this->height - $footer_height);

		// Letter text second part
		$this->setXLeftMargin();
		$this->pdf->setY($this->pdf->getY() + 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->writeHTMLCell(
			$this->width - $this->left_margin - $this->right_margin,
			0,
			$this->left_margin,
			$this->pdf->getY(),
			$currenttext_after,
			0,
			1
		);

		// TODO: Concat invoices with "DUPLICATA" watermark
	}

	/**
	 * \brief      Show page header
	 *
	 * @return void
	 */
	private function printHeader()
	{
		$this->outputlangs->load("main");

		$this->setXYTopLeftCorner();

		$logo_height = $this->printLogo();
		// TODO: Implement
		$address_height = $this->printAddress();
		$doc_infos_height = $this->printDocInfos();

		$this->setXLeftMargin();
		$this->setYTopMargin(max($logo_height, $address_height, $doc_infos_height));

		pdf_pagehead($this->pdf, $this->outputlangs, $this->height);
	}

	/**
	 * \brief   Show page footer
	 *
	 * @return float Footer Y start
	 */
	private function printFooter()
	{
		global $conf,$langs,$mysoc;
		
		// Logo en haut à gauche
		$logo=$conf->mycompany->dir_output.'/logos/footer.jpg';
		
		if (is_readable($logo))
		{
			$heightLogo=pdf_getHeightForLogo($logo);
			$this->pdf->Image($logo,  $this->left_margin, $this->height-$heightLogo-20, 0, 0, '', '', '', false, 300, '', false, false, 0, false, false, true);	// width=0 (auto)
		}

		return $heightLogo;
	}

	/**
	 * Add company logo to the document
	 *
	 * @return float Height
	 */
	private function printLogo()
	{
		global $conf;

		// FIXME: doesn't look like good practice
		$logo = $conf->mycompany->dir_output . '/logos/' . $this->issuer->logo;
		if ($this->issuer->logo) {
			if (is_readable($logo)) {
				$logo_height = pdf_getHeightForLogo($logo);
				$this->pdf->Image($logo, $this->pdf->getX(), $this->pdf->getY(), 0, $logo_height); // width=0 (auto)
				return $logo_height;
			}
		}

		// Text fallback
		$text = $this->issuer->name;
		$this->pdf->MultiCell(0, 4, $this->outputlangs->convToOutputCharset($text), 0, 'L');
		return $this->pdf->getY();
	}

	/**
	 * Print the addresses blocks
	 *
	 * @return void
	 */
	private function printAddress()
	{
		// FIXME: refactor, this stinks

		global $conf;

		$issuer_address = pdf_build_address($this->outputlangs, $this->issuer);

		// Show sender
		$posy = 42;
		$posx = $this->left_margin;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) {
			$posx = $this->width - $this->right_margin - 80;
		}
		$hautcadre = 40;

		// Show sender frame
		$this->pdf->SetTextColor(0, 0, 0);
		$this->pdf->SetFont('', '', $this->default_font_size - 2);
		$this->pdf->SetXY($posx, $posy - 5);
		$this->pdf->MultiCell(66, 5, $this->outputlangs->transnoentities("BillFrom") . ":", 0, 'L');
		$this->pdf->SetXY($posx, $posy);
		$this->pdf->SetFillColor(230, 230, 230);
		$this->pdf->MultiCell(82, $hautcadre, "", 0, 'R', 1);
		$return = $this->pdf->getY();
		$this->pdf->SetTextColor(0, 0, 60);

		// Show sender name
		$this->pdf->SetXY($posx + 2, $posy + 3);
		$this->pdf->SetFont('', 'B', $this->default_font_size);
		$this->pdf->MultiCell(80, 4, $this->outputlangs->convToOutputCharset($this->issuer->name), 0, 'L');
		$posy = $this->pdf->getY();

		// Show sender information
		$this->pdf->SetXY($posx + 2, $posy);
		$this->pdf->SetFont('', '', $this->default_font_size - 1);
		$this->pdf->MultiCell(80, 4, $issuer_address, 0, 'L');

		// If BILLING contact is defined, we use it
		$usecontact = false;
		$arrayidcontact = $this->recipient->getIdContact('external', 'BILLING');
		if (count($arrayidcontact) > 0) {
			$usecontact = true;
			$result = $this->recipient->fetch_contact($arrayidcontact[0]);
		}

		// Recipient name
		if (!empty($usecontact)) {
			// On peut utiliser le nom de la societe du contact
			if (!empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$socname = $this->recipient->contact->socname;
			} else {
				$socname = $this->recipient->nom;
			}
			$carac_client_name = $this->outputlangs->convToOutputCharset($socname);
		} else {
			$carac_client_name = $this->outputlangs->convToOutputCharset($this->recipient->nom);
		}

		$carac_client = pdf_build_address(
			$this->outputlangs,
			$this->emetteur,
			$this->recipient,
			($usecontact ? $this->recipient->contact : ''),
			$usecontact,
			'target'
		);

		// Show recipient
		$widthrecbox = 100;
		if ($this->width < 210) {
			$widthrecbox = 84;
		} // To work with US executive format
		$posy = 42;
		$posx = $this->width - $this->right_margin - $widthrecbox;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) {
			$posx = $this->left_margin;
		}

		// Show recipient frame
		$this->pdf->SetTextColor(0, 0, 0);
		$this->pdf->SetFont('', '', $this->default_font_size - 2);
		$this->pdf->SetXY($posx + 2, $posy - 5);
		$this->pdf->MultiCell($widthrecbox, 5, $this->outputlangs->transnoentities("BillTo") . ":", 0, 'L');
		$this->pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

		
		//Get the first contact of the first invoice
		$invoices = $this->dunning->getInvoices();
		if (count($invoices)>0) {
			$invoice= $invoices[0];
			$arrayidcontact=$invoice->getIdContact('external','BILLING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$invoice->fetch_contact($arrayidcontact[0]);
			}
			// Show Special contact name
			if (is_array($invoice->array_options) && key_exists('options_conatct_cust',$invoice->array_options) && !empty($invoice->array_options['options_conatct_cust'])) {
		
			}elseif (!empty($invoice->contact)) {
		
				$carac_client_name=$this->outputlangs->convToOutputCharset($invoice->contact->socname);
		
			}
		}
		
		// Show recipient name
		$this->pdf->SetXY($posx + 2, $posy + 3);
		$this->pdf->SetFont('', 'B', $this->default_font_size);
		$this->pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');
		
		//Get the first contact of the first invoice
		$invoices = $this->dunning->getInvoices();
		if (count($invoices)>0) {
			$invoice= $invoices[0];
			$arrayidcontact=$invoice->getIdContact('external','BILLING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$invoice->fetch_contact($arrayidcontact[0]);
			}
			// Show Special contact name
			if (is_array($invoice->array_options) && key_exists('options_conatct_cust',$invoice->array_options) && !empty($invoice->array_options['options_conatct_cust'])) {
				$this->pdf->SetXY($posx+2,$posy+7);
				$this->pdf->SetFont('','', $default_font_size);
				$this->pdf->MultiCell($widthrecbox, 4, $invoice->array_options['options_conatct_cust'], 0, 'L');
			}elseif (!empty($invoice->contact)) {
				$this->pdf->SetXY($posx+2,$posy+7);
				$this->pdf->SetFont('','', $default_font_size);
				$this->pdf->MultiCell($widthrecbox, 4, $this->outputlangs->convToOutputCharset($invoice->contact->getFullName($this->outputlangs,1)), 0, 'L');	
				
				$invoice->contact->fetch_thirdparty();
				
				$carac_client = pdf_build_address(
						$this->outputlangs,
						$this->emetteur,
						$invoice->contact->thirdparty,
						'',
						false,
						'target'
				);
			}
		}

		// Show recipient information
		$this->pdf->SetFont('', '', $this->default_font_size - 1);
		$this->pdf->SetXY($posx + 2, $posy + 7 + (dol_nboflines_bis($carac_client_name, 50) * 4));
		$this->pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');

		return $return;
	}

	/**
	 * Print document informations
	 *
	 * @return float Height
	 */
	private function printDocInfos()
	{
		$start_y = $this->pdf->getY();

		// FIXME: this works with millimeters, may not work with other units
		$company_header_width = 100;

		// Document title
		$this->setXYTopRightCorner($company_header_width);
		$this->pdf->SetFont('', 'B', $this->default_font_size + 3);
		$this->pdf->SetTextColorArray($this->text_color);
		$title = $this->outputlangs->transnoentities("Dunning");
		$this->pdf->MultiCell($company_header_width, 4, $title, '', 'R');

		// Reference
		$this->setXRightMargin($company_header_width);
		$this->pdf->SetFont('', '', $this->default_font_size);
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			4,
			$this->outputlangs->transnoentities("Ref") . " : " . $this->outputlangs->convToOutputCharset(
				$this->dunning->ref
			),
			'',
			'R'
		);

		// Customer code
		$this->setXRightMargin($company_header_width);
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			3,
			$this->outputlangs->transnoentities("CustomerCode") . " : "  . $this->outputlangs->convToOutputCharset(
				$this->recipient->code_client
			),
			'',
			'R'
		);

		// Date
		$this->setXRightMargin($company_header_width);
		
		
		//Print date of sending if it is later than dunning date
		$daterelance = $this->dunning->dated;
		if (!empty($this->dunning->datep) && ($this->dunning->datep>$this->dunning->dated)) {
			$daterelance=$this->dunning->datep;
		}
			
		$this->pdf->SetTextColorArray($this->text_color);
		$this->pdf->MultiCell(
			100,
			3,
			$this->outputlangs->transnoentities("Date") . " : "  . dol_print_date(
				$daterelance,
				"day",
				false,
				$this->outputlangs
			),
			'',
			'R'
		);

		return $this->pdf->GetY() - $start_y;
	}

	/**
	 * Prints the table
	 *
	 * @param float $max_y_position Bottom limit for page breaking management
	 * @return void
	 */
	private function printTable($max_y_position)
	{
		$this->setXLeftMargin();
		$table_start = array($this->pdf->getX(), $this->pdf->getY());
		$this->printTableHeader($table_start);

		// Lines
		$invoices = $this->dunning->getInvoices();
		$this->printLines($invoices, $max_y_position, $table_start);
		// TODO: Print total
		$total_rest = 0;
		foreach($invoices as $invoice) {
			/* @var $invoice Facture */
			$total_rest += getRest($invoice);
		}

		$line_height = 4;
		$col_width = $this->getPrintableWidth() / 5;

		// Total text
		$this->setXRightMargin($col_width * 2);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->transnoentities("Total"),
			0,
			0,
			"L",
			1
		);

		// Total
		$this->setXRightMargin($col_width);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), 'B', $this->default_font_size);
		$this->pdf->Cell(
			$col_width,
			$line_height,
			$this->outputlangs->convToOutputCharset(
				price($total_rest, 0, $this->outputlangs, 1, 2, 2)
			),
			0,
			1,
			"R",
			1
		);


	}

	/**
	 * Prints the table header
	 *
	 * @param array $table_start The element starting coordinates (x, y)
	 * @return void
	 */
	private function printTableHeader($table_start) {
		// Header
		$table_header_height = 4;

		$col_width = $this->getPrintableWidth() / 5;

		// Ref
		$this->pdf->SetXY($table_start[0], $table_start[1]);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$name_label = $this->outputlangs->transnoentities('InvoiceReference');
		$this->pdf->Cell(
			$col_width,
			$table_header_height,
			$this->outputlangs->convToOutputCharset($name_label),
			1,
			0,
			"L",
			0
		);

		// Date
		$this->pdf->SetXY($this->pdf->getX(), $table_start[1]);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$date_label = $this->outputlangs->transnoentities('Date');
		$this->pdf->Cell(
			$col_width,
			$table_header_height,
			$this->outputlangs->convToOutputCharset($date_label),
			1,
			0,
			"C",
			0
		);

		// Late
		$this->pdf->SetXY($this->pdf->getX(), $table_start[1]);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$late_label = $this->outputlangs->transnoentities('Late') .
			' (' . $this->outputlangs->transnoentities('days') . ')';
		$this->pdf->Cell(
			$col_width,
			$table_header_height,
			$this->outputlangs->convToOutputCharset($late_label),
			1,
			0,
			"C",
			0
		);

		// Amount
		$this->pdf->SetXY($this->pdf->getX(), $table_start[1]);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$amount_label = $this->outputlangs->transnoentities('Montant');
		$this->pdf->Cell(
			$col_width,
			$table_header_height,
			$this->outputlangs->convToOutputCharset($amount_label),
			1,
			0,
			"R",
			0
		);

		// Rest
		$this->pdf->SetXY($this->pdf->getX(), $table_start[1]);
		$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
		$rest_label = $this->outputlangs->transnoentities('Rest');
		$this->pdf->Cell(
			$col_width,
			$table_header_height,
			$this->outputlangs->convToOutputCharset($rest_label),
			1,
			1,
			"R",
			0
		);
	}

	/**
	 * Print a new page with it's footer and header
	 *
	 * @return float Footer Y start coordinate
	 */
	private function newPage()
	{
		$this->pdf->AddPage();
		$this->pagenb++;
		$footer_height = $this->printFooter();
		$this->printHeader();

		return $footer_height;
	}

	/**
	 * Print the table lines
	 *
	 * @param array $invoices Invoices list
	 * @param float $max_y_position Maximum Y position for manual page break management
	 * @param array $table_start Table start coordinates (x, y)
	 * @return void
	 */
	private function printLines($invoices, $max_y_position, $table_start)
	{
		// Dimensions
		$line_height = 4;
		$col_width = $this->getPrintableWidth() / 5;

		$current_line = 0;
		foreach ($invoices as $line) {
			/* @var $line Facture */
			$current_line++;
			$table_content_start = $this->pdf->getY();

			// Ref
			$this->pdf->setXY($table_start[0], $table_content_start);
			$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
			$this->pdf->Cell(
				$col_width,
				$line_height,
				$this->outputlangs->convToOutputCharset($line->ref),
				1,
				0,
				"L",
				0
			);

			// Date
			$this->pdf->SetXY($this->pdf->getX(), $table_content_start);
			$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
			$this->pdf->Cell(
				$col_width,
				$line_height,
				$this->outputlangs->convToOutputCharset(
					dol_print_date($line->date, 'day')
				),
				1,
				0,
				"C",
				0
			);

			// Late
			$this->pdf->SetXY($this->pdf->getX(), $table_content_start);
			$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
			$this->pdf->Cell(
				$col_width,
				$line_height,
				$this->outputlangs->convToOutputCharset(
					num_between_day($line->date_lim_reglement, dol_now())
				),
				1,
				0,
				"C",
				0
			);

			// Amount
			$this->pdf->SetXY($this->pdf->getX(), $table_content_start);
			$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
			$this->pdf->Cell(
				$col_width,
				$line_height,
				$this->outputlangs->convToOutputCharset(
					price($line->total_ttc, 0, $this->outputlangs, 1, 2, 2)
				),
				1,
				0,
				"R",
				0
			);

			// Rest
			$this->pdf->SetXY($this->pdf->getX(), $table_content_start);
			$this->pdf->SetFont(pdf_getPDFFont($this->outputlangs), '', $this->default_font_size);
			$this->pdf->Cell(
				$col_width,
				$line_height,
				$this->outputlangs->convToOutputCharset(
					price(getRest($line), 0, $this->outputlangs, 1, 2, 2)
				),
				1,
				1,
				"R",
				0
			);

			// Page break management
			if ($this->pdf->GetY() > $max_y_position - $line_height) {
				$this->newPage();
				// Don't reprint header if we were displaying the last line before breaking
				if ($current_line !== count($invoices)) {
					$table_start = array($this->pdf->getX(), $this->pdf->getY() + 2);
					$this->printTableHeader($table_start);
				}
			}
		}
	}
	
	/**
	 * Add the Invoices PDF
	 *
	 * @return void
	 */
	private function mergeInvoicePDF() {
		
		global $conf;
		//Get invoice list
		$invoices = $this->dunning->getInvoices();
		if (count ( $invoices ) > 0) {
			foreach ( $invoices as $invoice ) {
				//Get invoice PDF file name
				$objectref = dol_sanitizeFileName($invoice->ref);
				$dir = $conf->facture->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
				dol_syslog ( get_class ( $this ) . ':: $file=' . $file, LOG_DEBUG );
				// If file really exists
				if (is_file ( $file )) {
					$count = $this->pdf->setSourceFile ( $file );
					// import all page
					for($i = 1; $i <= $count; $i ++) {
						// New page
						$this->pdf->AddPage ();
						
						//Merge Invoice PDF
						$tplIdx = $this->pdf->importPage ( $i );
						
						$this->pdf->useTemplate ( $tplIdx, 0, 0, $this->width );
						if (method_exists ( $this->pdf, 'AliasNbPages' ))
							$this->pdf->AliasNbPages ();
					}
				}
			}
		}
	}
}
