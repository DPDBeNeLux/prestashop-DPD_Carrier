<!-- Carrier DpdCarrier  -->
<div id="dpdLocatorContainer">
<input type="hidden" name="dpd_carrier_shop_id" id="dpd_carrier_shop_id">
<input type="hidden" name="dpd_carrier_shop_details" id="dpd_carrier_shop_details">
	<div id="chosenShop"></div>
</div>

<script type="text/javascript">
{literal}
	//window.addEventListener('load', function (){
		var dpdLocator = new DPD.locator({
			rootpath: '{/literal}{$module_path}{literal}',
			ajaxpath: 'index.php?fc=module&module=dpdcarrier&controller=parcelshoplocator',
			containerId: 'dpdLocatorContainer',
			fullscreen: false,
			width: '100%',
			height: '600px',
			filter: 'pick-up',
			country: '{/literal}{$country}{literal}',
			callback: 'dpdChosenShop',
			dictionaryXML: '{/literal}{$dictionary_XML}{literal}',
			language: '{/literal}{$lang_iso}{literal}_{/literal}{$country}{literal}'
		});
		
		dpdLocator.initialize();
		
		$('#carrier_area').ready(function(){
			$('[id^="delivery_option_"]').each(function(index) {
				// if it is parcelshop option
				if(this.value == '{/literal}{$carrier_id}{literal},'){
					// If the parcelshop option is selected on load
					if(this.checked){
						dpdLocator.showLocator('{/literal}{$selected_address|escape:quotes}{literal}');
						disableOpcPayment();
						}
					this.onchange = function(){
						disableOpcPayment();
						$('#chosenShop').html('');
						dpdLocator.showLocator('{/literal}{$selected_address|escape:quotes}{literal}');
						return false;
					}
				} else {
					this.onchange = function(){
						enableOpcPayment();
						dpdLocator.hideLocator();
						return false
					}
				}
			});
		});
	//});

{/literal}
</script>
<!-- End Carrier DpdCarrier  -->