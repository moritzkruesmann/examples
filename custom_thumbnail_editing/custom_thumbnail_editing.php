<?php
/**
 * Plugin Name: Bellendo Thumbnails
 * Plugin URI: bellendo.de
 * Description: Allows to modify product thumbnails to look better in front end
 * Version: 1.0
 * Author: bellendo
 */

add_action('admin_menu', 'wpdocs_register_my_custom_submenu_page');
add_action('admin_enqueue_scripts', 'tc_include_css_javscript_callback');
function tc_include_css_javscript_callback() {
    wp_register_style( 'tc_style', plugins_url('/inc/thumbnail_correction.css',__FILE__ ) );
    wp_enqueue_style( 'tc_style' );
    wp_enqueue_script( 'tc_js_script', plugins_url('/inc/thumbnail_correction.js',__FILE__ ));
}

function wpdocs_register_my_custom_submenu_page() {
    add_submenu_page(
        'upload.php',
        'Thumbnails',
        'Thumbnails',
        'manage_options',
        'Thumbnails-submenu-page',
        'Thumbnails_submenu_page_callback' );
}



/*
 * GUI
 * Three Step Interface to Guide through the Thumbnail Correction
 * At the beginning the user has to choose a category. Based on this category he will be shown product thumbnails.
 */


function Thumbnails_submenu_page_callback() {

    echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';

    /*
     * First Step: Load Category List and let user choose. Certain POST Data is created in this step.
     */
    if ($_POST['step'] != 'confirm' && $_POST['step'] != 'start_processing' && $_GET['step'] != 'final') { ?>

        <h2 class="tc_title">Thumbnail Correction: Select Category</h2>
        <form id="pre_filter_by_category" action="" method="post">


            <select name="select_category" id="select_category">
                <?php

                /*
                 * Get all categories. Woocommerce only allows to display parent categories.
                 * So we will have to loop three times to get to the sub sub categories
                 */

                $args = array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'parent'   => 0
                );
                $product_cat = get_terms( $args );

                //loop for parent categories
                foreach ($product_cat as $parent_product_cat)
                {
                    // Display Categorie as option in selectbox with name and id
                    echo '<option value="' . $parent_product_cat->term_id . '">'.$parent_product_cat->name.'</option>';

                    // Loop to find Subcategories and repeat
                    $sub_args = array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                        'parent'   => $parent_product_cat->term_id
                    );
                    $sub_product_cats = get_terms( $sub_args );
                    foreach ($sub_product_cats as $sub_product_cat)
                    {
                        echo '<option value="' .$sub_product_cat->term_id . '"> - '.$sub_product_cat->name.'</option>';

                        // Sub Sub Categories Loop
                        $sub_sub_args = array(
                            'taxonomy' => 'product_cat',
                            'hide_empty' => false,
                            'parent'   => $sub_product_cat->term_id
                        );
                        $sub_sub_product_cats = get_terms( $sub_sub_args );
                        foreach ($sub_sub_product_cats as $sub_sub_product_cat)
                        {
                            echo '<option value="' .$sub_sub_product_cat->term_id . '"> - - '.$sub_sub_product_cat->name.'</option>';

                        }

                    }
                } ?>
            </select>
            <input id="bellendo_resize_images_select_confirm_category" class="button" type="submit" value="Kategorie auswählen">
            <input type="hidden" name="step" value="confirm">
        </form>
        <?php
    }

    /*
     * Get Category and list products in backend
     * Give user feedback about amount of images.
     */
    if ($_POST['step'] == 'confirm') {
        $term = get_term_by( 'id', $_POST['select_category'], 'product_cat' ); ?>

        <h2 class="tc_title">Thumbnail Correction: Choose Products from <?php echo $term->name; ?></h2>

        <?php
            /*
             * Loop through all products in this category
             */
            $choosen_category_id = $_POST['select_category'];

            $the_query = new WP_Query( array(
                'post_type'             => 'product',
                'post_status'           => 'publish',
                'ignore_sticky_posts'   => 1,
                'posts_per_page'        => -1,
                'tax_query'             => array(
                    array(
                        'taxonomy'      => 'product_cat',
                        'field' => 'term_id',
                        'terms'         => $choosen_category_id,
                        'operator'      => 'IN' // Possible values are 'IN', 'NOT IN', 'AND'.
                    ),
                    array(
                        'taxonomy'      => 'product_visibility',
                        'field'         => 'slug',
                        'terms'         => 'exclude-from-catalog', // Possibly 'exclude-from-search' too
                        'operator'      => 'NOT IN'
                    )
                )
            ) );

            /*
             * For user information gather some data in these values
             */
            $product_amount = 0;
            $product_amount_uncorrected = 0;

            /*
             * build wrapper in for each loop. Display only at the end. User information is supposed to be above it. so no direct echo of divs
             */
            $wrapper_startput = '<form id="tc_choose_thumbnail_gallery_wrapper" action="" method="post">';
        /*
         * Display Statistics for Editor
         */


            while ( $the_query->have_posts() ) :
                $the_query->the_post();
                $loop_product_id = get_the_id();
                $product_amount++;
                $thumbnail_already_corrected = get_post_meta($loop_product_id, '_product_thumbnail_corrected', true);
                if($thumbnail_already_corrected != 'thumbnail_corrected') {
                    $thumbnail_already_corrected = "thumbnail_not_yet_corrected";
                    $product_amount_uncorrected++;
                }

                /*
                 * we create a wrapper with checkbox and image. Chechbox will be hidden and toggled via JS when image is clicked.
                 */

                $wrapper_content .= '<div id="choose_thumbnail_gallery_' . $product_amount .'" class="choose_thumbnail_single '.$thumbnail_already_corrected .'">
                                            <input class="tc_multiproduct_selectbox" type="checkbox" name="tc_selected_products[]" value="' . $loop_product_id .'"/>
                                                <div class="tc_img_wrap">' . woocommerce_get_product_thumbnail() . '
                                                    <div class="tc_img_title"><a  target="_blank" rel="noopener noreferrer" href="' . get_edit_post_link() .'">' . get_the_title() . '</a></div>
                                                </div>
                                      </div>';
            endwhile;

                $wrapper_content .=  '</div>';
        $wrapper_stats = '<div class="tc_head_wrap"><div class="tc_info_box"><span>Für die Kategorie ' .$term->name .' gibt es ' . $product_amount . ' Produkt(e). Davon sind ' . $product_amount_uncorrected . ' noch nicht korrigiert.</span></div><div class="tc_info_box"><span><a href="/wp-admin/upload.php?page=Thumbnails-submenu-page">Zurück zur Auswahl</a></span></div><input id="bellendo_resize_images_select_proceed" class="button" type="submit" value="Thumbnails korrigieren"></div>';
            echo $wrapper_startput . $wrapper_stats . $wrapper_content;
            ?>

                <input type="hidden" name="step" value="start_processing">
            </form>
            <?php

        }

    /*
     * Start the process. Get the ids of all products in lieferanten and check again for products that have already been run. for all other run both the image thumbnail function and (blindly) the gallery functions.
     * One could check beforehand if gallery images exist but i dont think the performance issue is big in this case.
     *
     * After code is run we redirect to start page and give quick feedback.
     *
     * TODO MAYBE:
     * error logger integration
     * performance enhancement
     */
    if ($_POST['step'] == 'start_processing') {

        $selected_products_ids = $_POST['tc_selected_products'];

        foreach ($selected_products_ids as $product_id) {

            $the_id = $product_id;
            /*
             * check if already has been run for this product
             */
            $thumbnail_already_corrected = get_post_meta($the_id, '_product_thumbnail_corrected', true);
            if ($thumbnail_already_corrected != 'thumbnail_corrected') {
                //ingle Image
                resize_thumbnail_by_id($the_id);
                // Gallery Images
                //resize_gallery_thumbnails_by_id($the_id); /* deactivated for pfannen test. When activated, gallery images will also be optimized
            }
        }

       $url = $_POST['_wp_http_referer']. 'upload.php?page=Thumbnails-submenu-page&thumbnail_correction=complete&step=final';
       wp_redirect($url);
    }
    if ($_GET['step'] == 'final' && $_GET['thumbnail_correction'] == 'complete') { ?>
        <h2 class="tc_title">Thumbnail Correction: Abgeschlossen</h2>
    <span class="tc_info_box"><i class="fas fa-thumbs-up"></i> Thumbnailkorrektur der Produkte ist abgeschlossen. Bitte Browsercache leeren!</span>

   <?php } ?>

