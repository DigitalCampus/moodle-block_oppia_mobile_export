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

    // Slider functionality
    const totalSlides = document.querySelectorAll('slide').length;

    if (totalSlides > 0) {
        let currentSlide = 0;
        const slide = document.querySelector('slide');
        const sliderContainer = slide.parentNode;
        const slideStyle = window.getComputedStyle(slide);
        const slideWidth = slide.offsetWidth + parseFloat(slideStyle.marginRight) + parseFloat(slideStyle.marginLeft);

        if (sliderContainer.getAttribute('pagination') === 'true') {
            const pagination = document.createElement('div');
            pagination.classList.add('pagination');
            for (let i = 0; i < totalSlides; i++) {
                const paginationItem = document.createElement('div');
                paginationItem.classList.add('pagination-item');
                pagination.appendChild(paginationItem);
            }
            sliderContainer.appendChild(pagination);

        }

        const paginationItems = document.querySelectorAll('.pagination-item');
        if (paginationItems.length > 0) {
            paginationItems[0].classList.add('active');
        }

        function changeSlide(direction) {
            currentSlide = Math.max(0, Math.min(currentSlide + direction, totalSlides - 1));
            if (paginationItems.length > 0)
            {
                if (direction > 0) {
                    paginationItems[currentSlide].classList.add('active');
                } else {
                    paginationItems[currentSlide + 1].classList.remove('active');
                }
            }

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
            if (currentSlide !== totalSlides - 1) {
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

        if (sliderContainer) {

            const prevBtn = document.createElement('div');
            prevBtn.id = 'prevBtn';
            prevBtn.innerHTML = '&#10094;';
            prevBtn.addEventListener('touchstart', function () {
                changeSlide(-1);
            });
            sliderContainer.appendChild(prevBtn);

            const nextBtn = document.createElement('div');
            nextBtn.id = 'nextBtn';
            nextBtn.innerHTML = '&#10095;';
            nextBtn.addEventListener('touchstart', function () {
                changeSlide(1);
            });
            sliderContainer.appendChild(nextBtn);

            function updateButtonVisibility() {
                prevBtn.style.visibility = currentSlide === 0 ? 'hidden' : 'visible';
                nextBtn.style.visibility = currentSlide === totalSlides - 1 ? 'hidden' : 'visible';
            }

            updateButtonVisibility();
        }
    }

    // Cards functionality
    const cards = document.querySelectorAll('card');
    if (cards.length > 0) {
        let currentCard = 0;
        cards[0].classList.add('active');
        cards[1].classList.add('next');

        $('card').on('click', function () {
            $('card').css({'pointer-events': 'none'});

            $('card.active').addClass('animate-leave');

            setTimeout(function () {
                $('card.animate-leave').addClass('animate-back').removeClass('animate-leave');
                $('card').parent().prepend($('.animate-back'));
                cards[currentCard].classList.remove('active');
                $('card.next').addClass('active').removeClass('next');
                currentCard = (currentCard + 1) % cards.length;

                const nextCard = cards[(currentCard + 1) % cards.length];
                nextCard.classList.add('next');
            }, 300);
            setTimeout(function () {
                $('card.animate-back').removeClass('animate-back');
                $('card').css({'pointer-events': 'auto'});
            }, 700);
        });
    }
});
