<?php
if (!defined('_PS_VERSION_'))
  exit;
 
function upgrade_module_0_1_6($object)
{
		$carrier_mapping = array( 
			'DPD Classic' => 'Home'
			,'DPD Home' => 'Home with predict'
			,'DPD ParcelShop' => 'PICKUP'
		);
		
		foreach($carrier_mapping as $old => $new)
		{
			$carrier_id = Configuration::get(generateVariableName($object, $old . ' id'));
			Configuration::updateValue(generateVariableName($object, $new . ' id'), $carrier_id);
			Configuration::deleteByName(generateVariableName($object, $old . ' id'));
			
			copy(dirname(__FILE__) . '/../views/img/' . strtolower(str_replace(' ', '_', $new)) . '.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier_id . '.jpg');
		}
		
		return true;
}

function generateVariableName($object, $input)
{
	return strtoupper($object->name . '_' . str_replace(" ", "_", $input));
}
?>