<?php
}

/*
 * This function is for single thumbnail images or "featured images"
 *
 * We need to edit thumbnails for 100x100 and 300x300 so we run the code for both of these sizes
 *
 * We get full image as reference (this should never be cropped) and then the currently cropped one as our file that is supposed to be overwritten
 *
 * we run the image ressize on this single image
 *
 * finaly register this product as already handled by this code for future reference
 *
 */

function resize_thumbnail_by_id($id){

    //either shop_catalog or woocommerce_thumbnail both have 300x300
    $image_sizes_array = ['woocommerce_thumbnail', 'woocommerce_gallery_thumbnail'];

        foreach ($image_sizes_array as $image_size_key) {

            $square_size = get_image_size_width($image_size_key);

                $input_image = get_the_post_thumbnail_url($id, 'full');
                $output_location = get_the_post_thumbnail_url($id, $image_size_key);

                resize_image($square_size, $input_image, $output_location);

                update_post_meta($id, '_product_thumbnail_corrected', 'thumbnail_corrected');
        }

}

/*
 * similar to singular one only that one extra loop is needed to get each of the images in the gallery
 */

function resize_gallery_thumbnails_by_id($id){
    //either shop_catalog or woocommerce_thumbnail both have 300x300
    $image_sizes_array = ['woocommerce_thumbnail', 'woocommerce_gallery_thumbnail'];

    $product = wc_get_product( $id );
    $gallery_images = $product->get_gallery_image_ids();
    if ($gallery_images) {

        foreach ($image_sizes_array as $image_size_key) {

            $square_size = get_image_size_width($image_size_key);


            foreach ($gallery_images as $gallery_image) {
                $input_image = wp_get_attachment_image_src($gallery_image, 'full')[0];
                $output_location = wp_get_attachment_image_src($gallery_image, $image_size_key)[0];

                resize_image($square_size, $input_image, $output_location);

            }

        }
    }
}

