var minicartUpdate = false;

jQuery(document).ready(function () {
    
    //Disable enter click event (when update qty - cleared all)
    jQuery("body.checkout-cart-index").keypress(function (event) {
        if (event.keyCode == 13) {
            event.preventDefault();
        }
    });

    //for theme rwd
    jQuery(document).on('click', "#header-cart a.close.skip-link-close", function (event) {
        event.preventDefault();
        hideShowMiniCart();
    });

    //for theme rwd
    jQuery(document).on('click', ".header-minicart a.skip-link.skip-cart", function (event) {
        event.preventDefault();
        hideShowMiniCart();
    });

    // work only when jQuery update minicart (for rwd theme)
     function hideShowMiniCart() {
        if (minicartUpdate) {
            if (jQuery(".header-minicart a.skip-link.skip-cart").hasClass('skip-active')) {
                jQuery(".header-minicart a.skip-link.skip-cart").removeClass('skip-active');
                jQuery("#header-cart").removeClass('skip-active');
            } else {
                jQuery(".header-minicart a.skip-link.skip-cart").addClass('skip-active');
                jQuery("#header-cart").addClass('skip-active');
            }
        }
    }

    //this function is use to get base url of site
    function getBaseUrl() {
        var baseurl = document.getElementById("base_url").value;
        return baseurl;
    }
    
    //code for updpdate and delete

    //Delete item from cart in sidebar
    jQuery(document).on('click', ".sidebar .block-cart .btn-remove", function (event) {
        event.preventDefault();
        updateCart(this.href.replace("checkout/cart", "ajaxcart/index"));
    });

    //Delete item from cart page
    jQuery(document).on('click', ".checkout-cart-index a.btn-remove.btn-remove2", function (event) {
        event.preventDefault();
        updateCart(this.href.replace("checkout/cart/delete", "ajaxcart/index/deleteCart"));
    });

    //Delete all items from cart page
    jQuery(document).on('click', "#shopping-cart-table .btn-empty", function (event) {
        event.preventDefault();
        updateCart(getBaseUrl() + '/ajaxcart/index/deleteall');
    });
    
    //Update item on cart page
    jQuery(document).on('change', "#shopping-cart-table .input-text.qty", function (event) {
        event.preventDefault();
        var id = this.name.replace(/[^\d.]/g, '');
        updateCart(getBaseUrl() + 'ajaxcart/index/update?id=' + id + '&qty=' + this.value);
    });
});
function updateCart(url) {
  showLoading();
  jQuery.ajax({
    url: url,
    dataType: 'json',
    success: function (data) {
			hideLoading();
			setAjaxData(data);
		}
	});
}
function showLoading() {
    jQuery('#AjaxCartLoader').show();
};
function hideLoading() {
    jQuery('#AjaxCartLoader').hide();
};
function setAjaxData(data) {
	//fill all global variables

	//update top links for cart
	if (data.toplink) {
		jQuery('.header .links').replaceWith(data.toplink);
	}
	//update sidebar cart
	if (data.sidebar) {
		if (jQuery('.block-cart')) {
			jQuery('.block-cart').replaceWith(data.sidebar);
		}
		
	}
	//update minicart for rwd theme
	if (data.minicart) {
		if (jQuery('.header-minicart')) {
			jQuery('.header-minicart').empty().append(data.minicart);
		}
		
	}
	//update cart
	if (data.checkout) {
		if (jQuery('.cart').length) {
			jQuery('.cart').replaceWith(data.checkout);
		}
	}
	minicartUpdate = true;
};
   
   
