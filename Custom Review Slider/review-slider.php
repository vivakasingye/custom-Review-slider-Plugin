<?php
/**
 * Plugin Name: Custom Review Slider
 * Description: Display testimonials in slider or grid format with categories. Shortcodes: [reviews_carousel] and [reviews_grid]
 * Version: 1.0
 * Author: KASINGYE VIVA
 * Author URI: x.com/vivakasingye1
 * License: GPL2
 * Text Domain: custom-review-slider
 */

// 1. Register Custom Post Type and Taxonomy
function create_review_post_type() {
    register_post_type('reviews',
        array(
            'labels' => array(
                'name' => __('Reviews'),
                'singular_name' => __('Review'),
                'add_new_item' => __('Add New Review'),
                'edit_item' => __('Edit Review'),
                'all_items' => __('All Reviews'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-star-filled',
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'reviews'),
        )
    );

    register_taxonomy(
        'review_category',
        'reviews',
        array(
            'label' => __('Review Categories'),
            'rewrite' => array('slug' => 'review-category'),
            'hierarchical' => true,
            'show_in_rest' => true,
        )
    );
}
add_action('init', 'create_review_post_type');

// 2. Add Custom Fields
function add_review_meta_boxes() {
    add_meta_box('review_details', 'Review Details', 'display_review_meta_box', 'reviews', 'normal', 'high');
}
add_action('add_meta_boxes', 'add_review_meta_boxes');

function display_review_meta_box($post) {
    wp_nonce_field('save_review_meta', 'review_meta_nonce');
    $rating = get_post_meta($post->ID, 'rating', true);
    ?>
    <div class="meta-field">
        <label for="rating">Rating:</label>
        <select name="rating" id="rating">
            <option value="5" <?php selected($rating, '5'); ?>>★★★★★</option>
            <option value="4" <?php selected($rating, '4'); ?>>★★★★☆</option>
            <option value="3" <?php selected($rating, '3'); ?>>★★★☆☆</option>
            <option value="2" <?php selected($rating, '2'); ?>>★★☆☆☆</option>
            <option value="1" <?php selected($rating, '1'); ?>>★☆☆☆☆</option>
        </select>
    </div>
    <?php
}

function save_review_meta($post_id) {
    if (!isset($_POST['review_meta_nonce']) || !wp_verify_nonce($_POST['review_meta_nonce'], 'save_review_meta')) {
        return;
    }
    if (isset($_POST['rating'])) {
        update_post_meta($post_id, 'rating', sanitize_text_field($_POST['rating']));
    }
}
add_action('save_post_reviews', 'save_review_meta');

// 3. Handle Form Submission
function handle_review_submission() {
    if (!isset($_POST['submit_review'])) return;

    if (!wp_verify_nonce($_POST['review_nonce'], 'submit_review_action')) {
        wp_die('Security check failed');
    }

    $attachment_id = 0;
    if (!empty($_FILES['reviewer_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attachment_id = media_handle_upload('reviewer_image', 0);
        if (is_wp_error($attachment_id)) {
            wp_die('Error uploading image');
        }
    }

    $post_data = array(
        'post_title'   => sanitize_text_field($_POST['reviewer_name']),
        'post_content' => sanitize_textarea_field($_POST['review_content']),
        'post_type'    => 'reviews',
        'post_status'  => 'pending',
    );

    $post_id = wp_insert_post($post_data);

    if ($post_id) {
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        update_post_meta($post_id, 'rating', sanitize_text_field($_POST['review_rating']));
        if (!empty($_POST['review_category'])) {
            wp_set_post_terms($post_id, array(intval($_POST['review_category'])), 'review_category');
        }
    }

    wp_redirect(add_query_arg('review_submitted', '1', home_url()));
    exit;
}
add_action('template_redirect', 'handle_review_submission');

// 4. Carousel Shortcode
function reviews_carousel_shortcode() {
    ob_start();
    $reviews = new WP_Query(array(
        'post_type' => 'reviews',
        'posts_per_page' => -1,
        'order' => 'DESC',
        'post_status' => 'publish'
    ));
    ?>
    <div class="reviews-carousel-container">
        <?php if ($reviews->have_posts()) : ?>
            <div class="reviews-carousel">
                <?php while ($reviews->have_posts()) : $reviews->the_post(); 
                    $rating = get_post_meta(get_the_ID(), 'rating', true);
                    $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                    $full_content = get_the_content();
                    $excerpt = wp_trim_words($full_content, 25);
                    $categories = get_the_terms(get_the_ID(), 'review_category');
                    $review_date = get_the_date('M Y');
                    $first_category = !empty($categories) ? $categories[0]->name : '';
                ?>
                    <div class="review-slide">
                        <div class="review-grid-header">
                            <?php if ($first_category) : ?>
                                <div class="review-category"><?php echo esc_html($first_category); ?></div>
                            <?php endif; ?>
                            <div class="review-rating">
                                <?php echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?>
                            </div>
                        </div>
                        <div class="reviewer-info">
                            <?php if ($thumbnail) : ?>
                                <div class="review-avatar">
                                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title_attribute(); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="reviewer-name-date">
                                <div class="reviewer-name"><?php the_title(); ?></div>
                                <div class="review-date"><?php echo $review_date; ?></div>
                            </div>
                        </div>
                        <div class="review-content">
                            <div class="review-excerpt"><?php echo $excerpt; ?></div>
                            <?php if (str_word_count($full_content) > 25) : ?>
                                <div class="review-full-content" style="display:none;"><?php echo wpautop($full_content); ?></div>
                                <button class="read-more-btn">
                                    <span class="text">Read More</span>
                                    <span class="icon">↓</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p class="no-reviews">No reviews yet. Be the first to review!</p>
        <?php endif; ?>
        <button class="submit-review-btn" id="open-review-modal">Submit Your Review</button>
    </div>

    <div id="review-modal" class="review-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Submit Your Review</h3>
            <form id="review-form" method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('submit_review_action', 'review_nonce'); ?>
                <input type="hidden" name="submit_review" value="1">
                <div class="form-group">
                    <label for="reviewer_name">Your Name*</label>
                    <input type="text" name="reviewer_name" id="reviewer_name" required>
                </div>
                <div class="form-group">
                    <label for="reviewer_image">Your Photo</label>
                    <input type="file" name="reviewer_image" id="reviewer_image" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="review_category">Category</label>
                    <?php wp_dropdown_categories(array(
                        'taxonomy' => 'review_category',
                        'name' => 'review_category',
                        'id' => 'review_category',
                        'show_option_none' => 'Select Category',
                        'hide_empty' => false,
                        'hierarchical' => true
                    )); ?>
                </div>
                <div class="form-group">
                    <label for="review_rating">Rating*</label>
                    <select name="review_rating" id="review_rating" required>
                        <option value="">Select Rating</option>
                        <option value="5">★★★★★ Excellent</option>
                        <option value="4">★★★★☆ Very Good</option>
                        <option value="3">★★★☆☆ Good</option>
                        <option value="2">★★☆☆☆ Fair</option>
                        <option value="1">★☆☆☆☆ Poor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="review_content">Your Review*</label>
                    <textarea name="review_content" id="review_content" rows="5" required></textarea>
                </div>
                <button type="submit" class="submit-form-btn">Submit Review</button>
            </form>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('reviews_carousel', 'reviews_carousel_shortcode');




function reviews_grid_shortcode() {
    ob_start();
    $categories = get_terms(array(
        'taxonomy' => 'review_category',
        'hide_empty' => false,
        'parent' => 0
    ));
    ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <style>
   

        .category-filter {
            list-style: none;
            padding: 0;
        }

        .category-filter li {
            margin-bottom: 12px;
        }

        .category-filter input[type="radio"] {
            display: none;
        }

        .category-filter label {
            position: relative;
            padding-left: 28px;
            cursor: pointer;
            display: block;
            font-weight: 500;
        }

        .category-filter label::before {
            content: "";
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #444;
            position: absolute;
            left: 0;
            top: 2px;
            background: #fff;
        }

        .category-filter input[type="radio"]:checked + label::before {
            background: #444;
            box-shadow: inset 0 0 0 4px #fff;
        }

        .review-grid-item {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #eee;
            overflow: hidden;
            margin-bottom: 30px;
        }
.review__header {
	background-color: #f8bf02;
	color: #000;
	border-radius: 12px 12px 0 0;
	padding: 20px;
	font-weight: normal;
	position: relative;
	clip-path: polygon( 0 0, 100% 0, 100% 90%, 95% 100%, 90% 90%, 85% 100%, 80% 90%, 75% 100%, 70% 90%, 65% 100%, 60% 90%, 55% 100%, 50% 90%, 45% 100%, 40% 90%, 35% 100%, 30% 90%, 25% 100%, 20% 90%, 15% 100%, 10% 90%, 5% 100%, 0 90% );
}

        .review-title {
            font-size: 18px;
            margin-bottom: 8px;
        }

       .review-stars {
	font-size: 16px;
	color: #fff;
	margin-bottom: 8px;
}

        .reviewer-name {
            font-size: 14px;
            font-weight: normal;
        }

        .review-content {
            padding: 20px;
            position: relative;
        }

        .review-excerpt {
            margin-bottom: 15px;
        }

        .read-more-btn {
            background: #ffb300;
            border: none;
            color: #000;
            font-weight: bold;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
        }

        .review-full-content {
            display: none;
            margin-top: 10px;
        }
    </style>

    <div class="reviews-grid-container">
        <div class="row align-items-start">
            <div class="col-lg-2">
                <div class="review-grid-item2">
                    <div class="review__header2">
                        <h5 class="mb-3">FILTER ON:</h5>
                        <ul class="category-filter">
                            <li>
                                <input type="radio" id="cat_all" name="review_category" value="0" checked>
                                <label for="cat_all">All Categories</label>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <input type="radio" id="cat_<?php echo $cat->term_id; ?>" name="review_category" value="<?php echo $cat->term_id; ?>">
                                    <label for="cat_<?php echo $cat->term_id; ?>"><?php echo $cat->name; ?></label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-10">
                <div id="reviews-container" class="row"></div>
            </div>
        </div>
    </div>

    <script>
        function loadReviews(categoryId = 0) {
            const data = {
                action: 'load_reviews',
                category: categoryId
            };

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(data)
            })
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('reviews-container');
                container.innerHTML = html;

                document.querySelectorAll('.read-more-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const content = this.previousElementSibling;
                        const isOpen = content.style.display === 'block';
                        content.style.display = isOpen ? 'none' : 'block';
                        this.textContent = isOpen ? 'Read More' : 'Read Less';
                    });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.category-filter input').forEach(radio => {
                radio.addEventListener('change', function () {
                    loadReviews(this.value);
                });
            });
            loadReviews(0);
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('reviews_grid', 'reviews_grid_shortcode');

function ajax_load_reviews_callback() {
    $cat = isset($_POST['category']) ? intval($_POST['category']) : 0;

    $args = [
        'post_type' => 'reviews',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    if ($cat) {
        $args['tax_query'] = [[
            'taxonomy' => 'review_category',
            'field' => 'term_id',
            'terms' => array_merge([$cat], get_term_children($cat, 'review_category')),
            'include_children' => true,
            'operator' => 'IN'
        ]];
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $rating = intval(get_post_meta(get_the_ID(), 'rating', true));
            $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
            $date = get_the_date('F Y');
            $subcats = get_the_terms(get_the_ID(), 'review_category');
            $subcat_name = '';
            if ($subcats) {
                foreach ($subcats as $sc) {
                    if ($sc->parent != 0) {
                        $subcat_name = $sc->name;
                        break;
                    }
                }
            }
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="review-grid-item">
                    <div class="review__header">
                        <div class="review-title"><?php echo esc_html($subcat_name); ?></div>
                        <div class="review-stars"><?php echo $stars; ?></div>
                        <div class="reviewer-name"><?php the_title(); ?> (<?php echo $date; ?>)</div>
                    </div>
                    <div class="review-content">
                        <div class="review-excerpt"><?php echo wp_trim_words(get_the_content(), 25); ?></div>
                        <div class="review-full-content"><?php echo wpautop(get_the_content()); ?></div>
                        <button class="read-more-btn">Read More</button>
                    </div>
                </div>
            </div>
            <?php
        endwhile;
        wp_reset_postdata();
    else :
        echo '<p>No reviews found.</p>';
    endif;

    wp_die();
}
add_action('wp_ajax_load_reviews', 'ajax_load_reviews_callback');
add_action('wp_ajax_nopriv_load_reviews', 'ajax_load_reviews_callback');






// 6. Enqueue Assets
function review_system_assets() {
    wp_enqueue_style('review-system-css', plugin_dir_url(__FILE__) . 'css/reviews.css');
    wp_enqueue_style('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);
    wp_enqueue_script('review-system-js', plugin_dir_url(__FILE__) . 'js/reviews.js', array('jquery', 'slick-carousel'), null, true);
    wp_localize_script('review-system-js', 'reviewSystem', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'review_system_assets');

// 7. Activation/Deactivation
function review_slider_activate() { flush_rewrite_rules(); }
register_activation_hook(__FILE__, 'review_slider_activate');
function review_slider_deactivate() { flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'review_slider_deactivate');