/*
 * We had some issues with images beeing blank which was caused by wrong extensions so those are filterd correctly. i highly suspect only jpeg and png are used in shop. otherwise please adapt the tow instances
 *
 * first get the extensions and filter by them
 *
 * create new image for each instance from original source image
 *
 * then fill with white color and in choosen size
 *
 * check for landscape and portrait and continue
 *
 * finally overwrite cropped source thumbnail and done
 */

function resize_image($square_size, $input_image,$output_location){

    $info = getimagesize($input_image);
    $extension = image_type_to_extension($info[2]);

    // Load up the original image based on extension
    if ($extension == '.jpeg'){
        $src  = imagecreatefromjpeg(return_path($input_image));
    }
    if($extension == '.png'){
        $src  = imagecreatefrompng(return_path($input_image));
    }

    $w = imagesx($src); // image width
    $h = imagesy($src); // image height

    // Create output canvas and fill with white
    $final = imagecreatetruecolor($square_size,$square_size);
    $bg_color = imagecolorallocate ($final, 255, 255, 255);
    imagefill($final, 0, 0, $bg_color);

    // Check if portrait or landscape
    if($h>=$w){
        // Portrait, i.e. tall image
        $newh=$square_size;
        $neww=intval($square_size*$w/$h);
       // printf("New: %dx%d\n",$neww,$newh);
        // Resize and composite original image onto output canvas
        imagecopyresampled(
            $final, $src,
            intval(($square_size-$neww)/2),0,
            0,0,
            $neww, $newh,
            $w, $h);
    } else {
        // Landscape, i.e. wide image
        $neww=$square_size;
        $newh=intval($square_size*$h/$w);
       // printf("New: %dx%d\n",$neww,$newh);
        imagecopyresampled(
            $final, $src,
            0,intval(($square_size-$newh)/2),
            0,0,
            $neww, $newh,
            $w, $h);
    }

    // Write result based on extension
    if ($extension == '.jpeg'){
        imagejpeg($final,return_path($output_location));
    }
    if($extension == '.png'){
        imagepng($final,return_path($output_location));
    }

}



function compare_height_with_width($imageurl){
    $imagesize_array = getimagesize(return_path($imageurl));

    $height = $imagesize_array[0];
    $width = $imagesize_array[1];

    if ($height >= $width) {//greater than or equal
        return $height;
    }else{
        return $width;
    }
}

function return_path($image){

    $final_path = parse_url($image, PHP_URL_PATH);

    $path = $_SERVER['DOCUMENT_ROOT'] . $final_path;

    return $path;
};

function img_path_by_id($id, $type){
    return return_path(get_the_post_thumbnail_url( $id, $type));
}

function img_url_by_id($id, $type){
    return get_the_post_thumbnail_url( $id, $type);
}

function get_image_size_width($name){
    global $_wp_additional_image_sizes;
    return $_wp_additional_image_sizes[$name]['width'];

}
