<?php

if (!defined('_PS_VERSION_'))
 exit;
 
if(!class_exists('DpdLogin'))
	include_once dirname(__FILE__).'/classes/DPD/dpdlogin.php';
 
include_once dirname(__FILE__).'/classes/dpdcarrierconfig.php';
include_once dirname(__FILE__).'/classes/DPD/dpdparcelshopfinder.php';
include_once dirname(__FILE__).'/classes/DPD/dpdshippingmethods.php';

class DpdCarrier extends Module
{
	private $config;
	private $hooks = array(
		'displayHeader'							// Used to add javascript and ccs files to the header
		,'displayBeforeCarrier'			// Used to display the map before the carrier selection
		,'displayCarrierList'				// Used to display the map after the carrier selection
		,'actionCarrierUpdate'			// Triggered when carrier is edited in back-end
		,'actionCarrierProcess'			// Triggered when carrier choice is validated
		,'actionPaymentConfirmation'  // Tiggered on payment selection
		,'actionValidateOrder'  		// Triggered when order is validated (last step)
		,'displayOrderDetail'				// Used to display chosen parcelshop in order detail page
		,'displayOrderConfirmation'	// Used to display chosen parcelshop on order confirmation page
		,'actionObjectOrderUpdateAfter'  // Used to change delivery method when parcelshop address is changed
	);
	
	/************************************
	 * Construct, Install and UnInstall *
	 ************************************/
	
	public function __construct()
	{
		$this->config = new DpdCarrierConfig();
		
		$this->name = 'dpdcarrier';
		$this->version = '0.1.9';
		$this->author = 'Michiel Van Gucht';
		
		$this->tab = 'shipping_logistics';
		$this->need_instance = 1;
		$this->bootstrap = true;
		
		$this->limited_countries = array('BE', 'LU', 'NL');
		
		parent::__construct();
		
		$this->displayName = $this->l('DPD Carrier');
		$this->description = $this->l('This module will add several carriers to your checkout.');
		
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall the DPD Carriers Module?');
		
		if (self::isInstalled($this->name))
			$this->checkConfiguraion();
	}
	
	public function install()
	{
		if (substr(_PS_VERSION_, 0, 3) < '1.5')
			return false;
			
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);
			
		if (!parent::install())
			return false;
			
