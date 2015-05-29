function disableOpcPayment(){
	$("#opc_payment_methods a").click(function(){
		alert('Don\'t forget to select a ParcelShop');
		$('html, body').animate({
			scrollTop: $("#dpdLocatorContainer").offset().top
		}, 2000);
		return false;
	});
}

function enableOpcPayment(){
	$("#opc_payment_methods a").unbind('click');
}

function dpdChosenShop(shopID) {
	var query = $.ajax({
		type: 'POST'
		,cache: false
		,url: 'index.php?fc=module&module=dpdcarrier&controller=parcelshopselected'
		,data: {
			parcelshopId : shopID
		}
		,dataType: 'json'
		,success: function(json) {
			if(json.hasErrors){
				alert(json.errors);
			} else {
				dpdLocator.hideLocator();
				$('#chosenShop').html(json.result);
				if($('#opc_payment_methods')){
					enableOpcPayment();
				}
			}
		}
	});
}
