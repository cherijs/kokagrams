jQuery( document ).ready(function( $ ) {

	$('.wp-instagram-item a').magnificPopup({
		type: 'image',
		gallery:{
			enabled:true
		},
		mainClass: 'mfp-fade',
		callbacks: {
			change: function() {
				if (this.isOpen) {
				    this.wrap.addClass('mfp-open');
				}
			}
		}
	});

});