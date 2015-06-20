<?php
// This provides the locator with the json data it needs to dispaly the shops.

if (!defined('_PS_VERSION_'))
 exit;
	
class DpdCarrierParcelShopLocatorModuleFrontController extends ModuleFrontController
{		
	public function init()
	{
		parent::init();
		
		$this->delisID = Configuration::get('DPDCARRIER_DELISID');
		$this->delisPw = Configuration::get('DPDCARRIER_PASSWORD');

		$this->url = Configuration::get('DPDCARRIER_LIVE_SERVER') == 1 ? 'https://public-ws.dpd.com/services/' : 'https://public-ws-stage.dpd.com/services/';
		
		$this->timeLogging = Configuration::get('DPDCARRIER_TIME_LOGGING') == 1;
		
		$login;
		if(!($login = unserialize(Configuration::get('DPDCARRIER_LOGIN')))
			|| !($login->url == $this->url))
		{
			try
			{
				$login = new DpdLogin($this->delisID, $this->delisPw, $this->url, $this->timeLogging);
			}
			catch (Exception $e)
			{
				Logger::addLog('Something went wrong logging in to the DPD Web Services (' . $e->getMessage() . ')', 3, null, null, null, true);
				die;
			}
			Configuration::updateValue('DPDCARRIER_LOGIN', serialize($login));
		}
		
		$long = isset($_POST['long']) ? $_POST['long'] : die;
		$lat = isset($_POST['lat']) ? $_POST['lat'] : die;
		
		try
		{
			$parcelshopfinder = new DpdParcelShopFinder($login);
			$parcelshopfinder->search(array('long' => $long, 'lat' => $lat));
		}
		catch (Exception $e)
		{
			Logger::addLog('Something went wrong looking for ParcelShops (' . $e->getMessage() . ')', 3, null, null, null, true);
			die;
		}
			
		if($parcelshopfinder->login->refreshed)
		{
			Logger::addLog('DPD Login Refreshed');
			$parcelshopfinder->login->refreshed = false;
			Configuration::updateValue('DPDCARRIER_LOGIN', serialize($parcelshopfinder->login));
		}
		
		$parcelshop_cookie = array();
	
		foreach($parcelshopfinder->results as $key => $shop)
		{
			$parcelshop_cookie[$shop->parcelShopId] = new stdClass();
			
			$parcelshop_cookie[$shop->parcelShopId]->id = $shop->parcelShopId;
			$parcelshop_cookie[$shop->parcelShopId]->name = $shop->company;
			$parcelshop_cookie[$shop->parcelShopId]->address = $shop->street . ' ' . $shop->houseNo;
			$parcelshop_cookie[$shop->parcelShopId]->postcode = $shop->zipCode;
			$parcelshop_cookie[$shop->parcelShopId]->city = $shop->city;
			$parcelshop_cookie[$shop->parcelShopId]->id_country = Country::getByIso($shop->isoAlpha2);
		}
		
		$this->context->cookie->DPD_ParcelShops = serialize($parcelshop_cookie);
		$this->context->cookie->write();
		
		// TODO: add new search results together with old ones.
		
		echo json_encode($parcelshopfinder->results);
		die;
	}
}
