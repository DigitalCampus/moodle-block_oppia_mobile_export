$( document ).ready(function() {
	$('[name=reveal]').each(function(i){
		var revealSection = $(this).addClass('showmore revealed');
		var target = $('#answer'+$(this).attr('id'));

		function revealContent(){
			target.addClass('revealed');
			revealSection.removeClass('revealed');
		}

		target.addClass('showmore').show();
		if (revealSection.has('button').length > 0){
			
			revealSection.find('button').on('click', function(){
				inputValue = revealSection.find('input').eq(0).val();
				if (inputValue != null && inputValue != ''){ 
					revealContent(); 
				}
				else{
					var errorMsg = revealSection.find('.error-msg');
					if (errorMsg.length == 0){
						errorMsg = $('<div class="error-msg" style="display:none;">You have to enter some text.</div>');
						revealSection.append(errorMsg);
					}
					errorMsg.fadeIn();
				}
			});
		}		
		else{
			revealSection.on('click', revealContent);	
		}
		
	});
});
