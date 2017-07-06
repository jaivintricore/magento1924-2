
jQuery(document).ready(function()
{
   //popup width auto resize the height 
  if(jQuery(window).width() < 1024){
    jQuery(window).resize(function() {
      jQuery('.popup').height(jQuery(window).height() - 46);
    });
  }

  jQuery(window).trigger('resize');
  // onclick form popup code 
  jQuery('#profile1').click(function(e){
	  var productAddToCartForm2 = new VarienForm('product_addtocart_form');
	  if(productAddToCartForm2.validator.validate())
    {
		//var formData1 = productAddToCartForm2.serializeArray();
		console.log(productAddToCartForm2);
	}
    jQuery('.popup, .overlay').show();

    // close popup onclick close button
    jQuery("#button-close").click(function(){
      jQuery(".popup").hide();
      jQuery(".overlay").hide()
    });

     // close popup onclick overlay background
    jQuery(document).on("mouseup",".overlay",function (e) {
      jQuery(".popup").hide();
      jQuery(".overlay").hide()
    });

    // close success msg onclick overlay background
    jQuery(".overlaymsg").click(function(){
      jQuery(".popupmsg").hide();
      jQuery(".overlaymsg").hide()
    });

    // close success msg onclick close img
    jQuery(".popupmsg #msg-close").click(function(){
      jQuery(".popupmsg").hide();
      jQuery(".overlaymsg").hide()
    });
    return;
  });

// get url from phtml
//var mailurl = document.getElementById("mailurl").value;

//submit form using ajax and validite data
jQuery("#makeofferForm").submit(function(e){

  e.preventDefault();
  var url = jQuery(this).attr('action');
  var makeofferForm = new VarienForm('makeofferForm', true);
  var formdata =  jQuery("#makeofferForm").serializeArray();

    // validite the data before submit
    if(makeofferForm.validator.validate())
    {
      jQuery('.loaderajax').show();
      jQuery.ajax
      ({ 
        url : url,
        type: "post",
        data : formdata,
        success: function(response)
        {
          jQuery('.loaderajax').hide();
          //append the response data in div.
          jQuery("#makeofferForm")[0].reset();
          jQuery(".mymassage").html('');
          jQuery(".mymassage").append(response);
          jQuery(".popup").hide();
          jQuery(".overlay").hide();
          jQuery('.popupmsg, .overlaymsg').show();
        }
      });
    }
  });


});

