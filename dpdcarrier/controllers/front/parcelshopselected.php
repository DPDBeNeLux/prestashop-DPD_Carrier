<?php
// This provides the locator with the json data it needs to dispaly the shops.

if (!defined('_PS_VERSION_'))
 exit;
	
class DpdCarrierParcelShopSelectedModuleFrontController extends ModuleFrontController
{		
	public function init()
	{	
		parent::init();
		if(!(isset($_SERVER['HTTP_REFERER'])
			|| parse_url($_SERVER['HTTP_REFERER'])['host'] == parse_url(_PS_BASE_URL_)['host']))
		{
			Tools::Redirect(__PS_BASE_URI__);
			die;
		}
		else
		{
			parent::init();
			$this->ajax = true;
		}
	}
	
	public function displayAjax() {
		if(!($id_parcelshop = Tools::getValue('parcelshopId')))
			$return = array(
				'hasErrors' => true
				,'errors' => $this->l('There was an error while selecting you ParcelShop. If this problem persists please select an other delivery option.')
			);
		else
		{		
			$parcelshop_cookie = unserialize($this->context->cookie->DPD_ParcelShops);
			$delivery_address = new Address($this->context->cart->id_address_delivery);
			if (isset($parcelshop_cookie[$id_parcelshop]))
			{
				$parcelshop = $parcelshop_cookie[$id_parcelshop];
				if($delivery_address->id_country == $parcelshop->id_country)
				{
					$parcelshop_address = new Address();
					$parcelshop_address->id_country = $parcelshop->id_country;
					$parcelshop_address->alias = 'DPD ParcelShop';
					$parcelshop_address->company = $parcelshop->name;
					$parcelshop_address->firstname = $delivery_address->firstname;
					$parcelshop_address->lastname = $delivery_address->lastname;
					$parcelshop_address->address1 = $parcelshop->address;
					$parcelshop_address->postcode = $parcelshop->postcode;
					$parcelshop_address->city = $parcelshop->city;
					$parcelshop_address->other = $parcelshop->id;
					$parcelshop_address->add();
					
					$this->context->cookie->parcelshop_address_id = $parcelshop_address->id;
					$this->context->cookie->write();
					
					$return = array();
					$return['result'] = '<p>' . $this->l('You have chosen') . ': <strong>' . $parcelshop->name . '</strong>';
					$return['result'] .= ' <br>' . $this->l('Located at') . ': ' . $parcelshop->address . ', ' . $parcelshop->postcode . ' ' . $parcelshop->city . '</p>';
					$return['result'] .= '<a href="#" onclick="javascript:dpdLocator.showLocator();return false;">' . $this->l('Click here to alter your choice') .'</a>';
				} else {
					Logger::addLog('Customer, ' . $this->context->customer->firstname . ' ' . $this->context->customer->lastname . ' (' . $this->context->customer->id . '), tried to hack the country restrictions for DPD ParcelShop Delivery.', 2, null, null, null, true);
					$return = array(
						'hasErrors' => true
						,'errors' => $this->l('Somehow the shop is not in the same country as your delivery address. As this is not allowed this warning has been logged. Please select a shop in the same country as your pre selected delivery address.')
					);
				}
			} else {
				Logger::addLog('Customer, ' . $this->context->customer->firstname . ' ' . $this->context->customer->lastname . ' (' . $this->context->customer->id . '), tried to hack  the ParcelShop locator by sending an unknown shop id (' . $id_parcelshop . ')', 2, null, null, null, true);
				$return = array(
					'hasErrors' => true
					,'errors' => $this->l('Somehow the shop that you selected was not in the list of proposed shops. As this is not allowed this warning has been logged. Please select a shop from the initial suggestions.')
				);
			}
			
			die(Tools::jsonEncode($return));
		}
	}
	
	private function l($data)
	{
		return $data;
	}

}