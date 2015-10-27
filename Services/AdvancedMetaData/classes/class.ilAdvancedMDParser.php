<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("Services/Xml/classes/class.ilSaxParser.php");
include_once("Services/Utilities/classes/class.ilSaxController.php");
include_once("Services/Utilities/interfaces/interface.ilSaxSubsetParser.php");
include_once("Services/AdvancedMetaData/classes/class.ilAdvancedMDFieldDefinition.php");
include_once("Services/AdvancedMetaData/classes/class.ilAdvancedMDValues.php");

/**
 * Adv MD XML Parser
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id: class.ilCourseXMLParser.php 53320 2014-09-12 11:33:49Z fwolf $
 *
 * @extends ilMDSaxParser
 */
class ilAdvancedMDParser extends ilSaxParser implements ilSaxSubsetParser
{	
	protected $obj_id; // [int]
	protected $rec_id; // [int]
	protected $mapping; // [object]	
	protected $cdata; // [string]	
	protected $value_records = array(); // [array]
	protected $current_record; // [ilAdvancedMDValues]
	protected $current_value; // [ilAdvancedMDFieldDefinition]
	protected $has_values; // [bool]
	protected $record_ids = array(); // [array]
	
	// local adv md record support
	protected $local_record; // [string]
	protected $local_rec_id; // [int]
	protected $local_rec_map = array(); // [array]
	
	function __construct($a_obj_id, $a_mapping)
	{		
		parent::__construct();
		
		$parts = explode(":", $a_obj_id);
		$this->obj_id = $parts[0];		
		$this->mapping = $a_mapping;
	}
	
	function setHandlers($a_xml_parser)
	{
		$this->sax_controller = new ilSaxController();
		$this->sax_controller->setHandlers($a_xml_parser);
		$this->sax_controller->setDefaultElementHandler($this);		
	}
	
	function createLocalRecord($a_xml, $a_obj_id, $a_sub_type = null)
	{		
		$tmp_file = ilUtil::ilTempnam();
		file_put_contents($tmp_file, $a_xml);
		
		// see ilAdvancedMDSettingsGUI::importRecord()
		try
		{	
			// the (old) record parser does only support files
			include_once('Services/AdvancedMetaData/classes/class.ilAdvancedMDRecordParser.php');
			$parser = new ilAdvancedMDRecordParser($tmp_file);
			$parser->setContext($a_obj_id, ilObject::_lookupType($a_obj_id), $a_sub_type);			
			$parser->setMode(ilAdvancedMDRecordParser::MODE_INSERT_VALIDATION);
			$parser->startParsing();
			$parser->setMode(ilAdvancedMDRecordParser::MODE_INSERT);
			$parser->startParsing();								
		}
		catch(ilSAXParserException $exc)
		{
			
		}
				
		unlink($tmp_file);	
		
		$map = $parser->getRecordMap();
		$this->local_rec_map = $map;
		return array_shift(array_keys($map));
	}
	
	function handlerBeginTag($a_xml_parser,$a_name,$a_attribs)
	{
		switch($a_name)
		{
			case 'AdvancedMetaData':									
				break;
			
			case 'Record':				
				break;
				
			case 'Value':				
				$this->initValue($a_attribs['id'], $a_attribs['sub_type'], $a_attribs['sub_id']);
				break;
		}
	}
	
	function handlerEndTag($a_xml_parser,$a_name)
	{
		switch($a_name)
		{
			case 'AdvancedMetaData':	
				// get rid of temp local record
				$this->local_rec_id = null;
				$this->local_rec_map = null;
				
				// we need to write all records that have been created (1 for each sub-item)
				foreach($this->value_records as $record)
				{
					$record->write();
				}
				break;
				
			case 'Record':				
				$this->local_record = base64_decode(trim($this->cdata));					
				break;
				
			case 'Value':
				$value = trim($this->cdata);
				if(is_object($this->current_value) && $value != "")
				{
					$this->current_value->importValueFromXML($value);					
				}
				break;
		}
		$this->cdata = '';
	}
	
	function handlerCharacterData($a_xml_parser,$a_data)
	{
		if($a_data != "\n")
		{
			// Replace multiple tabs with one space
			$a_data = preg_replace("/\t+/"," ",$a_data);

			$this->cdata .= $a_data;
		}
	}
	
	protected function initValue($a_import_id, $a_sub_type = "", $a_sub_id = 0)
	{
		$this->current_value = null;
				
		// get parent objects		
		$new_parent_id = $this->mapping->getMapping("Services/AdvancedMetaData", "parent", $this->obj_id);		
		if(!$new_parent_id)
		{
			return;
		}
		if($a_sub_type)
		{								
			$new_sub_id = $this->mapping->getMapping("Services/AdvancedMetaData", "advmd_sub_item", "advmd:".$a_sub_type.":".$a_sub_id);						
			if(!$new_sub_id)
			{
				return;
			}
		}
				
		// init local record?	
		// done here because we need object context
		if($this->local_record)
		{
			$this->local_rec_id = $this->createLocalRecord($this->local_record, $new_parent_id, $a_sub_type);	
			$this->local_record = null;
		}		
		
		// find record via import id		
		if(!$this->local_rec_id)
		{
			if($field = ilAdvancedMDFieldDefinition::getInstanceByImportId($a_import_id))
			{
				$rec_id = $field->getRecordId();
			}
		}
		// (new) local record
		else
		{
			$rec_id = $this->local_rec_id;
		}
					
		// init record definitions		
		if($a_sub_type)
		{
			$rec_idx = $rec_id.";".$a_sub_type.";".$new_sub_id;
			if(!array_key_exists($rec_idx, $this->value_records))	
			{
				$this->value_records[$rec_idx] = new ilAdvancedMDValues($rec_id, $new_parent_id, $a_sub_type, $new_sub_id);
			}				
		}
		else
		{			
			$rec_idx = $rec_id.";;";
			if(!array_key_exists($rec_idx, $this->value_records))	
			{
				$this->value_records[$rec_idx] = new ilAdvancedMDValues($rec_id, $new_parent_id);
			}				
		}						

		// init ADTGroup before definitions to bind definitions to group
		$this->value_records[$rec_idx]->getADTGroup();

		// find element with import id
		if(!$this->local_rec_id)
		{		
			foreach($this->value_records[$rec_idx]->getDefinitions() as $def)
			{										
				if($a_import_id == $def->getImportId())
				{
					$this->current_value = $def;
					break;
				}
			}			
		}
		else
		{			
		    $field_id = $this->local_rec_map[$rec_id][$a_import_id];
			if($field_id)
			{
				foreach($this->value_records[$rec_idx]->getDefinitions() as $def)
				{										
					if($field_id == $def->getFieldId())
					{
						$this->current_value = $def;
						break;
					}
				}	
			}
		}
	 	
		// record will be selected for parent
		// see ilAdvancedMetaDataImporter
		if($this->current_value && 
			!$this->local_rec_id)
		{								
			$this->record_ids[$new_parent_id][$a_sub_type][] = $rec_id;
		}	 	 	
	}
	
	public function getRecordIds()
	{	
		return $this->record_ids;
	}
}