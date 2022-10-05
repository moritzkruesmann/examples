<?php
/**
 * Plugin Name: Image import
 * Plugin URI: bellendo.de
 * Description: Import missed imagies from ek vendor
 * Version: 1.0
 * Author: bellendo
 */

//include('assets/CurlLoginAndDownload.php');
//GUI
add_action('admin_menu', 'wpdocs_register_product_import_page');

function wpdocs_register_product_import_page()
{
    add_submenu_page(
        'tools.php',
        'Product Image Import Helper',
        'Product Image Import Helper',
        'manage_options',
        'product_image_import_helper',
        'wpdocs_product_image_import_helper_page_callback');
}
add_action('admin_post_start_image_import', 'start_image_import');

function wpdocs_product_image_import_helper_page_callback()
{ ?>

<div class="wrap">
    <h2>Bellendo Image Import Helper</h2>

    <?php

    //get response to the user
    if ($_GET['ii_response_message'] == 'complete'){
        echo '<div style="border:2px solid #ccc; font-weight:bold; padding: 15px 5px;">Images imported</div>';
    }

    //Load the first step where the Lieferant ca be choosen
    if ($_POST['step'] != 'confirm' && $_GET['success'] != 'true'){ ?>

        <form id="the_choosen_list" action="" method="post">
            <?php
            //lets call all lieferanten to display in dropdown
            $taxonomy = "lieferanten";
            /** Get all taxonomy terms */
            $lieferanten = get_terms($taxonomy, array(
                    "orderby"    => "count",
                    "hide_empty" => false
                )
            );
            $hierarchy = _get_term_hierarchy($taxonomy);
            ?>
            <select name="lieferant" id="lieferanten">
                <?php

                foreach($lieferanten as $lieferant) {
                    if($lieferant->parent) {
                        continue;
                    }
                    echo '<option value="' . $lieferant->name . '">' . $lieferant->name . '</option>';
                    if($hierarchy[$lieferant->term_id]) {
                        foreach($hierarchy[$lieferant->term_id] as $child) {
                            $child = get_term($child, "category");
                            echo '<option value="' . $lieferant->name . '"> - ' . $child->name . '</option>';
                        }
                    }
                }
                ?>
            </select>
            <input id="bellendo_image_to_gallery_confirm" class="button"  type="submit" value="Lieferant auswählen">
            <input type="hidden" name="step" value="confirm">
            <input type="hidden" name="choosen_lieferant" value="<?php echo $_POST['lieferant']; ?>">
        </form>
        <?php
    }
    //second pseudopage
    // here the user sees how many products a lieferant has and how many of them have the external urls set
    // finally he can start the import for all external images for these products
    if ($_POST['step'] == 'confirm'){

        $the_query = new WP_Query( array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array (
                    'taxonomy' => 'lieferanten',
                    'field' => 'slug',
                    'terms' => $_POST['lieferant'],
                )
            ),
        ) );

        //count of the relevant products
        $amount_of_products = 0;
        $amount_of_products_with_external_images = 0;
        $amount_of_products_with_external_images_no_thumbnail = 0;
        $array_with_product_name = [];
        while ( $the_query->have_posts() ) :
            $the_query->the_post();
            $amount_of_products++;
            $array_with_external_images = 0;
            $array_with_external_images = get_external_image_sources(get_the_id());

            if(count($array_with_external_images) > 0){
                if ( ! has_post_thumbnail(get_the_id())){
                    $amount_of_products_with_external_images_no_thumbnail++;
                }

                $amount_of_products_with_external_images++;
                array_push($array_with_product_name, count($array_with_external_images) . ' - ' .get_the_title());

            }

        endwhile;

        wp_reset_postdata();

        ?>

        <form id="the_choosen_list" action="<?php echo admin_url('admin-post.php');?>" method="post">
            <p>Es gibt <?php echo $amount_of_products; ?> Produkte des Lieferanten <?php echo $_POST['lieferant']; ?></p>
            <p>Davon haben <?php echo $amount_of_products_with_external_images; ?> Bilderlinks aus externer Quelle von <?php echo $amount_of_products_with_external_images_no_thumbnail; ?> keine Bilder gesetzt haben.</p>
            <div style="max-height: 150px; height: 250px; overflow-y:scroll; width: 350px; border: 1px solid #ccc; padding: 15px; margin-bottom: 15px"><?php
                foreach($array_with_product_name as $i => $item) {
                    echo $item;
                    echo '<br>';
                }
                ?>
            </div>

            <input type="hidden" name="action" value="start_image_import">
            <input type="hidden" name="choosen_lieferant" value="<?php echo $_POST['lieferant']; ?>">
            <input id="bellendo_image_to_gallery" type="submit"  class="button" value="Bilder von <?php echo $amount_of_products_with_external_images_no_thumbnail; ?> Produkten laden">
            <?php wp_nonce_field('start_image_import', 'submitform_nonce'); ?>
        </form>
        <?php
    }
    }




    function start_image_import(){
        $logger = wc_get_logger();

        $the_query = new WP_Query( array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array (
                    'taxonomy' => 'lieferanten',
                    'field' => 'slug',
                    'terms' => $_POST['choosen_lieferant'],
                )
            ),
        ) );

        while ( $the_query->have_posts() ) :
            $the_query->the_post();
            $product_id = get_the_id();

            //$logger->debug( 'image_import_instance_' . $product_id , array( 'source' => 'image-import-logger_test' ) );

            if ( ! has_post_thumbnail($product_id) && ! get_post_meta($product_id, '_external_images_imported', true)){
                bellendo_image_to_gallery($product_id);

            }else{

                $logger->debug( 'Image already imported for this Product: '  . $product_id, array( 'source' => 'image-import-logger_test' ) );

            }
        endwhile;

        wp_reset_postdata();
        $url = $_POST['_wp_http_referer']. '&ii_response_message=complete';
        wp_redirect($url);
    }

    function bellendo_image_to_gallery($product_id)
    {

        //$logger = wc_get_logger();
        //$logger->debug( 'image_import_instance_' . $product_id , array( 'source' => 'image-import-logger_test' ) );

        $product = get_post($product_id);

        $product_slug = $product->post_name;
        $product_name = $product->post_title;
        $folder_name = $product_name . ' ' . $product_id; //support of possible duplicated Productnames

        $list_of_urls = get_external_image_sources($product_id);
        $gallery_ids = [];

        $image_run_index = 0;
        foreach ($list_of_urls as $index => $url_of_EK_file) {
            set_time_limit(0);
            if (!empty($url_of_EK_file)) {

                $upload_dir = wp_upload_dir(); // Set upload folder

                $path_and_url = connect_to_ftp_server($url_of_EK_file, $image_run_index, $product_slug ); //return baseurl for file on our server
                $image_internal_path = $path_and_url[0];
                $image_internal_url = $path_and_url[1];

                $image_data = file_get_contents($image_internal_path); // Get image data

                //$logger->debug( 'To Gallery - Filesize after FTP Connect: ' . filesize($image_internal_path) . ' bytes' , array( 'source' => 'image-import-logger_debug' ) );
                // if the string was maybe not an url or somehow wrong
                if (!$image_data == false) {

                    //filter out the extension of the file
                    $image_mimetype = getimagesize($image_internal_path)['mime'];

                    switch ($image_mimetype) {

                        //maybe more to be added for support ??
                        case 'image/jpeg':
                            $extension = 'jpg';
                            break;

                        case 'image/png':
                            $extension = 'png';
                            break;

                    }

                    //$logger->debug( 'To Gallery - Extension decided: ' . $extension , array( 'source' => 'image-import-logger_debug' ) );

                    //create the filename and add the ID as a unique element in case there is a duplicate post title somewwhere
                    //add the index if multiple images
                    $index_suffix = '';
                    if (count($list_of_urls) > 1 && $index > 0) {
                        $index_suffix = '_' . $index;
                        $multi_image_import = true;
                    }

                    $image_name = $product_slug . '_' . $product_id . $index_suffix . '.' . $extension;

                    if (!file_exists($upload_dir['path'] . '/'  . $product_slug. '_' . $product_id . '/' . $image_name)) {

                        $unique_file_name = wp_unique_filename($upload_dir['path']. '/'  . $product_slug. '_' . $product_id, $image_name); // Generate unique name
                        $filename = basename($unique_file_name); // Create image file name

                        // Check folder permission and define file location
                        //Set Productslug as directory name. so not everything is just thrown into the main folder
                        if (wp_mkdir_p($upload_dir['path']. '/'  . $product_slug. '_' . $product_id)) {

                            $file_location = $upload_dir['path'] . '/'  . $product_slug. '_' . $product_id ;
                            $file_base_path = $file_location . '/' . $filename;
                        } else {
                            $file_location = $upload_dir['basedir'] . '/'  . $product_slug. '_' . $product_id;
                            $file_base_path = $file_location . '/' . $filename;
                        }

                        // Create the image  file on the server
                        //file_put_contents($file_base_url, $image_data);

                        //check if imagesize is over zero
                        //$logger->debug( 'To Gallery - Filesize before IMG API request: ' .  filesize($image_internal_path) , array( 'source' => 'image-import-logger_debug' ) );
                        //$logger->debug( 'To Gallery - URL: ' .  filesize($image_internal_url) , array( 'source' => 'image-import-logger_debug' ) );

                        imageoptim_api_request($image_internal_url, $file_base_path);

                        //$logger->debug( 'To Gallery - Filesize after IMG API request: ' .  filesize($file_base_path) , array( 'source' => 'image-import-logger_debug' ) );
                        // Check image file type
                        $wp_filetype = wp_check_filetype($filename, null);

                        // Set attachment data
                        $attachment = array(
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title' => sanitize_file_name($filename),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );

                        //TODO variation images support

                        // Create the attachment
                        $attach_id = wp_insert_attachment($attachment, $file_base_path, $product_id);

                        // Include image.php
                        require_once(ABSPATH . 'wp-admin/includes/image.php');

                        // Define attachment metadata
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_base_path);

                        // Assign metadata to attachment
                        wp_update_attachment_metadata($attach_id, $attach_data);


                        // And finally assign featured image to post
                        if ($index == 0) {
                            set_post_thumbnail($product_id, $attach_id);
                            //create field to confirm already imported images
                            update_post_meta($product_id, '_external_images_imported', true);
                        }
                        if ($index > 0) {
                            array_push($gallery_ids, $attach_id );
                        }

                        /*
                         * Include in WP Media Folder Structure
                         */

                        add_to_wp_media_folder($product_id, $folder_name, $attach_id, $index);

                    }

                }
                unlink($image_internal_path);
            }

            $image_run_index++;
        }
        if ($multi_image_import == true){
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }


        return;
    }

    /*
     * Support Functions
     */

    /*
     * Call ImageOptim to reduce the load of the images
     */
    function imageoptim_api_request($image_url, $file_base_path){

        $logger = wc_get_logger();

        $sourceImageUrl = $image_url;
        $options = 'full';

        //echo 'Image_OPTIM:' . $sourceImageUrl . '<br>';
        // echo getimagesize($sourceImageUrl)[3]. '<br>';

        $postContext = stream_context_create([
            'http' => [
                'method' => 'POST',
            ],
        ]);


        $imageData = file_get_contents(
            'https://im2.io/REDACTED/' . $options . '/' . $sourceImageUrl,
            false, $postContext);

        file_put_contents($file_base_path, $imageData);

    }

    /*
     * fetches the single urls which were placed in the meta of the product and returns them in an array
     */
    function get_external_image_sources($product_id){

        $external_image_urls = [];

        if (get_post_meta($product_id, 'main_external_image_field', true)) {

            $main_external_image_field = get_post_meta($product_id, 'main_external_image_field', true);
            $main_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $main_external_image_field;
            array_push($external_image_urls, $main_external_image_field_url);

        }
        if (get_post_meta($product_id, 'first_external_image_field', true)) {

            $first_external_image_field = get_post_meta($product_id, 'first_external_image_field', true);
            $first_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $first_external_image_field;
            array_push($external_image_urls, $first_external_image_field_url);

        }
        if (get_post_meta($product_id, 'second_external_image_field', true)) {

            $second_external_image_field = get_post_meta($product_id, 'second_external_image_field', true);
            $second_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $second_external_image_field;
            array_push($external_image_urls, $second_external_image_field_url);

        }
        if (get_post_meta($product_id, 'third_external_image_field', true)) {

            $third_external_image_field = get_post_meta($product_id, 'third_external_image_field', true);
            $third_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $third_external_image_field;
            array_push($external_image_urls, $third_external_image_field_url);

        }
        if (get_post_meta($product_id, 'fourth_external_image_field', true)) {

            $fourth_external_image_field = get_post_meta($product_id, 'fourth_external_image_field', true);
            $fourth_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $fourth_external_image_field;
            array_push($external_image_urls, $fourth_external_image_field_url);

        }
        if (get_post_meta($product_id, 'fifth_external_image_field', true)) {

            $fifth_external_image_field = get_post_meta($product_id, 'fifth_external_image_field', true);
            $fifth_external_image_field_url = 'https://LINKTOVENDOR/cdn/image/' . $fifth_external_image_field;
            array_push($external_image_urls, $fifth_external_image_field_url);

        }

        return $external_image_urls;

    }

    /*
     * Add support for the WP Media Folder Structure
     */
    function add_to_wp_media_folder($product_id, $folder_name, $attach_id, $index){

        //get the category pf the product to later match wp media folder
        $term_list = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        /*
         * lets match the ids to their corresponding WP-MEDIA FOLDER
         *
         * Media Folder IDs:         Category Ids
         * Aufbewahrung     176             18
         * Badezimmer       421            432
         * Heimtextilien    174             17
         * Küche und Tisch  175             16
         *
         */
        $parent_term_id = ''; //no parent to be found; Just use root

        if (in_array(18, $term_list)) {
            $parent_term_id = 176; //Aufbewahrung
        }
        if (in_array(432, $term_list)) {
            $parent_term_id = 421; // Badezimmer
        }
        if (in_array(17, $term_list)) {
            $parent_term_id = 174; //Heimtextilien
        }
        if (in_array(16, $term_list)) {
            $parent_term_id = 175; // Küche und Tisch
        }

        /*
         * Create the Pseudo Folder if not already existing
         */
        $new_term = wp_insert_term(
            $folder_name,
            'wpmf-category',
            array(
                'parent' => $parent_term_id,
            )
        );

        if (!is_wp_error($new_term)) {
            $term_id = isset($new_term['term_id']) ? $new_term['term_id'] : 0;

        } else {

            /*
             * if term/folder already exists use this existing folder
             */
            $existing_term = get_term_by('name', $folder_name, 'wpmf-category');
            $term_id = $existing_term->term_id;

        }

        wp_set_object_terms($attach_id, intval($term_id), 'wpmf-category');

        if ($index == 0) {

            /*
             * set the cover image in wp media folder
             */
            $cover_images = get_option('wpmf_field_bgfolder');

            /*
             * Define array if not yet any cover image defined
             */
            if (empty($cover_images)) {
                $cover_images = array();
            }
            $params = array(
                (int)$attach_id,
                wp_get_attachment_thumb_url($attach_id)
            );
            $cover_images[$term_id] = $params;
            update_option('wpmf_field_bgfolder', $cover_images);
        }

    }

    /*
     * Connect to EK Media FTP Server to fetch the Absolute url of image
     */

    function connect_to_ftp_server($url_of_EK_file, $image_run_index, $product_slug ){
        $host= 'REDACTED';
        $user = 'REDACTED';
        $password = 'REDACTED';

        $temp_file_name = 'imported_' .$product_slug . '_' . $image_run_index . '.jpg';

        $logger = wc_get_logger();


        //ONly supports jpg at the moment. One would need to check the mimetype on ek side before downlaoding and change the paths accordingly

        $fileUrl = $url_of_EK_file;
        $upload_dir = wp_upload_dir();
        $tempFilePath = $upload_dir['basedir'].'/' . date("Y") .'/'. date('m') .'/'. $temp_file_name;
        $tempFileURL = $upload_dir['baseurl'] .'/' . date("Y") .'/'. date('m') .'/'. $temp_file_name;

        $downloadFileInstance = new \BellendoEK\Helper\CurlLoginAndDownloadFile($user, $password);
        $downloadFileInstance->downloadImage($fileUrl, $tempFilePath);



        // echo "Import from external source successfull ' . $temp_file_name .'<br>";
        $path_and_url = [];
        array_push($path_and_url, $tempFilePath);
        array_push($path_and_url, $tempFileURL);

        return $path_and_url;
    }

    /*
     * display the image fields on product page
     */

    add_action('woocommerce_product_options_advanced', 'show_image_links_in_product_backend');

    function show_image_links_in_product_backend(){

        $product_id = get_the_id();

        if (get_post_meta($product_id, 'main_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Hauptbild (Extern): ' .get_post_meta($product_id, 'main_external_image_field', true) . '</div><br>';

        }
        if (get_post_meta($product_id, 'first_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Bild 1 (Extern): ' .get_post_meta($product_id, 'first_external_image_field', true) . '</div><br>';

        }
        if (get_post_meta($product_id, 'second_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Bild 2 (Extern): ' .get_post_meta($product_id, 'second_external_image_field', true) . '</div><br>';

        }
        if (get_post_meta($product_id, 'third_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Bild 3 (Extern): ' .get_post_meta($product_id, 'third_external_image_field', true) . '</div><br>';

        }
        if (get_post_meta($product_id, 'fourth_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Bild 4 (Extern): ' .get_post_meta($product_id, 'fourth_external_image_field', true) . '</div><br>';

        }
        if (get_post_meta($product_id, 'fifth_external_image_field', true)) {

            echo '<div style="margin-left:15px; padding-top:5px">Bild 5 (Extern): ' .get_post_meta($product_id, 'fifth_external_image_field', true) . '</div><br>';

        }

    }


    ?>
