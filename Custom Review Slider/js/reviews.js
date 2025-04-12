jQuery(document).ready(function($) {
    // Initialize carousel
    $('.reviews-carousel').slick({
        dots: true,
        infinite: true,
        speed: 300,
        slidesToShow: 3,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 5000,
        prevArrow: '<button type="button" class="slick-prev">←</button>',
        nextArrow: '<button type="button" class="slick-next">→</button>',
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 1
                }
            },
            {
                breakpoint: 600,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1
                }
            }
        ]
    });

    // Read More/Less functionality
    $('.reviews-carousel, .reviews-grid').on('click', '.read-more-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $content = $btn.closest('.review-content');
        var $excerpt = $content.find('.review-excerpt');
        var $fullContent = $content.find('.review-full-content');
        
        $fullContent.slideToggle(300, function() {
            if ($fullContent.is(':visible')) {
                $btn.find('.text').text('Read Less');
                $btn.addClass('active');
            } else {
                $btn.find('.text').text('Read More');
                $btn.removeClass('active');
            }
        });
    });

    // Modal functionality
    var modal = $('#review-modal');
    var btn = $('#open-review-modal');
    var span = $('.close-modal');
    
    btn.on('click', function() {
        modal.css('display', 'flex');
    });
    
    span.on('click', function() {
        modal.css('display', 'none');
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.css('display', 'none');
        }
    });
    
    // Form submission
    $('#review-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(form[0]);
        var submitBtn = form.find('.submit-form-btn');
        
        submitBtn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                form.hide();
                $('.modal-content').append(
                    '<div class="review-success" style="padding: 20px; text-align: center; color: #28a745;">' +
                    '<h3>Thank you for your review!</h3>' +
                    '<p>It will be published after approval.</p>' +
                    '</div>'
                );
                
                setTimeout(function() {
                    modal.css('display', 'none');
                    location.reload();
                }, 2000);
            },
            error: function() {
                alert('Error submitting review. Please try again.');
                submitBtn.prop('disabled', false).text('Submit Review');
            }
        });
    });
    
    // Show success message
    if (window.location.search.indexOf('review_submitted=1') > -1) {
        $('html, body').animate({
            scrollTop: $('.reviews-carousel-container').offset().top - 100
        }, 500);
    }
});