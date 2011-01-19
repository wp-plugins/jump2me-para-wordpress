// Stuff that happen on the post page

function jump2me_update_count() {
	var len = 140 - jQuery('#titlewrap #title').val().length;
	jQuery('#jump2me_count').html(len);
	jQuery('#jump2me_count').removeClass();
	if (len < 60) {jQuery('#jump2me_count').removeClass().addClass('len60');}
	if (len < 30) {jQuery('#jump2me_count').removeClass().addClass('len30');}
	if (len < 15) {jQuery('#jump2me_count').removeClass().addClass('len15');}
	if (len < 0) {jQuery('#jump2me_count').removeClass().addClass('len0');}
}

(function($){
	var jump2me = {
		
		// Ajax: success
		success : function(x, div) {
			if ( typeof(x) == 'string' ) {
				this.error({'responseText': x}, div);
				return;
			}

			var r = wpAjax.parseAjaxResponse(x);
			if ( r.errors )
				this.error({'responseText': wpAjax.broken}, div);

			r = r.responses[0];
			$('#'+div).html('<p>'+r.data+'</p>');
			
			console.log( r.supplemental.shorturl );
			
			//Update also built-in Shortlink button
			$('#shortlink').val( r.supplemental.shorturl );
		},

		// Ajax: failure
		error : function(r, div) {
			var er = r.statusText;
			if ( r.responseText )
				er = r.responseText.replace( /<.[^<>]*?>/g, '' );
			if ( er )
				$('#'+div).html('<p>Ocorreu um erro durante a requisição Ajax: '+er+'</p>');
		}
	};
	
	$(document).ready(function(){
		// Add the character count
		jQuery('#titlewrap #title').after('<div id="jump2me_count" title="Número de caracteres disponíveis">000</div>').keyup(function(e){
			jump2me_update_count();
		});
		jump2me_update_count();

		$('#jump2me_promote').click(function(e) {
			jump2me.send();
			e.preventDefault();
		});
		$('#jump2me_reset').click(function(e) {
			jump2me.reset();
			e.preventDefault();
		});
		
	})

})(jQuery);