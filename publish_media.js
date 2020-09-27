require(['jquery'], function($) { $(function(){
	
	var filenames = $('.media_files .media_file');
	var publishURL = $('.media_files').attr('data-publish');
	var server = $('.media_files').attr('data-server');
	var form = $('#step2_form');

	fetchMediaInfo(0);

	function fetchMediaInfo(mediaElem){

		if (mediaElem >= filenames.length){
			console.log("End!");
			form.find('[type="submit"]').removeAttr('disabled');
			return;
		}

		var file = filenames.eq(mediaElem);
		var filename = file.find('.filename').text();
		var digest = file.find('.digest').text();

		$.get(publishURL, { 'digest':digest, 'server':server }, function(data){

			var downloadUrl = data['download_url'];
			file.find('.download_url').text(downloadUrl);
			file.find('.length').text(data['length']);
			file.find('.status').removeClass('loading').addClass('completed');

			form.append('<input type="hidden" name="'+digest+'" value="'+encodeURI(downloadUrl)+'">');
			
			fetchMediaInfo(mediaElem+1);
		});
	}

});});
