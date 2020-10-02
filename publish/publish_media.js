require(['jquery'], function($) { $(function(){
	
	var filenames = $('.media_files .media_file');
	var publishURL = $('.media_files').attr('data-publish');
	var server = $('.media_files').attr('data-server');
	var form = $('#step2_form');

	var pendingFiles = false;
	var pending;

	var publishForm = $('#publish_form').hide();
	publishForm.on('submit', function(e){
		e.preventDefault();

		var username = publishForm.find('[name="username"]').val();
		var password = publishForm.find('[name="password"]').val();
		pending = $('.media_files .media_file.pending');
		pending.find('.status').removeClass('pending').addClass('loading')
		
		pendingFiles = false;
		publishForm.hide();
		publishMedia(0, username, password);
	});

	fetchMediaInfo(0);

	function fetchMediaInfo(mediaElem){

		if (mediaElem >= filenames.length){
			if (pendingFiles){
				publishForm.show();
			}
			else{
				form.find('[type="submit"]').removeAttr('disabled');
			}
			return;
		}

		var file = filenames.eq(mediaElem);
		var moodlefile = file.attr('data-moodlefile');
		var filename = file.find('.filename').text();
		var digest = file.find('.digest').text();

		$.ajax({
			type: 'get',
			url: publishURL, 
			data: { 'digest':digest, 'server':server }, 
			
			success: function(data, status){
				var downloadUrl = data['download_url'];
				file.find('.download_url').text(downloadUrl);
				file.find('.length').text(data['length']);
				file.find('.status').removeClass('loading').addClass('completed');
				form.append('<input type="hidden" name="'+digest+'" value="'+encodeURI(downloadUrl)+'">');
				fetchMediaInfo(mediaElem+1);
			},

			error: function (response, status, error) {
				pendingFiles = true;
				if (response.status == 404){
					file.addClass('pending');
					file.find('.status').removeClass('loading').addClass('pending');
				}
				else{
					file.find('.status').removeClass('loading').addClass('error');	
				}
				fetchMediaInfo(mediaElem+1);
			}
		});
	}

	function publishMedia(mediaElem, username, password){

		if (mediaElem >= pending.length){
			if (pendingFiles){
				publishForm.show();
			}
			else{
				form.find('[type="submit"]').removeAttr('disabled');
			}
			return;
		}

		var file = pending.eq(mediaElem);
		var moodlefile = file.attr('data-moodlefile');
		var filename = file.find('.filename').text();
		var digest = file.find('.digest').text();

		$.ajax({
			type: 'post',
			url: publishURL, 
			data: { 'digest':digest, 'server':server, 'moodlefile':moodlefile, 'username': username, 'password':password }, 
			
			success: function(data, status){
				var downloadUrl = data['download_url'];
				file.find('.download_url').text(downloadUrl);
				file.find('.length').text(data['length']);
				file.find('.status').removeClass('loading').addClass('completed');
				form.append('<input type="hidden" name="'+digest+'" value="'+encodeURI(downloadUrl)+'">');
				publishMedia(mediaElem+1, username, password);
			},

			error: function (response, status, error) {
				pendingFiles = true;
				if (response.status == 404){
					file.addClass('pending');
					file.find('.status').removeClass('loading').addClass('pending');
				}
				else{
					file.find('.status').removeClass('loading').addClass('error');	
				}
				publishMedia(mediaElem+1, username, password);
			}
		});
	}



});});
