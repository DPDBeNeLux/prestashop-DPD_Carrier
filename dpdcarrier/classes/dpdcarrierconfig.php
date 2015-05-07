<?php

class DpdCarrierConfig
{
	private $config = array(
		array(
			'name'	=> 'Delis Credentials'
			,'elements'	=> array(
				array(
					'name'	=>	'DelisID'
					,'required'	=> true
				)
				,array(
					'type' => 'password'
					,'name'	=> 	'Password'
					,'required'	=> true
				)
				,array(
					'type' => 'radio'
					,'name' => 'Live Server'
					,'required' => true
					,'class' => 't'
					,'is_bool' => true
					,'default_value' => 2
					,'values' => array(
						array(
							'id' => 'active_on'
							,'value' => 1
							,'label' => 'Yes'
						)
						,array(
							'id' => 'active_off'
							,'value' => 2
							,'label' => 'No'
						)
					)
				)
			)
		)
		,array(
			'name'	=> 'Layout Options'
			,'elements'	=> array(
				array(
					'type' => 'radio'
					,'name' => 'Display Locator'
					,'required' => true
					,'class' => 't'
					,'default_value' => 2
					,'values' => array(
						array(
							'id' => 'before'
							,'value' => 1
							,'label' => 'Before'
						)
						,array(
							'id' => 'after'
							,'value' => 2
							,'label' => 'After'
						)
					)
				)
			)
		)
		,array(
			'name'	=> 'Logging Options'
			,'elements'	=> array(
				array(
					'type' => 'radio'
					,'name' => 'Time Logging'
					,'required' => true
					,'class' => 't'
					,'default_value' => 1
					,'values' => array(
						array(
							'id' => 'on'
							,'value' => 1
							,'label' => 'On'
						)
						,array(
							'id' => 'off'
							,'value' => 2
							,'label' => 'Off'
						)
					)
				)
			)
		)
	);

	public function getAllElementsFlat()
	{
		$result = array();
		
		foreach ($this->config as $config_group)
		{
			$result = array_merge($result, $config_group['elements']);
		}
		
		return $result;
	}
	
	public function getAllElements()
	{
		return $this->config;		
	}

}