		foreach($this->hooks as $hook_name)
			if(!$this->registerHook($hook_name))
				return false;
		
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $this->generateVariableName($config_element['name']);
			$default_value = isset($config_element['default_value']) ? $config_element['default_value'] : '';
			if (!Configuration::updateValue($variable_name, $default_value))
				return false;
		}
		
		if(!$this->initCarriers())
			return false;

		return true;
	}
	
	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
			
		foreach($this->hooks as $hook_name)
			if(!$this->unregisterHook($hook_name))
				return false;
		
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $this->generateVariableName($config_element['name']);
			if (!Configuration::deleteByName($variable_name))
				return false;
		}
		
		if(!$this->removeCarriers())
			return false;
		
		return true;
	}

	/************************
	 * Configuration Screen *
	 ************************/
	
	public function getContent()
	{
		$output = null;
		
		if (Tools::isSubmit('submit'.$this->name))
		{
			foreach ($this->config->getAllElementsFlat() as $config_element)
			{
				$variable_name = $this->generateVariableName($config_element['name']);
				$user_readable_name = $config_element['name'];
				
				$value = strval(Tools::getValue($variable_name));
				if (!$value || empty($value))
					$output .= $this->displayError($this->l('Invalid Configuration value ('.$user_readable_name.')'));
				else
					Configuration::updateValue($variable_name, $value);
			}
			
			$shipping_methods = new DpdShippingMethods();			
			foreach($shipping_methods->methods as $method)
			{
				$carrier = new Carrier(Configuration::get($this->generateVariableName($method->name . ' id')));
				$carrier->url = 'https://tracking.dpd.de/parcelstatus?locale=' . $this->context->language->iso_code . '_' . $this->context->country->iso_code .
					'&delisId=' . Tools::getValue($this->generateVariableName("DelisID")) . 
					'&matchCode=@';
				$carrier->save();
			}
			
			if ($output == null)
				$output .= $this->displayConfirmation($this->l('Settings updated'));
		}
		return $output.$this->displayForm();
	}
	
	public function displayForm()
	{
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		
		$fields_config = $this->config->getAllElements();
		
		$fields_form = array();
		
		foreach ($fields_config as $group_key => $config_group)
		{
			if($group_key == 0 || (substr(_PS_VERSION_, 0, 3) > '1.5'))
				$fields_form[$group_key]['form'] = array(
					'legend'	=> array(
						'title'	=> $this->l($config_group['name'])
					),
					'submit'	=> array(
						'title'	=> $this->l('Save'),
						'class'	=> 'button'
					)
				);
			foreach ($config_group['elements'] as $element)
			{
				$config = $element;
				$config['name'] = $this->generateVariableName($element['name']);
				$config['label'] = $this->l($element['name']);
				
				if(!isset($element['type']))
					$config['type'] = 'text';
					
				$fields_form[$group_key]['form']['input'][] = $config;
			}
		}
		
		$helper = new HelperForm();
		
		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		
		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		
		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;			// false -> remove toolbar
		$helper->toolbar_scroll = true;			// yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back'	=> 
				array(
					'href'	=> AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
					'desc'	=> $this->l('Back to list')
				)
			);
		
		// Load current value
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $this->generateVariableName($config_element['name']);
			$helper->fields_value[$variable_name] = Configuration::get($variable_name);
		}
		
		return $helper->generateForm($fields_form);
	}
	
	/******************
	 * Hook functions *
	 ******************/
	 
	public function hookDisplayHeader($params)
	{
		if(is_a($this->context->controller, 'OrderController') || is_a($this->context->controller, 'OrderOpcController'))
		{
			$this->context->controller->addCSS($this->_path.'views/templates/front/css/parcelshoplocator.css');
			
			$this->context->controller->addJS($this->_path.'views/templates/front/js/parcelshoplocator.js');
			$this->context->controller->addJS($this->_path.'views/templates/front/js/dpdcarrier.js');
			
			$this->context->controller->addJS('https://maps.googleapis.com/maps/api/js?libraries=places');
		}	
	}
	
	/*
	 * Returns the parcelshop template before the carriers in the checkout
	 */
	public function hookDisplayBeforeCarrier($params)
	{
		if(Configuration::get($this->generateVariableName('Display Locator')) == 1)
		{
			return $this->getParcelShopLocatorDisplay($this->context->cart->id_address_delivery);
		}
	}
	
	/*
	 * Returns the parcelshop template after the carriers in the checkout
	 */
	public function hookDisplayCarrierList($params)
	{
		if(Configuration::get($this->generateVariableName('Display Locator')) == 2)
		{
			return $this->getParcelShopLocatorDisplay($this->context->cart->id_address_delivery);
		}
	}
	
	/*
	 * Called when carrier(s) are updated
	 * 
	 * @return void
	 */
	public function hookActionCarrierUpdate($params)
	{
		$shipping_methods = new DpdShippingMethods();
		
		foreach ($shipping_methods->methods as $method)
		{
			$var_name = $this->generateVariableName($method->name . ' id');
			
			if ((int)($params['id_carrier']) == (int)(Configuration::get($var_name)))
				Configuration::updateValue($var_name, (int)($params['carrier']->id));
		}
	}
	
	public function hookActionCarrierProcess($params)
	{
		if(!Configuration::get('PS_ORDER_PROCESS_TYPE'))
			$this->checkIfParcelShopSelected($params);
	}
	
	public function hookActionPaymentConfirmation($params)
	{
		// in case of a third party checkout page we try to capture it here.
		if(Configuration::get('PS_ORDER_PROCESS_TYPE'))
			$this->checkIfParcelShopSelected($params);
	}
	
	public function hookActionValidateOrder($params)
	{	

	}
	
	public function hookDisplayOrderConfirmation($params)
	{
		// Parcelshop details in order confirmation page.
	}
	
	public function hookActionObjectOrderUpdateAfter($params)
	{
		$order = $params['object'];
		$delivery_address = new Address($order->id_address_delivery);
		$cookie = new Cookie('parcelshops');
    
		if(isset($cookie->parcelshop_address_id))
		{
			$id_parcelshop_address = $cookie->parcelshop_address_id;
			unset($cookie->parcelshop_address_id);
			unset($cookie->DPD_ParcelShops);
			$cookie->__destruct();
			
			Db::getInstance()->update( 'orders' , 
				array('id_address_delivery' => $id_parcelshop_address), 
				'id_order = ' . $order->id, 0, $null_values);
		}
		elseif( (int)($order->id_carrier) == (int)(Configuration::get('DPDCARRIER_PICKUP_ID'))
		 && $delivery_address->alias != 'Pickup')
		{
			$id_carrier = Configuration::get('DPDCARRIER_HOME_WITH_PREDICT_ID');
			Db::getInstance()->update( 'orders' ,   
				array('id_carrier' => $id_carrier),   
				'id_order = ' . $order->id, 0, $null_values);  
				
			Db::getInstance()->update( 'order_carrier' , 
				array('id_carrier' => $id_carrier), 
				'id_order = ' . $order->id, 0, $null_values);
		}
	}
	
	/*********************
	 * Private functions *
	 *********************/
	private function generateVariableName($input)
	{
		return strtoupper($this->name . '_' . str_replace(" ", "_", $input));
	}
	
	private function initCarriers()
	{
		$weight_multiplier;
		switch(_PS_WEIGHT_UNIT_)
		{
			case 'mg':
				$weight_multiplier = 1000000;
				break;
			case 'g':
				$weight_multiplier = 1000;
				break;
			case 'Kg':
				$weight_multiplier = 1;
				break;
			case 'lbs':
				$weight_multiplier = 0.45359237;
				break;
			case 'st':
				$weight_multiplier = 6.35029318;
				break;
			default:
				$weight_multiplier = 1;
				break;
		}
		
		$dimension_multiplier;
		switch(_PS_DIMENSION_UNIT_)
		{
			case 'mm':
				$dimension_multiplier = 10;
				break;
			case 'cm':
				$dimension_multiplier = 1;
				break;
			case 'dm':
				$dimension_multiplier = 0.1;
				break;
			case 'm':
				$dimension_multiplier = 0.01;
				break;
			case 'in':
				$dimension_multiplier = 2.54;
				break;
			case 'ft':
				$dimension_multiplier = 30.48;
				break;
			default:
				$dimension_multiplier = 1;
				break;
		}
		
		$shipping_methods = new DpdShippingMethods();
		$languages = Language::getLanguages(true);
		
		$default = $shipping_methods->default;
		
		if(!isset($shipping_methods->methods))
			return false;
		
		foreach($shipping_methods->methods as $method)
		{
			$carrier = new Carrier();
			$carrier->name = $method->name;
			$carrier->url = 'https://tracking.dpd.de/parcelstatus?locale=' . $this->context->language->iso_code . '_' . $this->context->country->iso_code .'&query=@';
			$carrier->active = true;
			$carrier->shipping_hanling = true;
			$carrier->range_behavior = 0;
			$carrier->shipping_external = false;
			$carrier->external_module_name = $this->name;
			$carrier->need_range = false;
			$carrier->max_width = (isset($method->max_width) ? $method->max_width : $default->max_width) * $dimension_multiplier;
			$carrier->max_height = (isset($method->max_width) ? $method->max_width : $default->max_width) * $dimension_multiplier;
			$carrier->max_depth = (isset($method->max_width) ? $method->max_width : $default->max_width) * $dimension_multiplier;
			$carrier->max_weight = (isset($method->max_weight) ? $method->max_weight : $default->max_weight) * $weight_multiplier;
			$carrier->grade = 9;
			
			$zones = isset($method->zones) ? $method->zones : $default->zones;
			foreach($zones as $zone_name)
				$carrier->addZone(Zone::getIdByName($zone_name));
			
			foreach ($languages as $language) 
				$carrier->delay[$language['id_lang']] = $method->description;
			
			if (!$carrier->add())
				return false;
			else
			{
				$groups = Group::getGroups(true);
				foreach ($groups as $group) 
				{
					Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_group', array(
						'id_carrier' => (int) $carrier->id,
						'id_group' => (int) $group['id_group']
						), 'INSERT');
					}
					
					$weight_ranges = (isset($method->weight_ranges) ? $method->weight_ranges : $default->weight_ranges);
					$ranges = array();

					for($i = 0; $i < count($weight_ranges) - 1; $i++)
					{
						$rangeWeight = new RangeWeight();
						$rangeWeight->id_carrier = $carrier->id;
						$rangeWeight->delimiter1 = $weight_ranges[$i] * $weight_multiplier;
						$rangeWeight->delimiter2 = $weight_ranges[$i + 1] * $weight_multiplier;
						$rangeWeight->add();
						
						$ranges[] = $rangeWeight;
					}
					
					$zones = (isset($method->zones) ? $method->zones : $default->zones);
					
					foreach($zones as $zone_name)
					{
						$zone = new Zone(Zone::getIdByName($zone_name));
						Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_zone',
							array('id_carrier' => (int) $carrier->id, 'id_zone' => (int) $zone->id), 'INSERT');
						foreach($ranges as $range)
						{
						Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery',
							array('id_carrier' => $carrier->id, 'id_range_price' => NULL, 'id_range_weight' => (int) $range->id, 'id_zone' => (int) $zone->id, 'price' => '0'), 'INSERT');
						}
					}
			}
			
			copy(dirname(__FILE__) . '/views/img/' . strtolower(str_replace(' ', '_', $method->name)) . '.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
				
			Configuration::updateValue($this->generateVariableName($method->name . ' id'), (int)($carrier->id));			
		}
		return true;
	}
	
	private function removeCarriers()
	{
		$shipping_methods = new DpdShippingMethods();

		foreach($shipping_methods->methods as $method)
		{
			$carrier_var_name = $this->generateVariableName($method->name . ' id');
			$carrier = new Carrier(Configuration::get($carrier_var_name));
			
			if (!$carrier->delete() || !Configuration::deleteByName($carrier_var_name))
				return false;
		}
		
		return true;
	}
	
	private function checkConfiguraion()
	{
		$warning = array();
		foreach ($this->config->getAllElementsFlat() as $config_element)
		{
			$variable_name = $this->generateVariableName($config_element['name']);
			$user_readable_name = $config_element['name'];
			if (!($value = Configuration::get($variable_name)) || $value == '')
				$warning[] = $this->l('No value for "'.$user_readable_name.'" provided');
		}
		
		if (!extension_loaded('soap'))
			$warning[] = $this->l('The PHP SOAP extension not installed/enabled on this server.');
			
		if (count($warning))
			$this->warning = implode(' , ',$warning);
	}

	private function getParcelShopLocatorDisplay($id_address)
	{
		$address = new Address((int)$id_address);
		$country = new Country();
		$country_iso = $country->getIsoById($address->id_country);
		$this->context->smarty->assign(
			array(
			'carrier_id' => Configuration::get('DPDCARRIER_PICKUP_ID'),
			'module_path' => $this->_path,
			'dictionary_XML' => $this->_path.'translations/dictionary.xml',
			'selected_address' => $address->address1 . ', ' . $address->postcode . ' ' . $address->city,
			'country' => $country_iso
			)
		);
		return $this->display(__FILE__, 'parcelshop-locator.tpl');
	}

	private function checkIfParcelShopSelected($params)
	{
		if((int)($this->context->cart->id_carrier) == (int)(Configuration::get('DPDCARRIER_PICKUP_ID'))) {
			$cookie = new Cookie('parcelshops');
			if(!(isset($cookie->parcelshop_address_id)))
			{
				$this->context->controller->errors[] = $this->l("You have not selected a ParcelShop");
				$this->context->controller->init();
			}
		}
	}
}
