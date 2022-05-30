
$( document ).ready(function() {
	$('[name=reveal]').each(function(i){
		var revealBtn = $(this).addClass('showmore revealed');
		var target = $('#answer'+$(this).attr('id'));
		target.addClass('showmore').show();
		revealBtn.click(function() {
			target.addClass('revealed');
			revealBtn.removeClass('revealed');
		});
		}
	);
});
