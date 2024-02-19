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

    let currentSlide = 0;
    const totalSlides = document.querySelectorAll('.slide').length;
    const sliderContainer = document.getElementById('slider-container');
    const slideWidth = document.querySelector('.slide').clientWidth;

    function changeSlide(direction) {
        currentSlide = Math.max(0, Math.min(currentSlide + direction, totalSlides - 1));
        updateSlider();
    }

    function updateSlider() {
        sliderContainer.scroll({left: currentSlide * slideWidth, behavior: 'smooth'});
        updateButtonVisibility();

		console.log('currentSlide:', currentSlide);
		console.log('slideWidth:', slideWidth);
		console.log('Transform:', sliderContainer.style.transform);
		console.log('Slider Container Height:', sliderContainer.clientHeight);
    }

    let touchStartX = 0;
    let touchEndX = 0;

    sliderContainer.addEventListener('touchstart', (e) => {
        if (currentSlide !== totalSlides -1) {
            e.preventDefault();
        }
        touchStartX = e.changedTouches[0].screenX;
    });

    function handleTouchEnd(e) {
        touchEndX = e.changedTouches[0].screenX;
        if (touchEndX < touchStartX) {
            changeSlide(1); // Swipe left
        }
        if (touchEndX > touchStartX) {
            changeSlide(-1); // Swipe right
        }
    }

    sliderContainer.addEventListener('touchcancel', handleTouchEnd);
    sliderContainer.addEventListener('touchend', handleTouchEnd);

    if(sliderContainer) {
        let section = document.querySelector("intro-section, content-section");

        const prevBtn = document.createElement('div');
        prevBtn.id = 'prevBtn';
        prevBtn.innerHTML = '&#10094;';
        prevBtn.onclick = () => changeSlide(-1);
        section.appendChild(prevBtn);

        const nextBtn = document.createElement('div');
        nextBtn.id = 'nextBtn';
        nextBtn.innerHTML = '&#10095;';
        nextBtn.onclick = () => changeSlide(1);
        section.appendChild(nextBtn);

        function updateButtonVisibility() {
            prevBtn.style.visibility = currentSlide === 0 ? 'hidden' : 'visible';
            nextBtn.style.visibility = currentSlide === totalSlides - 1 ? 'hidden' : 'visible';
        }

        updateButtonVisibility();
    }
});
