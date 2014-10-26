
$( document ).ready(function() {
	$('[name=reveal]').each(function(i){
		$('#answer'+$(this).attr('id')).toggle();
		$(this).click(function() {
			$('#answer'+$(this).attr('id')).toggle("blind", 1000 );
			$(this).hide();
		});
		}
	);
});