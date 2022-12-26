<?php
/**
 * Common function file
 *
 * @author   Magazine3
 * @category Admin
 * @path     admin_section/common-function
 * @version 1.1
 */

// Exit if accessed directly
if ( ! defined('ABSPATH') ) exit;
              
    /**
     * List of hooks used in this context
     */
    add_action( 'admin_init', 'saswp_import_all_settings_and_schema',9);
    add_action( 'wp_ajax_saswp_export_all_settings_and_schema', 'saswp_export_all_settings_and_schema'); 
    add_action( 'wp_ajax_saswp_download_csv_review_format', 'saswp_download_csv_review_format');  
    add_action( 'plugins_loaded', 'saswp_defaultSettings' );
    add_action( 'wp_enqueue_scripts', 'saswp_frontend_enqueue' );
    add_action( 'amp_post_template_css','saswp_enqueue_amp_script');
        
      /**
       * Function to get manual translated text 
       * @global array $translation_labels
       * @global type $sd_data
       * @param type $label_key
       * @return string
       */    
     function saswp_label_text($label_key){
         
         global $translation_labels;
         global $sd_data;
         
         if(isset($sd_data[$label_key]) && $sd_data[$label_key] !=''){
             return $sd_data[$label_key];
         }else{
             return $translation_labels[$label_key];
         }
                                    
     }     
    
    /**
     * We are here fetching all schema and its settings from backup files
     * note: Transaction is applied on this function, if any error occure all the data will be rollbacked
     * @global type $wpdb
     * @return boolean
     */        
    function saswp_import_all_settings_and_schema(){
                        
        if ( ! current_user_can( saswp_current_user_can() ) ) {
             return;
        }
        
        global $wpdb;
        
        $result          = null;
        $errorDesc       = array();
        $all_schema_post = array();
        
        $url = get_option('saswp-file-upload_url');                        
        
        if($url){
            
        $json_data       = @file_get_contents($url);
        
        if($json_data){
            
            $json_array      = json_decode($json_data, true);   
        
            $posts_data      = $json_array['posts'];                   
                        
            if($posts_data){  
                
            foreach($posts_data as $data){
                    
            $all_schema_post = $data;                   
                                
            $schema_post = array();                     
               
            if($all_schema_post && is_array($all_schema_post)){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            
            foreach($all_schema_post as $schema_post){  
                              
                $post_meta =     $schema_post['post_meta'];   
                
                if(saswp_post_exists($schema_post['post']['ID'])){
                    
                    $post_id    =     wp_update_post($schema_post['post']);  
                     
                }else{
                    
                    unset($schema_post['post']['ID']);
                    
                    $post_id    =     wp_insert_post($schema_post['post']); 
                    
                    if($post_meta){
                        
                        foreach($post_meta as $key => $val){

                          $explod_key = explode("_",$key);

                          $exp_count  = count($explod_key);

                          $explod_key[($exp_count-1)] = $post_id;

                          $explod_key = implode("_", $explod_key);

                          $post_meta[$explod_key] = $val;

                      }  
                        
                    }
                                        
                }
                                                                                          
                foreach($post_meta as $key => $meta){
                    
                    $meta = wp_unslash($meta);
                    
                    if(is_array($meta)){    
                        
                        $meta = wp_unslash($meta);
                        update_post_meta($post_id, $key, $meta);
                        
                    }else{
                        update_post_meta($post_id, $key, sanitize_text_field($meta));
                    }
                                                            
                }
                                                                                                                    
                if(is_wp_error($post_id)){
                    $errorDesc[] = $result->get_error_message();
                }
                } 
                
                }      
                                        
               }
                
            }            
            //Saving settings data starts here
            if(array_key_exists('sd_data', $json_array)){
                
                $saswp_sd_data = $json_array['sd_data'];
                
                foreach($saswp_sd_data as $key => $val){
                    
                    if(is_array($val)){
                        
                        $saswp_sd_data[$key] = $meta = array_map( 'sanitize_text_field' ,$val);   
                        
                    }else{
                        
                        $saswp_sd_data[$key] = sanitize_text_field($val);
                        
                    }
                    
                }
                
                update_option('sd_data', $saswp_sd_data); 
            } 
            //Saving settings data ends here 
             saswp_published();            
             update_option('saswp-file-upload_url','');
            
        }
                                     
        if ( count($errorDesc) ){
          echo implode("\n<br/>", $errorDesc);              
          $wpdb->query('ROLLBACK');             
        }else{
          $wpdb->query('COMMIT'); 
          return true;
        }
             
       }                                    
                                                             
    }   

    function saswp_download_csv_review_format(){

        if ( ! current_user_can( saswp_current_user_can() ) ) {
            return;
        }
        if ( ! isset( $_GET['_wpnonce'] ) ){
                return; 
        }

        if ( !wp_verify_nonce( $_GET['_wpnonce'], '_wpnonce' ) ){
                return;  
        }
                                                                   
       $data = "Author, Author Url, Author Image, Date, Time, Rating, Title, Text, Platform, Language, Source Url/ Place ID";

       header('Content-Type: text/csv; charset=utf-8');
       header('Content-disposition: attachment; filename=reviewscsv.csv');
       echo $data;   
                                     
       wp_die();


    }
    /**
     * We are here exporting all schema types and its settings as a backup file     
     * @global type $wpdb
     * @return boolean
     */
    function saswp_export_all_settings_and_schema(){   
        
                if ( ! current_user_can( saswp_current_user_can() ) ) {
                     return;
                }
                if ( ! isset( $_GET['_wpnonce'] ) ){
                     return; 
                }

                if ( !wp_verify_nonce( $_GET['_wpnonce'], '_wpnonce' ) ){
                     return;  
                }
        
                $post_type = array('saswp_reviews', 'saswp', 'saswp-collections');
                $export_data_all   = array(); 
                
                foreach($post_type as $type){
                    
                    $export_data       = array();                

                    $all_schema_post = get_posts(

                        array(
                                'post_type' 	     => $type,                                                                                   
                                'posts_per_page'     => -1,   
                                'post_status'        => 'any',
                        )

                     );                        

                    if($all_schema_post){
                    
                        foreach($all_schema_post as $schema){    

                        $export_data[$schema->ID]['post']      = (array)$schema;                    
                        $post_meta                             = get_post_meta($schema->ID);    

                        if($post_meta){

                            foreach ($post_meta as $key => $meta){

                                if(@unserialize($meta[0]) !== false){
                                    $post_meta[$key] = @unserialize($meta[0]);
                                }else{
                                    $post_meta[$key] = $meta[0];
                                }

                            }

                        }

                        $export_data[$schema->ID]['post_meta'] = $post_meta;  

                        }       

                      $export_data_all['posts'][$type] = $export_data;    
                        
                    }
                                        
                    
                }
                
                $export_data_all['sd_data']         = get_option('sd_data');
                
                header('Content-type: application/json');
                header('Content-disposition: attachment; filename=structuredatabackup.json');
                echo json_encode($export_data_all);   
                                              
                wp_die();
    }    
    /**
     * We are here fetching all schema and its settings from schema plugin
     * note: Transaction is applied on this function, if any error occure all the data will be rollbacked
     * @global type $wpdb
     * @return boolean
     */
    function saswp_import_schema_plugin_data(){           
                                                    
        $schema_post = array();
        $errorDesc   = array();
        global $wpdb;
        $user_id     = get_current_user_id();
        
        $all_schema_post = get_posts(
                    array(
                            'post_type' 	 => 'schema',                                                                                   
                            'posts_per_page'     => -1,   
                            'post_status'        => 'any',
                    )
                 );         
        
        if($all_schema_post){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            
            foreach($all_schema_post as $schema){    
                
                $schema_post = array(
                    
                    'post_author'           => $user_id,
                    'post_date'             => $schema->post_date,
                    'post_date_gmt'         => $schema->post_date_gmt,
                    'post_content'          => $schema->post_content,
                    'post_title'            => $schema->post_title. ' (Migrated from Schema plugin)',
                    'post_excerpt'          => $schema->post_excerpt,
                    'post_status'           => $schema->post_status,
                    'comment_status'        => $schema->comment_status,
                    'ping_status'           => $schema->ping_status,
                    'post_password'         => $schema->post_password,
                    'post_name'             => $schema->post_name,
                    'to_ping'               => $schema->to_ping,
                    'pinged'                => $schema->pinged,
                    'post_modified'         => $schema->post_modified,
                    'post_modified_gmt'     => $schema->post_modified_gmt,
                    'post_content_filtered' => $schema->post_content_filtered,
                    'post_parent'           => $schema->post_parent,                                        
                    'menu_order'            => $schema->menu_order,
                    'post_type'             => 'saswp',
                    'post_mime_type'        => $schema->post_mime_type,
                    'comment_count'         => $schema->comment_count,
                    'filter'                => $schema->filter, 
                    
                );    
                
                $post_id = wp_insert_post($schema_post);
                $result  = $post_id;
                $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");   
                
                $schema_post_meta       = get_post_meta($schema->ID); 
                $schema_post_types      = get_post_meta($schema->ID, $key='_schema_post_types', true );                                  
                
                $data_group_array = array();
                
                if($schema_post_types){
                                        
                    $i=0;
                    foreach ($schema_post_types as $post_type){
                       
                       $data_group_array['group-'.$i] =array(
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => $post_type,
                            )
                          )               
                         );                                               
                    $i++;  
                    
                    }                                        
                }                                
                
                $schema_article_type ='';                                                

                if(isset($schema_post_meta['_schema_article_type'])){
                  $schema_article_type = $schema_post_meta['_schema_article_type'][0];  
                }                      
                $saswp_meta_key = array(
                    'schema_type'      => $schema_article_type,
                    'data_group_array' => $data_group_array,
                    'imported_from'    => 'schema'
                );
                
                foreach ($saswp_meta_key as $key => $val){                     
                    update_post_meta($post_id, $key, $val);  
                }                                                        
                if(is_wp_error($result)){
                    $errorDesc[] = $result->get_error_message();
                }
              }          
                            
              //Importing settings starts here
                            
                $schema_plugin_options = get_option('schema_wp_settings');                                      
                $custom_logo_id        = get_theme_mod( 'custom_logo' );                
                                
                $saswp_plugin_options = array(                    
                    'sd_logo'   => array(
                                        'url'           => $schema_plugin_options['logo'],  
                                        'id'            => $custom_logo_id,
                                        'height'        => '600',
                                        'width'         => '60',
                                        'thumbnail'     => $schema_plugin_options['logo']        
                            ),                                                                                                                                                             
                    'saswp_kb_contact_1'       => 0,                                                                            
                    //AMP Block           
                    'saswp-for-amp'            => 1, 
                    'saswp-for-wordpress'      => 1,      
                    'saswp-logo-width'         => '60',
                    'saswp-logo-height'        => '60',                    
                    'sd_initial_wizard_status' => 1,
                                        
                );                
                if(isset($schema_plugin_options['facebook'])){
                  $saswp_plugin_options['sd_facebook'] =  $schema_plugin_options['facebook']; 
                  $saswp_plugin_options['saswp-facebook-enable'] =  1; 
                }
                if(isset($schema_plugin_options['twitter'])){
                  $saswp_plugin_options['sd_twitter'] =  $schema_plugin_options['twitter']; 
                  $saswp_plugin_options['saswp-twitter-enable'] =  1;
                }
                if(isset($schema_plugin_options['google'])){
                  $saswp_plugin_options['sd_google_plus'] =  $schema_plugin_options['google']; 
                  $saswp_plugin_options['saswp-google-plus-enable'] =  1;
                }
                if(isset($schema_plugin_options['instagram'])){
                  $saswp_plugin_options['sd_instagram'] =  $schema_plugin_options['instagram']; 
                  $saswp_plugin_options['saswp-instagram-enable'] =  1;
                }
                if(isset($schema_plugin_options['youtube'])){
                  $saswp_plugin_options['sd_youtube'] =  $schema_plugin_options['youtube']; 
                  $saswp_plugin_options['saswp-youtube-enable'] =  1;
                }
                if(isset($schema_plugin_options['linkedin'])){
                  $saswp_plugin_options['sd_linkedin'] =  $schema_plugin_options['linkedin']; 
                  $saswp_plugin_options['saswp-linkedin-enable'] =  1;
                }
                if(isset($schema_plugin_options['pinterest'])){
                  $saswp_plugin_options['sd_pinterest'] =  $schema_plugin_options['pinterest']; 
                  $saswp_plugin_options['saswp-pinterest-enable'] =  1;
                }
                if(isset($schema_plugin_options['soundcloud'])){
                  $saswp_plugin_options['sd_soundcloud'] =  $schema_plugin_options['soundcloud']; 
                  $saswp_plugin_options['saswp-soundcloud-enable'] =  1;
                }
                if(isset($schema_plugin_options['tumblr'])){
                  $saswp_plugin_options['sd_tumblr'] =  $schema_plugin_options['tumblr']; 
                  $saswp_plugin_options['saswp-tumblr-enable'] =  1;
                }                
                if(isset($schema_plugin_options['organization_or_person'])){
                                                           
                  $saswp_plugin_options['saswp_kb_type'] = ucfirst($schema_plugin_options['organization_or_person']);  
                  $saswp_plugin_options['sd_name'] = $schema_plugin_options['name'];
                  $saswp_plugin_options['sd-person-name'] = $schema_plugin_options['name'];
                }                
                if(isset($schema_plugin_options['about_page'])){
                  $saswp_plugin_options['sd_about_page'] = $schema_plugin_options['about_page'];  
                }
                if(isset($schema_plugin_options['contact_page'])){
                  $saswp_plugin_options['sd_contact_page'] = $schema_plugin_options['contact_page'];  
                }
                if(isset($schema_plugin_options['site_name'])){
                   
                }
                if(isset($schema_plugin_options['site_alternate_name'])){
                  $saswp_plugin_options['sd_alt_name'] = $schema_plugin_options['site_alternate_name'];  
                }
                if(isset($schema_plugin_options['url'])){
                  $saswp_plugin_options['sd_url'] = $schema_plugin_options['url'];  
                  $saswp_plugin_options['sd-person-url'] = $schema_plugin_options['url'];  
                }
                if(isset($schema_plugin_options['name'])){
                  $saswp_plugin_options['sd-person-name'] = $schema_plugin_options['name'];  
                }
                if(isset($schema_plugin_options['corporate_contacts_telephone'])){
                  $saswp_plugin_options['saswp_kb_telephone'] = $schema_plugin_options['corporate_contacts_telephone'];  
                }
                if(isset($schema_plugin_options['corporate_contacts_contact_type'])){
                  $saswp_plugin_options['saswp_contact_type'] = $schema_plugin_options['corporate_contacts_contact_type'];  
                }                
                if(isset($schema_plugin_options['breadcrumbs_enable'])){
                  $saswp_plugin_options['saswp_breadcrumb_schema'] = $schema_plugin_options['breadcrumbs_enable'];  
                }                
                update_option('sd_data', $saswp_plugin_options);
                //Importing settings ends here
              
            if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc); 
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }            
        }
                             
    }
    
    function saswp_import_schema_for_faqs_plugin_data(){

      global $wpdb;
                                   
      $wpdb->query('START TRANSACTION');
      $errorDesc = array(); 
        
      $post_ids = saswp_get_post_ids('post');

      if($post_ids){

        $result    = saswp_insert_schema_type('schema for faqs');
        $schema_id = intval($result);

        if($schema_id){
            
            foreach ($post_ids as $id) {
            
                $schema_for_faqs = get_post_meta($id, 'schema_faqs_ques_ans_data', true);
    
                if($schema_for_faqs){
    
                    $data_arr = json_decode($schema_for_faqs, true);
    
                    if($data_arr && is_array($data_arr)){
    
                        $saswp_faq = array();
    
                        foreach ($data_arr as $value) {
    
                            if(isset($value['question'])){
    
                                $saswp_faq[] =  array(
                                    'saswp_faq_question_name'   => sanitize_text_field($value['question']),
                                    'saswp_faq_question_answer' => saswp_sanitize_textarea_field($value['answer']),
        
                                );
    
                            }                        
                        }
                        //array is sanitize above
                        update_post_meta($id, 'faq_question_'.$schema_id, $saswp_faq);
                        update_post_meta($id, 'saswp_modify_this_schema_'.$schema_id, 1); 
    
                    }else{
                        $schema_enable = array();
                        $schema_enable[$schema_id] = 0;                                   
                        update_post_meta($id, 'saswp_enable_disable_schema', $schema_enable);  
                    }
                }
    
            }

        }        

      }                      
      
      if ( count($errorDesc) ){
        echo implode("\n<br/>", $errorDesc);           
        $wpdb->query('ROLLBACK');             
      }else{
        $wpdb->query('COMMIT'); 
        return true;
      }
                     
    } 
    function saswp_import_wp_custom_rv_plugin_data(){
        
           global $wpdb;
                                   
            $wpdb->query('START TRANSACTION');
            $errorDesc = array();            
                                                            
            $wpcr3reviews = get_posts(                
                    array(
                            'post_type' 	 => 'wpcr3_review',                                                                                   
                            'posts_per_page'     => -1,   
                            'post_status'        => 'any',
                    )                
                 ); 
            
            if($wpcr3reviews){
                           
                foreach($wpcr3reviews as $new_post){
                    
                    $review_post = (array)$new_post;                   
                    $wp_post_id  = $review_post['ID'];
                    $wp_rv_time  = get_post_time('h:i:s',false,$new_post);
                    $wp_rv_date  = get_the_date('Y-m-d',$new_post);                      
                    unset($review_post['ID']);
                    $review_post['post_type'] = 'saswp_reviews';                    
                    $post_id = wp_insert_post($review_post);
                                        
                    $wp_post_meta = get_post_meta($wp_post_id, '', true);
                                 
                    $term     = get_term_by( 'slug','google', 'platform' );
                    
                    $media_detail = array(                                                    
                        'width'      => 300,
                        'height'     => 300,
                        'thumbnail'  => SASWP_DIR_URI.'/admin_section/images/default_user.jpg',
                    );
                    
                    $review_meta = array(
                        'saswp_review_platform'       => $term->term_id,
                        'saswp_review_location_id'    => $wp_post_meta['wpcr3_review_post'][0],                        
                        'saswp_review_date'           => $wp_rv_date,
                        'saswp_review_time'           => $wp_rv_time,
                        'saswp_review_rating'         => $wp_post_meta['wpcr3_review_rating'][0],
                        'saswp_review_text'           => $review_post['post_content'],                                                        
                        'saswp_reviewer_name'         => $wp_post_meta['wpcr3_review_name'][0],
                        'saswp_reviewer_email'        => $wp_post_meta['wpcr3_review_email'][0],
                        'saswp_reviewer_website'      => $wp_post_meta['wpcr3_review_website'][0],
                        'saswp_review_link'           => get_permalink($wp_post_meta['wpcr3_review_post'][0]),
                        'saswp_reviewer_image'        => SASWP_DIR_URI.'/admin_section/images/default_user.jpg',
                        'saswp_reviewer_image_detail' => $media_detail
                    );

                    if($post_id && !empty($review_meta) && is_array($review_meta)){

                        foreach ($review_meta as $key => $val){                     
                            update_post_meta($post_id, $key, $val);  
                        }

                    }
                    
                }
                                
            }
                                 
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);           
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }                        
        
    }
    
    function saswp_import_aiors_plugin_data(){
        
                    global $wpdb;
        
                    $schema_types = array('Event', 'Person', 'Product', 'Recipe', 'Article', 'Service', 'VideoObject', 'SoftwareApplication');
                                       
                    $args_event   = get_option('bsf_event');
                    $args_person  = get_option('bsf_person');
                    $args_product = get_option('bsf_product');
                    $args_recipe  = get_option('bsf_recipe');
                    $args_soft    = get_option('bsf_software');	
                    $args_video   = get_option('bsf_video');	
                    $args_article = get_option('bsf_article');
                    $args_service = get_option('bsf_service');
                                        
                    $wpdb->query('START TRANSACTION');
                    $errorDesc = array();            
                                                            
                    foreach($schema_types as $schema){
                        
                        $schema_post = array(
                                'post_title'  => $schema,                                                            
                                'post_status' => 'publish',                    
                                'post_type'   => 'saswp',                    
                        ); 

                        $data_group_array = array();   

                        $data_group_array['group-0'] = array(                            
                                                'data_array' => array(
                                                            array(
                                                            'key_1' => 'post_type',
                                                            'key_2' => 'equal',
                                                            'key_3' => 'post',
                                                  )
                                                )               
                                               );                                        

                        $saswp_meta_key = array(
                            'schema_type'                  => $schema,
                            'data_group_array'             => $data_group_array,
                            'imported_from'                => 'aiors',                                                    
                         );    
                        
                        $post_id = wp_insert_post($schema_post);                    
                        $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                        $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");

                        foreach ($saswp_meta_key as $key => $val){                     
                            update_post_meta($post_id, $key, $val);  
                        }  
                        
                         $schema_options = array();
                         $meta_list = saswp_migrate_global_static_data($schema);                          
                         $schema_options['enable_custom_field'] = 1;                         
                         $fixed_text = array();
                         
                         switch ($schema) {
                             
                             case 'Event':
                                 
                                 $fixed_text['saswp_event_schema_name']          = $args_event["event_title"];                                 
                                 $fixed_text['saswp_event_schema_location_name'] = $args_event["event_location"];                                                                  
                                 $fixed_text['saswp_event_schema_start_date']    = $args_event["start_time"];
                                 $fixed_text['saswp_event_schema_end_date']      = $args_event["end_time"];                                                                  
                                 $fixed_text['saswp_event_schema_price']         = $args_event["events_price"];
                                 $fixed_text['saswp_event_schema_description']   = $args_event["event_desc"];
                                                                                                                                    
                                 break;                             
                             case 'Person':
                                 
                                 $fixed_text['saswp_person_schema_name']           = $args_person["person_name"];                                                                  
                                 $fixed_text['saswp_person_schema_street_address'] = $args_person["person_address"];                                 
                                 $fixed_text['saswp_person_schema_job_title']      = $args_person["person_job_title"];
                                 $fixed_text['saswp_person_schema_company']        = $args_person["person_company"];
                                 $fixed_text['saswp_person_schema_website']        = $args_person["person_website"];
                                 
                                 break;
                             case 'Product':
                                                                  
                                 $fixed_text['saswp_product_name'] = $args_product["product_name"];                                                                  
                                 $fixed_text['saswp_product_brand'] = $args_product["product_brand"];
                                 $fixed_text['saswp_product_price'] = $args_product["product_price"];                                                                  
                                 $fixed_text['saswp_product_availability'] = $args_product["product_avail"];
                                                                                                   
                                 break;
                             case 'Recipe':
                                 
                                 $fixed_text['saswp_recipe_name']           = $args_recipe["recipe_name"];
                                 $fixed_text['saswp_recipe_author_name']    = $args_recipe["author_name"];
                                 $fixed_text['saswp_recipe_date_published'] = $args_recipe["recipe_pub"];
                                 $fixed_text['saswp_recipe_preptime']       = $args_recipe["recipe_prep"];
                                 $fixed_text['saswp_recipe_cooktime']       = $args_recipe["recipe_cook"];
                                 $fixed_text['saswp_recipe_totaltime']      = $args_recipe["recipe_time"];
                                 $fixed_text['saswp_recipe_description']    = $args_recipe["recipe_desc"];
                                                                                                   
                                 break;
                             case 'Article':
                                                                  
                                 $fixed_text['saswp_article_image']                = $args_article["article_name"];
                                 $fixed_text['saswp_article_headline']             = $args_article["snippet_title"];                                                                  
                                 $fixed_text['saswp_article_description']          = $args_article["article_desc"];
                                 $fixed_text['saswp_article_author_name']          = $args_article["article_author"];
                                 $fixed_text['saswp_article_organization_name']    = $args_article["article_publisher"];
                                 $fixed_text['saswp_article_organization_logo']    = $args_article["article_publisher_logo"];
                                 
                                 break;
                             case 'Service':
                                 
                                 $fixed_text['saswp_service_schema_name']          = $args_service["snippet_title"];
                                 $fixed_text['saswp_service_schema_type']          = $args_service["service_type"];                                                                  
                                 $fixed_text['saswp_service_schema_provider_name'] = $args_service["service_provider_name"];                                 
                                 $fixed_text['saswp_service_schema_area_served']   = $args_service["service_area"];
                                 $fixed_text['saswp_service_schema_description']   = $args_service["service_desc"];                                
                                 $fixed_text['saswp_service_schema_url']           = $args_service["service_url_link"];
                                 
                                 break;
                             case 'VideoObject':
                                 
                                 $fixed_text['saswp_video_object_headline']          = $args_video["video_title"];
                                 $fixed_text['saswp_video_object_description']       = $args_video["video_desc"];                                                                  
                                 $fixed_text['saswp_video_object_upload_date']       = $args_video["video_date"];
                                 $fixed_text['saswp_video_object_description']       = $args_video["video_desc"];
                                 $fixed_text['saswp_video_object_duration']          = $args_video["video_time"];                                 
                                 
                                 break;
                             case 'SoftwareApplication':
                                 
                                 $fixed_text['saswp_software_schema_name']             = $args_soft["software_name"];                                                                          
                                 $fixed_text['saswp_software_schema_operating_system'] = $args_soft["software_os"];                                 
                                 $fixed_text['saswp_software_schema_price']            = $args_soft["software_price"];                                                                      
                                 break;                             
                             default:
                                 break;
                         }
                         
                         update_post_meta( $post_id, 'schema_options', $schema_options);                 
                         update_post_meta( $post_id, 'saswp_meta_list_val', $meta_list);
                         update_post_meta( $post_id, 'saswp_fixed_text', $fixed_text);   
                        
                    }                                    
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);           
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }                        
        
    }
    
    function saswp_import_wpsso_core_plugin_data(){
        
         global $wpdb;
                          
         $wpsso_option = get_option('wpsso_options');
         
         $saswp_option = array();
        
         if(isset($wpsso_option['schema_home_person_id'])){
             $user_info = get_userdata($wpsso_option['schema_home_person_id']);
             $saswp_option['sd-person-name']       = $user_info->user_login;
         }
         $saswp_option['sd_name']              =  $wpsso_option['site_name'];
         $saswp_option['sd_logo']['url']       = $wpsso_option['schema_logo_url'];
         $saswp_option['saswp_website_schema'] = $wpsso_option['schema_add_home_website'];                  
         
         if(isset($wpsso_option['fb_publisher_url'])){
             $saswp_option['saswp-facebook-enable'] = 1;
             $saswp_option['sd_facebook']   = $wpsso_option['fb_publisher_url'];
         }
         if(isset($wpsso_option['instgram_publisher_url'])){
             $saswp_option['saswp-instagram-enable'] = 1;
             $saswp_option['sd_instagram']  = $wpsso_option['instgram_publisher_url'];
         }
         if(isset($wpsso_option['linkedin_publisher_url'])){
             $saswp_option['saswp-linkedin-enable'] = 1;
             $saswp_option['sd_linkedin']   = $wpsso_option['linkedin_publisher_url'];
         }         
         if(isset($wpsso_option['p_publisher_url'])){
             $saswp_option['saswp-pinterest-enable'] = 1;
             $saswp_option['sd_pinterest']  = $wpsso_option['p_publisher_url'];
         }
         if(isset($wpsso_option['sc_publisher_url'])){
             $saswp_option['saswp-soundcloud-enable'] = 1;
             $saswp_option['sd_soundcloud'] = $wpsso_option['sc_publisher_url'];
         }
         if(isset($wpsso_option['tumblr_publisher_url'])){
             $saswp_option['saswp-tumblr-enable'] = 1;
             $saswp_option['sd_tumblr']     = $wpsso_option['tumblr_publisher_url'];
         }
         if(isset($wpsso_option['tc_site'])){
             $saswp_option['saswp-twitter-enable'] = 1;
             $saswp_option['sd_twitter']    = $wpsso_option['tc_site'];
         }
         if(isset($wpsso_option['yt_publisher_url'])){
             $saswp_option['saswp-youtube-enable'] = 1;
             $saswp_option['sd_youtube']    = $wpsso_option['yt_publisher_url']; 
         }
                   
        $schema_post = array(
                'post_title' => $wpsso_option['schema_type_for_home_index'],                                                            
                'post_status' => 'publish',                    
                'post_type'   => 'saswp',                    
        ); 

        $data_group_array = array();   

        $data_group_array['group-0'] =array(
                                'data_array' => array(
                                            array(
                                            'key_1' => 'post_type',
                                            'key_2' => 'equal',
                                            'key_3' => 'post',
                                  )
                                )               
                               );                                        

        $saswp_meta_key = array(
            'schema_type'                  => $wpsso_option['schema_type_for_home_index'],
            'data_group_array'             => $data_group_array,
            'imported_from'                => 'wpsso_core',                                                    
         );
         
         if(isset($saswp_option)){ 
                       
                $wpdb->query('START TRANSACTION');
                $errorDesc = array();
                                                                                                                                                           
                $get_options   = get_option('sd_data');
                $merge_options = array_merge($get_options, $saswp_option);
                update_option('sd_data', $merge_options);
                
                    $post_id = wp_insert_post($schema_post);                    
                    $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");
                                                         
                    foreach ($saswp_meta_key as $key => $val){                     
                        update_post_meta($post_id, $key, $val);  
                    }
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);           
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }               
         }
        
    }
    function saswp_import_seo_pressor_plugin_data(){
         
        global $wpdb;
        $social_fields = array();
        $opening_hours = '';
        $settings = WPPostsRateKeys_Settings::get_options();
        
        if(isset($settings['seop_home_social'])){
            
            foreach($settings['seop_home_social'] as $social){
               
                switch ($social['social_type']) {
                    
                    case 'Facebook':
                        
                        $social_fields['saswp-facebook-enable'] = 1;
                        $social_fields['sd_facebook'] = $social['social'];
                        
                        break;
                    case 'Twitter':
                        
                        $social_fields['saswp-twitter-enable'] = 1;
                        $social_fields['sd_twitter'] = $social['social'];
                        
                        break;                    
                    case 'Instagram':
                        $social_fields['saswp-instagram-enable'] = 1;
                        $social_fields['sd_instagram'] = $social['social'];
                        break;
                    case 'YouTube':
                        $social_fields['saswp-youtube-enable'] = 1;
                        $social_fields['sd_youtube'] = $social['social'];
                        break;
                    case 'LinkedIn':
                        $social_fields['saswp-linkedin-enable'] = 1;
                        $social_fields['sd_linkedin'] = $social['social'];
                        break;                    
                    case 'Pinterest':
                        $social_fields['saswp-pinterest-enable'] = 1;
                        $social_fields['sd_pinterest'] = $social['social'];
                        break;
                    case 'SoundCloud':
                        $social_fields['saswp-soundcloud-enable'] = 1;
                        $social_fields['sd_soundcloud'] = $social['social'];
                        break;
                    case 'Tumblr':
                        $social_fields['saswp-tumblr-enable'] = 1;
                        $social_fields['sd_tumblr'] = $social['social'];
                        break;

                    default:
                        break;
                }
                                                
            }         
        }
       
        if(isset($settings['seop_operating_hour'])){
            
           $hours = $settings['seop_operating_hour'];
           
           if(isset($hours['Mo'])){
             $opening_hours .='Mo-Mo'.' '.$hours['Mo']['from'].'-'.$hours['Mo']['to'].' '; 
           }
           if(isset($hours['Tu'])){
              $opening_hours .='Tu-Tu'.' '.$hours['Tu']['from'].'-'.$hours['Tu']['to'].' '; 
           }
           if(isset($hours['We'])){
              $opening_hours .='We-We'.' '.$hours['We']['from'].'-'.$hours['We']['to'].' '; 
           }
           if(isset($hours['Th'])){
              $opening_hours .='Th-Th'.' '.$hours['Th']['from'].'-'.$hours['Th']['to'].' '; 
           }
           if(isset($hours['Fr'])){
             $opening_hours .='Fr-Fr'.' '.$hours['Fr']['from'].'-'.$hours['Fr']['to'].' ';  
           }
           if(isset($hours['Sa'])){
             $opening_hours .='Sa-Sa'.' '.$hours['Sa']['from'].'-'.$hours['Sa']['to'].' '; 
           }
           if(isset($hours['Su'])){
             $opening_hours .='Su-Su'.' '.$hours['Su']['from'].'-'.$hours['Su']['to'];
           }
        } 
        
        
         if(isset($settings)){ 
             
          $local_business_details = array();          
          $wpdb->query('START TRANSACTION');
          $errorDesc = array();
          $user_id = get_current_user_id();
           
                    if($settings['seop_local_name'] !=''){ 
                        
                         $schema_post = array(
                            'post_author' => $user_id,                                                            
                            'post_status' => 'publish',                    
                            'post_type'   => 'saswp',                    
                        );   
                         
                    $schema_post['post_title'] = 'Organization (Migrated from SEO Pressor)';
                                      
                    if(isset($settings['seop_local_name'])){
                        
                     $schema_post['post_title'] = $settings['seop_local_name'].'(Migrated from WP SEO Plugin)'; 
                     
                    }
                    if(isset($settings['seop_home_logo'])){
                        
                       $image_details 	= wp_get_attachment_image_src($settings['seop_home_logo'], 'full');
              
                       $local_business_details['local_business_logo'] = array(
                                'url'           => $image_details[0],  
                                'id'            => $settings['site_image'],
                                'height'        => $image_details[1],
                                'width'         => $image_details[2],
                                'thumbnail'     => $image_details[0]        
                            ); 
                    }
                                                          
                    if(isset($settings['seop_local_website'])){
                      $local_business_details['local_website'] = $settings['seop_local_website'];  
                    }
                    
                    if(isset($settings['seop_local_city'])){
                        $local_business_details['local_city'] = $settings['seop_local_city'];
                    }
                    if(isset($settings['seop_local_state'])){
                        $local_business_details['local_state'] = $settings['seop_local_state'];
                    }
                    if(isset($settings['seop_local_postcode'])){
                        $local_business_details['local_postal_code'] = $settings['seop_local_postcode'];
                    }
                    if(isset($settings['seop_local_address'])){
                        $local_business_details['local_street_address'] = $settings['seop_local_address'];
                    }                                                                               
                    $post_id = wp_insert_post($schema_post);
                    $result  = $post_id;
                    $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");
                     
                    $data_group_array = array();   
                    
                    $data_group_array['group-0'] =array(
                                            'data_array' => array(
                                                        array(
                                                        'key_1' => 'post_type',
                                                        'key_2' => 'equal',
                                                        'key_3' => 'post',
                                              )
                                            )               
                                           );                                        
                    
                    $saswp_meta_key = array(
                        'schema_type'                  => 'local_business',
                        'data_group_array'             => $data_group_array,
                        'imported_from'                => 'wp_seo_schema',
                        'saswp_local_business_details' => $local_business_details,
                        'saswp_dayofweek'              => $opening_hours,        
                     );
                
                    foreach ($saswp_meta_key as $key => $val){                     
                        update_post_meta($post_id, $key, $val);  
                    }
                    if(is_wp_error($result)){
                        $errorDesc[] = $result->get_error_message();
                    }
                    }
                                                                                                            
                $get_options   = get_option('sd_data');
                $merge_options = array_merge($get_options, $social_fields);
                $result        = update_option('sd_data', $merge_options);
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);           
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }               
         }                        
    }
    
    function saswp_import_wp_seo_schema_plugin_data(){
        
         global $KcSeoWPSchema;
         global $wpdb;
         $settings = get_option($KcSeoWPSchema->options['settings']); 
         
         if(isset($settings)){
             
          $saswp_plugin_options   = array();   
          $local_business_details = array();          
          $wpdb->query('START TRANSACTION');
          $errorDesc = array();
          $user_id = get_current_user_id();
          
                    if($settings['site_type'] !='Organization'){
                        
                         $schema_post = array(
                            'post_author' => $user_id,                                                            
                            'post_status' => 'publish',                    
                            'post_type'   => 'saswp',                    
                        );                        
                    $schema_post['post_title'] = 'Organization (Migrated from WP SEO Plugin)';
                                      
                    if(isset($settings['type_name'])){
                     $schema_post['post_title'] = $settings['type_name'].'(Migrated from WP SEO Plugin)';    
                    }
                    if(isset($settings['site_image'])){
                       $image_details 	= wp_get_attachment_image_src($settings['site_image'], 'full');
              
                       $local_business_details['local_business_logo'] = array(
                                'url'           =>$image_details[0],  
                                'id'            =>$settings['site_image'],
                                'height'        =>$image_details[1],
                                'width'         =>$image_details[2],
                                'thumbnail'     =>$image_details[0]        
                            ); 
                    }
                    if(isset($settings['site_price_range'])){
                        $local_business_details['local_price_range'] = $settings['site_price_range']; 
                    }
                    if(isset($settings['site_telephone'])){
                        $local_business_details['local_phone'] = $settings['site_telephone'];
                    }                                        
                    if(isset($settings['web_url'])){
                      $local_business_details['local_website'] = $settings['web_url'];  
                    }
                    
                    if(isset($settings['address']['locality'])){
                        $local_business_details['local_city'] = $settings['site_telephone'];
                    }
                    if(isset($settings['address']['region'])){
                        $local_business_details['local_state'] = $settings['address']['region'];
                    }
                    if(isset($settings['address']['postalcode'])){
                        $local_business_details['local_postal_code'] = $settings['address']['postalcode'];
                    }
                    if(isset($settings['address']['street'])){
                        $local_business_details['local_street_address'] = $settings['site_telephone'];
                    }
                        
                    $post_id = wp_insert_post($schema_post);
                    $result  = $post_id;
                    $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");
                     
                    $data_group_array = array();    
                    
                    $data_group_array['group-0'] =array(
                                            'data_array' => array(
                                                        array(
                                                        'key_1' => 'post_type',
                                                        'key_2' => 'equal',
                                                        'key_3' => 'post',
                                              )
                                            )               
                                           );                                        
                    
                    $saswp_meta_key = array(
                        'schema_type'                  => 'local_business',
                        'data_group_array'             => $data_group_array,
                        'imported_from'                => 'wp_seo_schema',
                        'saswp_local_business_details' => $local_business_details
                     );
                
                    foreach ($saswp_meta_key as $key => $val){                     
                        update_post_meta($post_id, $key, $val);  
                    }
                    if(is_wp_error($result)){
                        $errorDesc[] = $result->get_error_message();
                    }
                    
                    }
                                                                
                if(isset($settings['person']['name'])){
                 $saswp_plugin_options['sd-person-name'] =  $settings['person']['name'];     
                }

                if(isset($settings['person']['jobTitle'])){
                 $saswp_plugin_options['sd-person-job-title'] =  $settings['person']['jobTitle'];        
                }

                if(isset($settings['person']['image'])){
                $image_details 	= wp_get_attachment_image_src($settings['person']['image'], 'full');

                $saswp_plugin_options['sd-person-image'] = array(
                                'url'           => $image_details[0],  
                                'id'            => $settings['organization_logo'],
                                'height'        => $image_details[1],
                                'width'         => $image_details[2],
                                'thumbnail'     => $image_details[0]        
                            );                                                  
          }         
               
          if(isset($settings['organization_logo'])){
              $image_details 	= wp_get_attachment_image_src($settings['organization_logo'], 'full');	   
              
              $saswp_plugin_options['sd_logo'] = array(
                                'url'           => $image_details[0],  
                                'id'            => $settings['organization_logo'],
                                'height'        => $image_details[1],
                                'width'         => $image_details[2],
                                'thumbnail'     => $image_details[0]        
                            );                               
          }          
          if(isset($settings['contact']['contactType'])){
              $saswp_plugin_options['saswp_contact_type'] =  $settings['contact']['contactType']; 
              $saswp_plugin_options['saswp_kb_contact_1'] =  1; 
          }
          if(isset($settings['contact']['telephone'])){
              $saswp_plugin_options['saswp_kb_telephone'] =  $settings['contact']['telephone'];    
          }                   
          if(isset($settings['sitename'])){
              $saswp_plugin_options['sd_name'] =  $settings['sitename']; 
          }
          
          if(isset($settings['siteurl'])){
              $saswp_plugin_options['sd_url'] =  $settings['sitename'];    
          }                
                $get_options   = get_option('sd_data');
                $merge_options = array_merge($get_options, $saswp_plugin_options);
                $result        = update_option('sd_data', $merge_options);
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);             
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }               
         }
                 
       
    }
    
    function saswp_import_schema_pro_plugin_data(){           
                                                                     
        $schema_post = array();
        global $wpdb;
        $user_id = get_current_user_id();
        
        $all_schema_post = get_posts(
                    array(
                            'post_type' 	 => 'aiosrs-schema',                                                                                   
                            'posts_per_page'     => -1,   
                            'post_status'        => 'any',
                    )
                 );   
        
        if($all_schema_post){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            $errorDesc = array();
            foreach($all_schema_post as $schema){    
                
                $schema_post = array(
                    'post_author'           => $user_id,
                    'post_date'             => $schema->post_date,
                    'post_date_gmt'         => $schema->post_date_gmt,
                    'post_content'          => $schema->post_content,
                    'post_title'            => $schema->post_title. ' (Migrated from Schema_pro plugin)',
                    'post_excerpt'          => $schema->post_excerpt,
                    'post_status'           => $schema->post_status,
                    'comment_status'        => $schema->comment_status,
                    'ping_status'           => $schema->ping_status,
                    'post_password'         => $schema->post_password,
                    'post_name'             => $schema->post_name,
                    'to_ping'               => $schema->to_ping,
                    'pinged'                => $schema->pinged,
                    'post_modified'         => $schema->post_modified,
                    'post_modified_gmt'     => $schema->post_modified_gmt,
                    'post_content_filtered' => $schema->post_content_filtered,
                    'post_parent'           => $schema->post_parent,                                        
                    'menu_order'            => $schema->menu_order,
                    'post_type'             => 'saswp',
                    'post_mime_type'        => $schema->post_mime_type,
                    'comment_count'         => $schema->comment_count,
                    'filter'                => $schema->filter,                    
                );   
                
                $post_id = wp_insert_post($schema_post);
                $result  = $post_id;
                $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                $wpdb->get_results("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");   
                                             
                $schema_post_types          = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-type', true );                   
                $schema_post_meta_box       = get_post_meta($schema->ID, $key='bsf-aiosrs-'.$schema_post_types, true );                
                $schema_enable_location     = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-location', true );
                $schema_exclude_location    = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-exclusion', true );
                
                $data_array = array();
                
                if($schema_exclude_location){
                    
                   $exclude_rule = $schema_exclude_location['rule'];                     
                   $fields = array_flip($exclude_rule);
                   
                   unset($fields['specifics']);
                   
                   $exclude_rule = array_flip($fields);                   
                   $exclude_specific = $schema_exclude_location['specific'];  
                  
                   
                   foreach($exclude_rule as $rule){
                       
                       if($rule =='basic-singulars'){
                           
                       $data_array['data_array'][] =array(                                                     
                            'key_1' => 'post_type',
                            'key_2' => 'not_equal',
                            'key_3' => 'post',                            
                         );
                       
                      }else{
                          
                       $explode = explode("|", $rule);   
                       $data_array['data_array'][] =array(                                                      
                            'key_1' => 'post_type',
                            'key_2' => 'not_equal',
                            'key_3' => $explode[0],                                                                  
                         );
                       
                      }                                                                   
                   }                                                           
                   
                   foreach ($exclude_specific as $rule){
                                             
                       $explode = explode("-", $rule);  
                       $specific_post_name = $explode[0];
                       $specific_post_id   = $explode[1];
                       
                       if($specific_post_name =='post'){
                         
                         $specific_post_type = get_post_type($specific_post_id); 
                         
                          $data_array['data_array'][] =array(                                                      
                            'key_1' => $specific_post_type,
                            'key_2' => 'not_equal',
                            'key_3' => $specific_post_id,                                                      
                         );  
                          
                       }
                       
                       if($specific_post_name =='tax'){
                           
                           $data_array['data_array'][] =array(                                                      
                            'key_1' => 'post_category',
                            'key_2' => 'not_equal',
                            'key_3' => $specific_post_id,                                                      
                         );
                           
                       }
                                                                                                                                                                                                                                     
                    }
                    
                    $temp_data_array = $data_array['data_array'];
                    $temp_two_array = $data_array['data_array'];                
                    $j =0;      
                    
                    foreach($temp_two_array as $key => $val){
                        
                        $index =0;     
                        
                        foreach($temp_data_array as $t=>$tval){

                        if(($val['key_1'] == $tval['key_1']) && ($val['key_2'] == $tval['key_2']) && ($val['key_3'] == $tval['key_3'])){
                          $index++;   
                            if($index>1 ){
                                unset($temp_two_array[$t]);
                            }
                         }                    

                        }
                    } 
                   $data_array['data_array'] =  array_values($temp_two_array);
                }               
                                                             
                $data_group_array = array();
                
                if($schema_enable_location){
                    
                   $enable_rule = $schema_enable_location['rule'];  
                   $fields      = array_flip($enable_rule);
                   
                   unset($fields['specifics']);
                   
                   $enable_rule     = array_flip($fields);                   
                   $enable_specific = $schema_enable_location['specific'];                    
                                                                                                                       
                    $i=0;
                    foreach ($enable_rule as $rule){
                       
                      if($rule =='basic-singulars'){
                          
                       $data_group_array['group-'.$i] =array(
                           
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => 'post',
                            )
                          ) 
                           
                         );  
                       
                      }else{
                          
                       $explode = explode("|", $rule);   
                       
                       $data_group_array['group-'.$i] =array(
                           
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => $explode[0],
                            )
                          ) 
                           
                         );   
                       
                      } 
                       if(isset($data_array['data_array'])){
                           
                            $data_group_array['group-'.$i]['data_array'] = array_merge($data_group_array['group-'.$i]['data_array'],$data_array['data_array']);                                                                      
                            
                       }
                    $i++;  
                    
                    }
                    
                    foreach ($enable_specific as $rule){
                                             
                       $explode            = explode("-", $rule);  
                       $specific_post_name = $explode[0];
                       $specific_post_id   = $explode[1];
                       
                       if($specific_post_name =='post'){
                         
                         $specific_post_type = get_post_type($specific_post_id);  
                         
                         $data_group_array['group-'.$i] =array(
                             
                                'data_array' => array(
                                  array(
                                  'key_1' => $specific_post_type,
                                  'key_2' => 'equal',
                                  'key_3' => $specific_post_id,
                                  )
                                )  
                             
                         );  
                       }
                       
                       if($specific_post_name =='tax'){
                           
                           $data_group_array['group-'.$i] =array(
                               
                                'data_array' => array(
                                 array(
                                 'key_1' => 'post_category',
                                 'key_2' => 'equal',
                                 'key_3' => $specific_post_id,
                                 )
                               )
                               
                         );
                           
                       }
                       if(isset($data_array['data_array'])){
                           
                               $data_group_array['group-'.$i]['data_array'] = array_merge($data_group_array['group-'.$i]['data_array'],$data_array['data_array']);                                                                                                                                                                           
                       
                       }
                     
                    $i++;  
                    
                    }                  
                }                                
                $schema_type  = '';                  
                
                if(isset($schema_post_types)){
                    
                  $schema_type = ucfirst($schema_post_types);  
                  
                  
                }
                if($schema_type =='Video-object'){
                    
                    $schema_type = 'VideoObject';
                    
                }
                $local_business_details = array();
                
                if($schema_type =='Local-business'){
                    
                    $schema_type = 'local_business';
                    
                    if(isset($schema_post_meta_box['telephone'])){
                        $local_business_details['local_phone'] = $schema_post_meta_box['telephone'];
                    }
                    if(isset($schema_post_meta_box['image'])){
                        $local_business_details['local_business_logo']['url'] = $schema_post_meta_box['image'];
                    }
                    if(isset($schema_post_meta_box['price-range'])){
                        $local_business_details['local_price_range'] = $schema_post_meta_box['price-range'];
                    }
                    if(isset($schema_post_meta_box['location-postal'])){
                        $local_business_details['local_postal_code'] = $schema_post_meta_box['location-postal'];
                    }
                    if(isset($schema_post_meta_box['location-region'])){
                        $local_business_details['local_state'] = $schema_post_meta_box['location-region']; 
                    }
                    if(isset($schema_post_meta_box['location-street'])){
                        $local_business_details['local_street_address'] = $schema_post_meta_box['location-street']; 
                    }
                    if(isset($schema_post_meta_box['url'])){
                       $local_business_details['local_website'] = $schema_post_meta_box['url'];  
                    }                                        
                }                  
                $saswp_meta_key = array(
                    
                    'schema_type'                   => $schema_type,
                    'data_group_array'              => $data_group_array,
                    'imported_from'                 => 'schema_pro',
                    'saswp_local_business_details'  => $local_business_details
                        
                );
                
                foreach ($saswp_meta_key as $key => $val){   
                    
                    update_post_meta($post_id, $key, $val);  
                    
                }   
                if(is_wp_error($result)){
                    $errorDesc[] = $result->get_error_message();
                }
            }                                      
              //Importing settings starts here              
              
                $schema_pro_general_settings = get_option('wp-schema-pro-general-settings');  
                $schema_pro_social_profile   = get_option('wp-schema-pro-social-profiles');
                $schema_pro_global_schemas   = get_option('wp-schema-pro-global-schemas');                                             
                $logo                        = wp_get_attachment_image_src( $schema_pro_general_settings['site-logo-custom'] , 'full' );
                             
                $saswp_plugin_options = array(
                    
                    'sd_logo'                   => array(
                                                'url'           => $logo[0],  
                                                'id'            => $schema_pro_general_settings['site-logo-custom'],
                                                'height'        => $logo[1],
                                                'width'         => $logo[2],
                                                'thumbnail'     => $logo[0]        
                    ),    
                    
                    'saswp_kb_contact_1'        => 0,                                                                            
                    //AMP Block           
                    'saswp-for-amp'             => 1, 
                    'saswp-for-wordpress'       => 1,      
                    'saswp-logo-width'          => '60',
                    'saswp-logo-height'         => '60',                    
                    'sd_initial_wizard_status'  => 1,
                                        
               );                
                if(isset($schema_pro_social_profile['facebook'])){
                  $saswp_plugin_options['sd_facebook'] =  $schema_pro_social_profile['facebook']; 
                  $saswp_plugin_options['saswp-facebook-enable'] =  1; 
                }
                if(isset($schema_pro_social_profile['twitter'])){
                  $saswp_plugin_options['sd_twitter'] =  $schema_pro_social_profile['twitter']; 
                  $saswp_plugin_options['saswp-twitter-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['google-plus'])){
                  $saswp_plugin_options['sd_google_plus'] =  $schema_pro_social_profile['google-plus']; 
                  $saswp_plugin_options['saswp-google-plus-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['instagram'])){
                  $saswp_plugin_options['sd_instagram'] =  $schema_pro_social_profile['instagram']; 
                  $saswp_plugin_options['saswp-instagram-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['youtube'])){
                  $saswp_plugin_options['sd_youtube'] =  $schema_pro_social_profile['youtube']; 
                  $saswp_plugin_options['saswp-youtube-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['linkedin'])){
                  $saswp_plugin_options['sd_linkedin'] =  $schema_pro_social_profile['linkedin']; 
                  $saswp_plugin_options['saswp-linkedin-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['pinterest'])){
                  $saswp_plugin_options['sd_pinterest'] =  $schema_pro_social_profile['pinterest']; 
                  $saswp_plugin_options['saswp-pinterest-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['soundcloud'])){
                  $saswp_plugin_options['sd_soundcloud'] =  $schema_pro_social_profile['soundcloud']; 
                  $saswp_plugin_options['saswp-soundcloud-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['tumblr'])){
                  $saswp_plugin_options['sd_tumblr'] =  $schema_pro_social_profile['tumblr']; 
                  $saswp_plugin_options['saswp-tumblr-enable'] =  1;
                }                
                if(isset($schema_pro_general_settings['site-represent'])){
                                                           
                  $saswp_plugin_options['saswp_kb_type'] = ucfirst($schema_pro_general_settings['site-represent']);  
                  $saswp_plugin_options['sd_name'] = $schema_pro_general_settings['site-name'];
                  $saswp_plugin_options['sd-person-name'] = $schema_pro_general_settings['person-name'];
                }                
                if(isset($schema_pro_global_schemas['about-page'])){
                  $saswp_plugin_options['sd_about_page'] = $schema_pro_global_schemas['about-page'];  
                }
                if(isset($schema_pro_global_schemas['contact-page'])){
                  $saswp_plugin_options['sd_contact_page'] = $schema_pro_global_schemas['contact-page'];  
                }
                if(isset($schema_pro_global_schemas['breadcrumb'])){
                  $saswp_plugin_options['saswp_breadcrumb_schema'] = $schema_pro_global_schemas['breadcrumb'];  
                }                                              
                $get_options = get_option('sd_data');
                $merge_options = array_merge($get_options, $saswp_plugin_options);
                update_option('sd_data', $merge_options);
               
              
            if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);              
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }            
        }
                             
    }    
    //Function to expand html tags form allowed html tags in wordpress    
    function saswp_expanded_allowed_tags() {
        
                $my_allowed = wp_kses_allowed_html( 'post' );
                // form fields - input
                $my_allowed['input']  = array(
                        'class'        => array(),
                        'id'           => array(),
                        'name'         => array(),
                        'data-type'    => array(),
                        'value'        => array(),
                        'type'         => array(),
                        'style'        => array(),
                        'placeholder'  => array(),
                        'maxlength'    => array(),
                        'checked'      => array(),
                        'readonly'     => array(),
                        'disabled'     => array(),
                        'width'        => array(),  
                        'data-id'      => array(),
                        'checked'      => array(),
                        'step'         => array(),
                        'min'          => array(),
                        'max'          => array()
                );
                $my_allowed['hidden']  = array(                    
                        'id'           => array(),
                        'name'         => array(),
                        'value'        => array(),
                        'type'         => array(), 
                        'data-id'         => array(), 
                );
                //number
                $my_allowed['number'] = array(
                        'class'        => array(),
                        'id'           => array(),
                        'name'         => array(),
                        'value'        => array(),
                        'type'         => array(),
                        'style'        => array(),                    
                        'width'        => array(),
                        'min'          => array(),
                        'max'          => array(),                    
                );
                $my_allowed['script'] = array(
                        'class'        => array(),
                        'type'         => array(),
                );
                //textarea
                 $my_allowed['textarea'] = array(
                        'class' => array(),
                        'id'    => array(),
                        'name'  => array(),
                        'value' => array(),
                        'type'  => array(),
                        'style'  => array(),
                        'rows'  => array(),                                                            
                );              
                // select
                $my_allowed['select'] = array(
                        'class'    => array(),
                        'multiple' => array(),
                        'id'       => array(),
                        'name'     => array(),
                        'value'    => array(),
                        'type'     => array(), 
                        'data-type'=> array(),                    
                );
                // checkbox
                $my_allowed['checkbox'] = array(
                        'class'  => array(),
                        'id'     => array(),
                        'name'   => array(),
                        'value'  => array(),
                        'type'   => array(),  
                        'disabled'=> array(),  
                );
                //  options
                $my_allowed['option'] = array(
                        'selected' => array(),
                        'value'    => array(),
                        'disabled' => array(),
                        'id'       => array(),
                );                       
                // style
                $my_allowed['style'] = array(
                        'types' => array(),
                );
                $my_allowed['a'] = array(
                        'href'           => array(),
                        'target'         => array(),
                        'add-on'         => array(),
                        'license-status' => array(),
                        'class'          => array(),
                        'data-id'        => array()
                );
                $my_allowed['p'] = array(                        
                        'add-on' => array(),                        
                        'class'  => array(),
                );
                return $my_allowed;
            }    
            
    function saswp_admin_link($tab = '', $args = array()){

                $page = 'structured_data_options';

                if ( ! is_multisite() ) {
                        $link = admin_url( 'admin.php?page=' . $page );
                }
                else {
                        $link = admin_url( 'admin.php?page=' . $page );                    
                }

                if ( $tab ) {
                        $link .= '&tab=' . $tab;
                }

                if ( $args ) {
                        foreach ( $args as $arg => $value ) {
                                $link .= '&' . $arg . '=' . urlencode( $value );
                        }
                }

                return esc_url($link);
    }
    
    function saswp_get_tab( $default = '', $available = array() ) {

                $tab = isset( $_GET['tab'] ) ? sanitize_text_field(wp_unslash($_GET['tab'])) : $default;            
                if ( ! in_array( $tab, $available ) ) {
                        $tab = $default;
                }

                return $tab;
            }
    /**
     * Function to get schema settings
     * @global type $sd_data
     * @return type array
     * @since version 1.0
     */   
            
    function saswp_default_settings_array(){
                        
                $sd_name  = 'default';
                $logo     = array();
                $bloginfo = get_bloginfo('name', 'display'); 

                if($bloginfo){

                $sd_name = $bloginfo;

                }

                $current_url    = get_home_url();           
                $custom_logo_id = get_theme_mod( 'custom_logo' );

                if($custom_logo_id){                
                    $logo       = wp_get_attachment_image_src( $custom_logo_id , 'full' );               
                }

                $user_id        = get_current_user_id();
                $username       = '';

                if($user_id > 0){

                    $user_info = get_userdata($user_id);
                    $username  = $user_info->data->display_name;

                }
                $defaults = array(                                                                                                
                        'saswp_kb_type'             => 'Organization',    
                        'sd_name'                   => $sd_name,   
                        'sd_alt_name'               => $sd_name,
                        'sd_url'                    => $current_url,                    
                        'sd-person-name'            => $username,                                            
                        'sd-person-url'             => $current_url,                                                                                                
                        'saswp_kb_contact_1'        => 0,                                                                                            
                        'saswp-for-wordpress'       => 1,                                                                        
                        'sd_initial_wizard_status'  => 1,
                        'saswp-microdata-cleanup'   => 1,
                        'saswp-other-images'        => 1,
                        'saswp_default_review'      => 1,
                        'saswp-multiple-size-image' => 1,
                        'saswp-image-resizing'      => 1,
                        'saswp_woocommerce_archive' => 1,
                        'saswp-default-rating'      => 5,
                        'instant_indexing_action'   => 1,
                        'instant_indexing'          => array('post' => 1, 'page' => 1)   
                );	  
                
                if(is_array($logo)){

                    $defaults['sd_logo']  = array(
                                    'url'           => array_key_exists(0, $logo)? $logo[0]:'',
                                    'id'            => $custom_logo_id,
                                    'height'        => array_key_exists(2, $logo)? $logo[2]:'',
                                    'width'         => array_key_exists(1, $logo)? $logo[1]:'',
                                    'thumbnail'     => array_key_exists(0, $logo)? $logo[0]:''        
                                );                   
                    
                }
                
                return $defaults;
        
    }        
            
    function saswp_defaultSettings(){
        
        // if(!function_exists('wp_get_current_user')) {
        //     include(ABSPATH . "wp-includes/pluggable.php"); 
        // }           
                global $sd_data; 
                
                $sd_data = get_option( 'sd_data', saswp_default_settings_array());     

                return $sd_data;

       }
    /**
     * Function to enqueue css and js in frontend
     * @global type $sd_data
     */        
    function saswp_frontend_enqueue(){ 

          global $sd_data;


          if(isset($sd_data['saswp-review-module']) && $sd_data['saswp-review-module'] == 1){

                    $review_details     = esc_sql ( get_post_meta(get_the_ID(), 'saswp_review_details', true));

                    if(isset($review_details['saswp-review-item-enable'])){

                        wp_enqueue_style( 'saswp-style', SASWP_PLUGIN_URL . 'admin_section/css/'.(SASWP_ENVIRONMENT == 'production' ? 'saswp-style.min.css' : 'saswp-style.css'), false , SASWP_VERSION );       

                    }                              

          }  

          if(isset($sd_data['saswp-google-review']) && $sd_data['saswp-google-review'] == 1 ){

                     wp_enqueue_style( 'saswp-style', SASWP_PLUGIN_URL . 'admin_section/css/'.(SASWP_ENVIRONMENT == 'production' ? 'saswp-style.min.css' : 'saswp-style.css'), false , SASWP_VERSION );       

          }                   

      }     
    /**
     * Function to enqueue css in amp version
     * @global type $sd_data
     */  
    function saswp_enqueue_amp_script(){
     
         global $sd_data;  
        
         $saswp_review_details = esc_sql ( get_post_meta(get_the_ID(), 'saswp_review_details', true)); 
        
         $saswp_rv_item_enable = 0;
        
         if(isset($saswp_review_details['saswp-review-item-enable'])){
            
          $saswp_rv_item_enable =  $saswp_review_details['saswp-review-item-enable'];  
         
         }         
        
         if( ( isset($sd_data['saswp-review-module']) && $sd_data['saswp-review-module'] == 1 ) && $saswp_rv_item_enable == 1){  
             
              $rating_module_css  =  SASWP_PLUGIN_DIR_PATH . 'admin_section/css/amp/rating-module.css';  
              echo @file_get_contents($rating_module_css);
              
        ?>
        
        .saswp-rvw-str .half-str{
           
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/half_star.png'); ?>);
        }
        .saswp-rvw-str .str-ic{
           
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/full_star.png'); ?>);
        }
        .saswp-rvw-str .df-clr{
           
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/blank_star.png'); ?>);
        }
               
        <?php
     }
                       
        if((has_shortcode( @get_the_content(), 'saswp-reviews')) || is_active_widget( false, false, 'saswp_google_review_widget',true ) || (isset($sd_data['saswp-review-module']) && $sd_data['saswp-review-module'] == 1) ){            
            ?>
        
            .saswp-rvw-str .half-str{                
                background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/half_star.png'); ?>);
            }
            .saswp-rvw-str .str-ic{               
                background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/full_star.png'); ?>);
            }
            .saswp-rvw-str .df-clr{                
                background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/blank_star.png'); ?>);
            }
                                
        <?php
        
              $rating_module_front_css  =  SASWP_PLUGIN_DIR_PATH . 'admin_section/css/amp/rating-module-front.css';  
              echo @file_get_contents($rating_module_front_css);
        
        }
          
  }
    /**
     * Function to get author name
     * @return type string
     */    
    function saswp_get_the_author_name(){
        
            $author_id          = get_the_author_meta('ID');														
            $aurthor_name 	= get_the_author();

            if(!$aurthor_name){

                $author_id    = get_post_field ('post_author', get_the_ID());
                $aurthor_name = get_the_author_meta( 'display_name' , $author_id ); 

            } 
            return $aurthor_name;
    }
    /**
     * Function to get post attachement details by attachement url or id
     * @param type $attachments
     * @param type $post_id
     * @return type array
     */
    function saswp_get_attachment_details($attachments, $post_id = null) {
        
        $response = array();
        
        $cached_data = get_transient('saswp_imageobject_' .$post_id); 
        
        if (empty($cached_data)) {
                       
            foreach ($attachments as $url){
             
                $image_data = array();    

                $image = @getimagesize($url);

                if(is_array($image)){

                    $image_data[0] =  $image[0]; //width
                    $image_data[1] =  $image[1]; //height

                }                                 
            
                if(empty($image) || $image == false){
                    
                    $img_id           = attachment_url_to_postid($url);
                    $imageDetail      = wp_get_attachment_image_src( $img_id , 'full');

                    if($imageDetail && is_array($imageDetail)){

                        $image_data[0]    = $imageDetail[1]; // width
                        $image_data[1]    = $imageDetail[2]; // height

                    }                    
                    
                }
                
              $response[] = $image_data;  
            }
                                  
            set_transient('saswp_imageobject_' .$post_id, $response,  24*30*HOUR_IN_SECONDS );   

            $cached_data = $response;
        }
                                            
        return $cached_data;
                	
}
    /**
     * Here we are getting article full body content
     * @global type $post
     * @return type string
     */
    function saswp_get_the_content(){

        global $post;
        $content = '';   
        
        if(is_object($post)){
            $content = get_post_field('post_content', $post->ID);            
            $content = wp_strip_all_tags($content);   
            $content = preg_replace('/\[.*?\]/','', $content);            
            $content = str_replace('=', '', $content); 
            $content = str_replace(array("\n","\r\n","\r"), ' ', $content);
        }
        
        return apply_filters('saswp_the_content' ,$content);

    }

    function saswp_strip_all_tags( $content ) {
            
            $content = wp_strip_all_tags($content);   
            $content = preg_replace('/\[.*?\]/','', $content);            
            $content = str_replace('=', '', $content); 
            $content = str_replace(array("\n","\r\n","\r"), ' ', $content);

            return $content;
    }
    /**
     * Here we are modifying the default excerpt
     * @global type $post
     * @return type string
     */
    function saswp_get_the_excerpt() {

        global $post;
        global $sd_data;
        
        $excerpt = '';
        
        
        if(is_object($post)){

        $excerpt = $post->post_excerpt;

        if(empty($excerpt)){

            $post_content = wp_strip_all_tags(strip_shortcodes($post->post_content)); 
            $post_content = preg_replace('/\[.*?\]/','', $post_content);

            $excerpt_length = apply_filters( 'excerpt_length', 55 );                        
            $excerpt_more = '';
            $excerpt      = wp_trim_words( $post_content, $excerpt_length, $excerpt_more );
        }

        if(strpos($excerpt, "<p>")!==false){

            $regex = '/<p>(.*?)<\/p>/';
            preg_match_all($regex, $excerpt, $matches);

            if(is_array($matches[1])){
                $excerpt = implode(" ", $matches[1]); 
            }

        }
               
        if(saswp_remove_warnings($sd_data, 'saswp-yoast', 'saswp_string') == 1){

            $yoast_meta_des = saswp_convert_yoast_metafields($post->ID, 'metadesc');

            if($yoast_meta_des){

                $excerpt = $yoast_meta_des;

            }

        }

        if(saswp_remove_warnings($sd_data, 'saswp-slimseo', 'saswp_string') == 1){

            $slim_seo = get_post_meta( $post->ID, 'slim_seo', true );
            
            if(isset($slim_seo['description']) && $slim_seo['description'] != ''){
                $excerpt = $slim_seo['description'];
            }

        }
        
        if(saswp_remove_warnings($sd_data, 'saswp-smart-crawl', 'saswp_string') == 1){
                            
                if(class_exists('Smartcrawl_OpenGraph_Value_Helper')){
                        
                    $value_helper = new Smartcrawl_OpenGraph_Value_Helper();
            
                    $smart_meta_des =  $value_helper->get_description();
                    
                    if($smart_meta_des){
                        $excerpt = $smart_meta_des;
                    }
                                                    
                }
                                      
        }
        
        //All in one Seo pack
        if(saswp_remove_warnings($sd_data, 'saswp-aiosp', 'saswp_string') == 1){
                             
             global $aiosp;  
             
             if(is_object($aiosp)){
             
                    $c_excerpt =  $aiosp->get_aioseop_description($post);             
                    if($c_excerpt){
                        $excerpt = $c_excerpt;
                    }
                 
             }
                                                                             
        }
        
        //SEOPress 
        if( saswp_remove_warnings($sd_data, 'saswp-seo-press', 'saswp_string') == 1 && function_exists('seopress_titles_the_description_content') ){
            
             require_once ( WP_PLUGIN_DIR. '/wp-seopress/inc/functions/options-titles-metas.php'); //Social                                                                              
             $c_excerpt =  seopress_titles_the_description_content($post);             
             
             if($c_excerpt){
                 $excerpt = $c_excerpt;
             }            
                                      
        }
        
        //SEOPress
        if(saswp_remove_warnings($sd_data, 'saswp-squirrly-seo', 'saswp_string') == 1 && class_exists('SQ_Models_Abstract_Seo')){
                        
                 global $wpdb;
                
                 $query = "SELECT * FROM " . $wpdb->prefix . "qss where post_id=".$post->ID;
                 
                 if ($rows = $wpdb->get_results($query, OBJECT)) {
                     
                    $seo_data = unserialize($rows[0]->seo) ;
                                        
                    if(isset($seo_data['description']) && $seo_data['description'] <>''){
                      $excerpt = $seo_data['description'];
                    }                     
                 }                                                 
        }
        
                
        if(saswp_remove_warnings($sd_data, 'saswp-the-seo-framework', 'saswp_string') == 1){
                            
                $c_excerpt = get_post_meta($post->ID, '_genesis_description', true);
                
                if($c_excerpt){
                    $excerpt = $c_excerpt;
                }       
                                      
        }

        if(saswp_remove_warnings($sd_data, 'saswp-rankmath', 'saswp_string') == 1 && class_exists('RankMath\Post')){
                        
            $c_excerpt = RankMath\Post::get_meta( 'description', $post->ID );
        
            if($c_excerpt){
                $excerpt = $c_excerpt;
            }
        
        }

        if( isset($sd_data['saswp-metatagmanager']) && $sd_data['saswp-metatagmanager'] == 1 && class_exists('Meta_Tag_Manager') ){

            $post_meta = get_post_meta($post->ID, 'mtm_data', true);
            
            if(is_array($post_meta)){

                $meta_tag = array_column($post_meta, 'value');
            
                $key      = array_search("description",$meta_tag);
                
                if(array_key_exists($key, $post_meta)){
                    
                    $c_excerpt = $post_meta[$key]['content'];
                    
                    if($c_excerpt){
                        $excerpt = $c_excerpt;
                    }

                }

            }            
                            
        }
            
        }
           
        $excerpt = wp_strip_all_tags(strip_shortcodes($excerpt)); 
        $excerpt = preg_replace('/\[.*?\]/','', $excerpt);
        return apply_filters('saswp_the_excerpt' ,$excerpt);
    }
    /**
     * since @1.8.9
     * Here, we are getting meta fields value from yoast seo
     * @global type $post
     * @return type string
     */
    function saswp_convert_yoast_metafields ($post_id, $field) {

        if(class_exists('WPSEO_Meta') && class_exists('WPSEO_Replace_Vars')){

            $string =  WPSEO_Meta::get_value( $field, $post_id );
            if ($string !== '') {
                $replacer = new WPSEO_Replace_Vars();

                return $replacer->replace( $string, get_post($post_id) );
            }

        }         
        return '';
    }
    
    function saswp_get_blog_desc(){
        
        global $sd_data; 
        
        $blog_desc = get_bloginfo('description');
        
        if(is_home() || is_front_page() || ( function_exists('ampforwp_is_home') && ampforwp_is_home()) ){
            
        if(isset($sd_data['saswp-yoast']) && $sd_data['saswp-yoast'] == 1){
            
            if(class_exists('WPSEO_Frontend')){
                
                if (defined('WPSEO_VERSION') && WPSEO_VERSION < 14.0) {
                    $front             = WPSEO_Frontend::get_instance();
                    $blog_desc         = $front->metadesc( false );  
                }else{
                   global $saswp_yoast_home_meta;
                   $blog_desc = $saswp_yoast_home_meta;                                        
                }
                                                      
                if(empty($blog_desc)){
                    $blog_desc = get_bloginfo('description');
                }                                   
            }            
          }
        }                        
        return $blog_desc;
    }    
    /**
     * since @1.8.7
     * Here we are modifying the default title
     * @global type $post
     * @return type string
     */
    function saswp_get_the_title(){

        global $post;
        global $sd_data;

        $title = $c_title = '';

        if(is_object($post)){
            $title   = @get_the_title();
        }
                                                
        //SEOPress
        if(saswp_remove_warnings($sd_data, 'saswp-squirrly-seo', 'saswp_string') == 1 && class_exists('SQ_Models_Abstract_Seo')){
                        
                global $wpdb;
                
                 $query = "SELECT * FROM " . $wpdb->prefix . "qss where post_id=".$post->ID;
                 
                 if ($rows = $wpdb->get_results($query, OBJECT)) {
                     
                    $seo_data = unserialize($rows[0]->seo) ;
                                        
                    if(isset($seo_data['title']) && $seo_data['title'] <>''){
                      $title = $seo_data['title'];
                    } 
                    
                 }             
                                    
        }
        
        //SEOPress
        if(saswp_remove_warnings($sd_data, 'saswp-seo-press', 'saswp_string') == 1){
            
             if(!is_admin()){
                                             
                    if(function_exists('seopress_titles_the_title') && seopress_titles_the_title() !=''){

                       require_once ( WP_PLUGIN_DIR. '/wp-seopress/inc/functions/options-titles-metas.php');

                       $c_title =  seopress_titles_the_title();                
                       if($c_title){
                           $title = $c_title;
                       }

                    }
                 
             }   
                                                               
        }
        
        //All in one Seo pack
        if(saswp_remove_warnings($sd_data, 'saswp-aiosp', 'saswp_string') == 1){
                 
            
             global $aiosp;
             
             if(is_object($aiosp)){
             
                $c_title =  $aiosp->wp_title();
             
                if($c_title){
                 $title = $c_title;
                }
                 
             }
                                                                            
        }
        
        //Slim Seo
        if(saswp_remove_warnings($sd_data, 'saswp-slimseo', 'saswp_string') == 1){
                          
            
            $slim_seo = get_post_meta( get_the_ID(), 'slim_seo', true );
            
            if(isset($slim_seo['title']) && $slim_seo['title'] != ''){
                $title = $slim_seo['title'];
            }
            
        }

        //The seo framework
        if(saswp_remove_warnings($sd_data, 'saswp-the-seo-framework', 'saswp_string') == 1){
                          
            if(is_object($post)){

                $c_title = get_post_meta($post->ID, '_genesis_title', true);
                
                if($c_title){
                    $title = $c_title;
                }                

            }
                                                                                      
        }
        
        //SmartCrawl title
                
        if(saswp_remove_warnings($sd_data, 'saswp-smart-crawl', 'saswp_string') == 1){

            if(is_object($post)){
                
                if(class_exists('Smartcrawl_OpenGraph_Value_Helper')){
                        
                    $value_helper = new Smartcrawl_OpenGraph_Value_Helper();
            
                    $c_title =  $value_helper->get_title();
                    
                    if($c_title){

                       $title = $c_title;

                    }
            
                }
                
            }
            
        }
        
        
        //Yoast title 
        if(saswp_remove_warnings($sd_data, 'saswp-yoast', 'saswp_string') == 1){

            if(is_object($post)){

                $c_title = saswp_convert_yoast_metafields($post->ID, 'title');

            }

            if($c_title){

                $title = $c_title;

            }

        }

        if(saswp_remove_warnings($sd_data, 'saswp-rankmath', 'saswp_string') == 1 && class_exists('RankMath\Post')){
                        
            $c_title = RankMath\Post::get_meta( 'title', get_the_ID() );
        
            if(empty($c_title)){
                $c_title = RankMath\Paper\Paper::get()->get_title();
            }

            if($c_title){

                $title = $c_title;

            }
        
        }
        
        if( isset($sd_data['saswp-full-heading']) && $sd_data['saswp-full-heading'] == 1 ){
            return $title;
        }
        
        if (mb_strlen($title, 'UTF-8') > 110){
            $title = mb_substr($title, 0, 106, 'UTF-8') . ' ...';
        }
        
        return $title; 

    }
    /**
     * since @1.8.7
     * Get the author details 
     * @global type $post
     * @return type array
     */
    function saswp_get_author_details(){

        global $post, $sd_data;

        $author_details = array();            

        $author_id          = get_the_author_meta('ID');
        $author_name 	    = get_the_author();
        $author_desc        = get_the_author_meta( 'user_description' );     

        if(!$author_name && is_object($post)){
            $author_id    = get_post_field ( 'post_author', $post->ID);
            $author_name  = get_the_author_meta( 'display_name' , $author_id );             
        }

        $author_meta =  get_user_meta($author_id);

        $author_url   = get_author_posts_url( $author_id ); 
        $same_as      = array();

        $social_links = array('url', 'facebook', 'twitter', 'instagram', 'linkedin', 'myspace', 'pinterest', 'soundcloud', 'tumblr', 'youtube', 'wikipedia', 'jabber', 'yim', 'aim');

        foreach($social_links as $links){

            $url  = get_the_author_meta($links, $author_id );

            if($url){
                $same_as[] = $url;
            }

        }
                        
        $author_image = array();
        
        if(function_exists('get_avatar_data')){
            $author_image	= get_avatar_data($author_id);
        }
                
        $author_details['@type']           = 'Person';
        $author_details['name']            = esc_attr($author_name);
        if(!empty($author_desc)){
            $author_details['description']     = wp_strip_all_tags(strip_shortcodes($author_desc)); 
        }else{
            if(!empty($author_meta['author_bio'][0])){
                $author_details['description'] =   $author_meta['author_bio'][0];
            }
        }
        $author_details['url']             = esc_url($author_url);
        $author_details['sameAs']          = $same_as;

        if(!empty($author_meta['knowsabout'][0])){
            $author_details['knowsAbout'] =   explode(',', $author_meta['knowsabout'][0]);
        }

        if(!empty($author_meta['honorificsuffix'][0])){
            $author_details['honorificSuffix'] =  $author_meta['honorificsuffix'][0];
        }

        if(!empty($author_meta['alumniof'][0])){
            $str =  $author_meta['alumniof'][0];
            $itemlist = explode(",", $str);
            foreach ($itemlist as $key => $list){
                $vnewarr['@type'] = 'Organization';
                $vnewarr['Name']   = $list;   
                $author_details['alumniOf'][] = $vnewarr;
            }
        }

        if(!empty($author_meta['author_image'][0])){
            $author_image =  wp_get_attachment_image_src($author_meta['author_image'][0]);
           
            $author_details['image']['@type']  = 'ImageObject';
            $author_details['image']['url']    = $author_image[0];
            $author_details['image']['height'] = $author_image[1];
            $author_details['image']['width']  = $author_image[2];

        }elseif(isset($author_image['url']) && isset($author_image['height']) && isset($author_image['width'])){

            $author_details['image']['@type']  = 'ImageObject';
            $author_details['image']['url']    = $author_image['url'];
            $author_details['image']['height'] = $author_image['height'];
            $author_details['image']['width']  = $author_image['width'];
        }
        if(isset($sd_data['saswp-simple-author-box']) && $sd_data['saswp-simple-author-box'] == 1 && function_exists('sab_fs') ){

            $sab_image = get_the_author_meta( 'sabox-profile-image', $author_id );

            if($sab_image){

                $image = @getimagesize($sab_image);

                if($image){
                    $author_details['image']['@type']  = 'ImageObject';
                    $author_details['image']['url']    = $sab_image;
                    $author_details['image']['height'] = $image[1];
                    $author_details['image']['width']  = $image[0];
                }                
                                 
            }
        }

        return $author_details;
    }
    /** 
     * Function to sanitize display condition and user targeting
     * @param type $array
     * @param type $type
     * @return type array
     */
    function saswp_sanitize_multi_array($array, $type){
    
    if($array){
               
        foreach($array as $group => $condition){
            
            $group_condition = $condition[$type];
            
            foreach ($group_condition as $con_key => $con_val){
                
                foreach($con_val as $key => $val){
                        
                        $con_val[$key] =   sanitize_text_field($val);
                        
                }
                
                $group_condition[$con_key] = $con_val;
            }
            
            $array[$group] = $condition;
            
        }
        
    }
    
    return $array;
}

function saswp_compatible_active_list(){
        
    $pnamelist   = array();
    $active      = array();
        
    $mappings_file = SASWP_DIR_NAME . '/core/array-list/compatibility-list.php';
                
    if ( file_exists( $mappings_file ) ) {
        $pnamelist = include $mappings_file;        
    }
    
    foreach ($pnamelist['plugins'] as $key => $plugin){
        
        if(is_plugin_active($plugin['free']) || (array_key_exists('pro', $plugin) && is_plugin_active($plugin['pro']))){

            $active[$key] = $plugin['opt_name'];

        }
        
    }    
    foreach ($pnamelist['themes'] as $key => $plugin){
        
        if(get_template() == $plugin['free']){

            $active[$key] = $plugin['opt_name'];

        }
        
    }
                                    
    return $active;
    
}

function saswp_uninstall_single($blog_id = null){
        
        try{
         
        global $wpdb;
	
        //SASWP post types
        $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'saswp' ) );
        
        if ( $post_ids ) {
                $wpdb->delete(
                        $wpdb->posts,
                        array( 'post_type' => 'saswp' ),
                        array( '%s' )
                );

                $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN( " . implode( ',', $post_ids ) . " )" );
        }
        
        if($post_ids){
            
            $query = "SELECT ID FROM " . $wpdb->posts;
            $all_post_id   = $wpdb->get_results($query, ARRAY_A );
            $all_post_id   = wp_list_pluck( $all_post_id, 'ID' );              
                        
            foreach($post_ids as $post_id){
                
               $meta_fields = saswp_get_fields_by_schema_type($post_id); 
               $meta_fields = wp_list_pluck( $meta_fields, 'id' );
               
               foreach ($meta_fields as $meta_key){                   
                   $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN( " . implode( ',', $all_post_id ) . " ) AND meta_key = '".$meta_key."'" );
                   
               }
                                              
            }
        }
        
        //Post specific post meta
                                
        //Review Post Types        
        $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'saswp_reviews' ) );
        
        if ( $post_ids ) {
                $wpdb->delete(
                        $wpdb->posts,
                        array( 'post_type' => 'saswp_reviews' ),
                        array( '%s' )
                );

                $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN( " . implode( ',', $post_ids ) . " )" );
        }
                
        //All options                    
        delete_option('sd_data');  
        
        wp_cache_flush();
            
        }catch(Exception $ex){
            echo $ex->getMessage();
        }            
                
}

function saswp_on_uninstall(){
        
   global $wpdb;
    
   $options = get_option('sd_data');
    
   if(isset($options['saswp_rmv_data_on_uninstall'])){
    
       if ( ! is_multisite() ) {
            saswp_uninstall_single();
        } else {
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

                foreach ( $blog_ids as $blog_id ) {

                        saswp_uninstall_single($blog_id);
                }

        }
              
   }            
                                      
}

function saswp_on_activation(){
    
    $installation_date = get_option('saswp_installation_date');
    
    if(!$installation_date){
        
        update_option('saswp_installation_date', date("Y-m-d"));        
        
    }
            
    $defaults = get_option('sd_data', saswp_default_settings_array());
    
    $active_plugin = saswp_compatible_active_list();
                
    if($active_plugin){

        foreach ($active_plugin as $plugin){
            $defaults[$plugin] = 1;
        }

    }
        
    update_option('sd_data', $defaults);  
                              
}

function saswp_context_url(){
    
    $url = 'http://schema.org';
    
    if(is_ssl()){
        $url = 'https://schema.org';
    }
    
    return $url;
}

function saswp_get_permalink(){
    
    $url = get_permalink();
        
    if ((function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint()) || function_exists( 'is_amp_endpoint' ) && is_amp_endpoint()) {  
    
        if(function_exists('ampforwp_url_controller')){
            
            $url = ampforwp_url_controller( $url );
            
        }
        
    }
    
    return saswp_validate_url($url);
}
function saswp_get_taxonomy_term_list(){
    
        if ( ! current_user_can( saswp_current_user_can() ) ) {
             return;
        }
        if ( ! isset( $_GET['saswp_security_nonce'] ) ){
           return; 
        }
        if ( !wp_verify_nonce( $_GET['saswp_security_nonce'], 'saswp_ajax_check_nonce' ) ){
           return;  
        }
        
        $choices    = array('all' => saswp_t_string('All'));
        $taxonomies = saswp_post_taxonomy_generator();        
        $choices    = array_merge($choices, $taxonomies);                                          
        echo wp_json_encode($choices);
        
        wp_die();
}
add_action( 'wp_ajax_saswp_get_taxonomy_term_list', 'saswp_get_taxonomy_term_list'); 

add_action('init', 'saswp_save_new_social_profile');
function saswp_save_new_social_profile(){
    saswp_migrate_old_social_profile();
}
function saswp_migrate_old_social_profile(){
    
        $upgrade_option = get_option('saswp_social_profile_upgrade');
        
        if(!$upgrade_option){
    
            $sd_data = get_option('sd_data');
    
            $sd_social_profile = array();

            if(isset($sd_data['sd_facebook']) && !empty($sd_data['sd_facebook'])){
                    $sd_social_profile[] = $sd_data['sd_facebook'];
            }	
            if(isset($sd_data['sd_twitter']) && !empty($sd_data['sd_twitter'])){		
                    $sd_social_profile[] = $sd_data['sd_twitter'];
            }		
            if(isset($sd_data['sd_instagram']) && !empty($sd_data['sd_instagram'])){		
                    $sd_social_profile[] = $sd_data['sd_instagram'];
            }	
            if(isset($sd_data['sd_youtube']) && !empty($sd_data['sd_youtube'])){		
                    $sd_social_profile[] = $sd_data['sd_youtube'];
            }	
            if(isset($sd_data['sd_linkedin']) && !empty($sd_data['sd_linkedin'])){		
                    $sd_social_profile[] = $sd_data['sd_linkedin'];
            }	
            if(isset($sd_data['sd_pinterest']) && !empty($sd_data['sd_pinterest'])){	
                    $sd_social_profile[] = $sd_data['sd_pinterest'];
            }	
            if(isset($sd_data['sd_soundcloud']) && !empty($sd_data['sd_soundcloud'])){		
                    $sd_social_profile[] = $sd_data['sd_soundcloud'];
            }
            if(isset($sd_data['sd_tumblr']) && !empty($sd_data['sd_tumblr'])){		
                    $sd_social_profile[] = $sd_data['sd_tumblr'];
            }                
            if(isset($sd_data['sd_yelp']) && !empty($sd_data['sd_yelp'])){		
                    $sd_social_profile[] = $sd_data['sd_yelp'];
            }
            if(isset($sd_data['saswp_social_links']) && !empty($sd_data['saswp_social_links'])){
                $sd_social_profile = array_merge($sd_social_profile, $sd_data['saswp_social_links']);
            }
            $sd_data['saswp_social_links'] = $sd_social_profile;        
            update_option('sd_data', $sd_data);
            
            update_option('saswp_social_profile_upgrade', date("Y-m-d"));
        }
    
}
function saswp_validate_url($url){
    
    if(wp_http_validate_url($url)){
        return $url;
    }else{
        return '';
    }
    
}
function saswp_validate_date($date, $format = 'Y-m-d H:i:s'){
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
}

function saswp_format_date_time($date, $time=null){
    
    $formated = ''; 

    $timezone = get_option('timezone_string');

    if($timezone){
        date_default_timezone_set($timezone);
    }    
    
    if($date && $time){
        $formated =  date('c',strtotime($date.' '.$time));       
    }else{
        if($date){
        $formated =  date('c',strtotime($date));      
        }        
    }               
    
    return $formated;
}

function saswp_https( $url ) {
                        
    return str_replace( 'http://', 'https://', $url );            
                	
}

function saswp_remove_unwanted_metabox(){
    
    global $wp_meta_boxes;    
    
    if(get_post_type() == 'saswp' || get_post_type() == 'saswp_reviews'){
        $wp_meta_boxes = array();               
    }
            
    return $wp_meta_boxes;
}


function saswp_remove_unwanted_notice_boxes(){
    
    $screen_id = ''; 
    $current_screen = get_current_screen();
    
    if(is_object($current_screen)){
        $screen_id =  $current_screen->id;
    }
    
    if( get_post_type() == 'saswp' || 
        get_post_type() == 'saswp_reviews' ||
        get_post_type() == 'saswp-collections' ||
        $screen_id =='saswp_page_structured_data_options' ||
        $screen_id =='edit-saswp' ||
        $screen_id == 'saswp' ||
        $screen_id == 'edit-saswp-collections'
       ){
        
       remove_all_actions('admin_notices'); 
       
    //    global $saswp_wisdom;
       
    //    add_action( 'admin_notices', array($saswp_wisdom , 'optin_notice') );
    //    add_action( 'admin_notices', array($saswp_wisdom , 'marketing_notice') );
       add_action( 'admin_notices', 'saswp_admin_notice' );
    }
        
}

add_action('in_admin_header', 'saswp_remove_unwanted_notice_boxes',999);

function saswp_admin_notice(){
    
    $screen_id      = ''; 
    $current_screen = get_current_screen();
    
    if(is_object($current_screen)){
        $screen_id =  $current_screen->id;
    }
    
    $nonce = wp_create_nonce( 'saswp_install_wizard_nonce' );  
    
    $setup_notice = '<div class="updated notice message notice notice-alt saswp-setup-notice">'
                    . '<p>'
                    . '<strong>'.saswp_t_string('Welcome to Schema & Structured Data For WP').'</strong>'
                    .' - '.saswp_t_string('You are almost ready :)')
                    . '</p>'
                    . '<p>'
                    . '<a class="button button-primary" href="'.esc_url(admin_url( 'plugins.php?page=saswp-setup-wizard' ).'&_saswp_nonce='.$nonce).'">'
                    . saswp_t_string('Run the Setup Wizard')
                    . '</a> '
                    .'<a class="button saswp-skip-button">'
                    . saswp_t_string('Skip Setup')
                    . '</a>'
                    . '</p>'
                    . '</div>';        
                                      
    $sd_data         = get_option('sd_data'); 
        
    if(($screen_id =='saswp_page_structured_data_options' ||$screen_id == 'plugins' || $screen_id =='edit-saswp' || $screen_id == 'saswp') && !isset($sd_data['sd_initial_wizard_status'])){
            
        echo $setup_notice;
        
    }     
     //Feedback notice    
    $activation_date  =  get_option("saswp_activation_date");  
    $activation_never =  get_option("saswp_activation_never");      
    $next_days        =  strtotime("+7 day", strtotime($activation_date));
    $next_days        =  date('Y-m-d', $next_days);   
    $current_date     =  date("Y-m-d");

    $notice_msg = '';

    if($activation_date){

        $date1 = new DateTime($activation_date);
        $date2 = new DateTime($current_date);
        $diff = $date1->diff($date2);
        
        $notice_msg = ( ($diff->y > 0 ) ? $diff->y. ' years, ' : ''). ( ($diff->m > 0 ) ? $diff->m. ' month, ' : ''). ( ($diff->d > 0 ) ? floor($diff->d / 7). ' Week, ' : '');        
    }
        
    if(($next_days < $current_date) && $activation_never !='never' && $notice_msg ){
      ?>
         <div class="updated notice message notice notice-alt saswp-feedback-notice">                         
            <p class="saswp-notice-p">
            <?php   echo saswp_t_string('Awesome, you\'ve been using '); 
                    echo '<strong>' .saswp_t_string(' Schema & Structured Data '). '</strong>' ;
                    echo saswp_t_string('plugin for more than '. $notice_msg);
                    echo '<p class="saswp-notice-p">'.saswp_t_string('May we ask you to give it a 5-star rating on WordPress?').'</p>';                                     
            ?>
            <div>- SASWP dev team</div>
            </p>                                                                        

            <div class="saswp-update-notice-btns">
                <ul>
                    <li><a target="_blank" href="https://wordpress.org/plugins/schema-and-structured-data-for-wp/#reviews"><?php echo saswp_t_string('Ok, you deserve it') ?></a></li>
                    <li><a  class="saswp-feedback-remindme"><?php echo saswp_t_string('Nope, May be later') ?></a></li>
                    <li><a  class="saswp-feedback-no-thanks"><?php echo saswp_t_string('I already did') ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }  
    
    if(isset($sd_data['sd_logo']['url']) && $sd_data['sd_logo']['url'] == '' && ($screen_id =='saswp_page_structured_data_options' ||$screen_id == 'plugins' || $screen_id =='edit-saswp' || $screen_id == 'saswp')){

        ?>
        <div class="updated notice is-dismissible message notice notice-alt saswp-feedback-notice">
            <p>
                  <span><?php echo saswp_t_string('Please setup Logo to avoid structured data validation issue.') ?> </span>                                               
                  &nbsp<a href="<?php echo esc_url( admin_url( 'admin.php?page=structured_data_options&tab=general#saswp-knowledge-container' ) ); ?>"> <?php echo saswp_t_string('Please Setup') ?></a>
            </p>
        </div>

      <?php   
        
    }

    if(isset($sd_data['sd_default_image']['url']) && $sd_data['sd_default_image']['url'] == '' && ($screen_id =='saswp_page_structured_data_options' ||$screen_id == 'plugins' || $screen_id =='edit-saswp' || $screen_id == 'saswp')){

        ?>
        <div class="updated notice is-dismissible message notice notice-alt saswp-feedback-notice">
            <p>
                  <span><?php echo saswp_t_string('You have not set up default image in Schema & Structured Data For WP.') ?> </span>                                               
                  &nbsp<a href="<?php echo esc_url( admin_url( 'admin.php?page=structured_data_options&tab=general#saswp-default-container' ) ); ?>"> <?php echo saswp_t_string('Please Setup') ?></a>
            </p>
        </div>

      <?php   
        
    }

    if(function_exists('Stars_Rating') && (isset($sd_data['saswp-starsrating']) && $sd_data['saswp-starsrating'] == 1)){
        
        ?>
        <div class="updated notice is-dismissible message">
            <p>
                  <span><?php echo saswp_t_string('You use Stars Rating plugin and has enabled Stars Rating option in Schema & Structured Data For WP & AMP. Use any one option for better comment form.') ?> </span>                                                                
            </p>
        </div>

      <?php 

    }

    $user_id      = get_current_user_id();
    $dismiss_meta = get_user_meta( $user_id, 'amp_enable_dismiss_date' );

    if(!$dismiss_meta && (!isset($sd_data['saswp-for-amp']) ||(isset($sd_data['saswp-for-amp']) &&$sd_data['saswp-for-amp'] == 0)) ){

        ?>
        <div class="updated notice message notice notice-alt saswp-feedback-notice">
           <p>
           <?php echo saswp_t_string('You have disabled schema on AMP.') ?>
           &nbsp<a href="<?php echo esc_url( admin_url( 'admin.php?page=structured_data_options&tab=amp' ) ); ?>"> <?php echo saswp_t_string('Enable it') ?></a>
           <a notice-type="amp_enable" class="saswp-revws-lnk saswp-dismiss-notices"> <?php echo saswp_t_string('Dismiss') ?></a>
         </p>           
       </div>
       <?php

    }
            
}

function saswp_remove_anonymous_object_filter_or_action( $tag, $class, $method, $hook_type ){
    
        $filters = $GLOBALS['wp_filter'][ $tag ];               
        if ( empty ( $filters ) )
        {
            return;
        }
       
        if(is_array($filters)){
            
            foreach ( $filters as $priority => $filter )
            {
             
            foreach ( $filter as $identifier => $function )
            {
                    
                if ( is_array( $function)
                    and is_a( $function['function'][0], $class )
                    and $method === $function['function'][1]
                )
                {         
                    if($hook_type == 'filter'){
                        
                        remove_filter(
                            $tag,
                            array ( $function['function'][0], $method ),
                            $priority
                        );
                        
                    }
                    if($hook_type == 'action'){
                     
                        remove_action(
                            $tag,
                            array ( $function['function'][0], $method ),
                            $priority
                        );
                        
                    }                    
                }
            }
        }
        }        
    }
    
function saswp_get_field_note($pname){
    
    $notes = array(  
            'ameliabooking'               => saswp_t_string('Requires').' <a target="_blank" href="https://wpamelia.com/">wpamelia</a>',
            'wpml'                        => saswp_t_string('Requires').' <a target="_blank" href="https://wpml.org">WPML</a>',
            'polylang'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/polylang/">Polylang</a>',
            'autolistings'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/auto-listings">Auto Listings</a>',
            'wpdiscuz'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wpdiscuz/">Comments – wpDiscuz</a>',
            'rannarecipe'                 => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/ranna-food-recipe-wordpress-theme/25157340">Ranna - Food & Recipe</a>',
            'easy_recipe'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/easyrecipe/">EasyRecipe</a>',
            'total_recipe_generator'      => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/total-recipe-generator-wordpress-recipe-maker-with-schema-and-nutrition-facts-elementor-addon/21445400/">Total Recipe Generator</a>',
            'yet_another_stars_rating'    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/yet-another-stars-rating/">Yet Another Stars Rating</a>',
            'wp_customer_reviews'         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-customer-reviews">WP Customer Reviews</a>',
            'wordpress_news'              => saswp_t_string('Requires').' <a target="_blank" href="#">Wordpress News</a>',
            'strong_testimonials'         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/strong-testimonials">Strong Testimonials</a>',
            'brb'                         => saswp_t_string('Requires').' <a target="_blank" href="https://richplugins.com/business-reviews-bundle-wordpress-plugin">Business Reviews Bundle</a>',
            'wp_event_aggregator'         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-event-aggregator/">WP Event Aggregator</a>',
            'stachethemes_event_calendar' => saswp_t_string('Requires').' <a target="_blank" href="http://stachethemes.com/">Stachethemes Event Calendar</a>',
            'timetable_event'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/mp-timetable">Timetable and Event Schedule by MotoPress</a>',
            'all_in_one_event_calendar'   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/all-in-one-event-calendar/">All In One Event Calendar</a>',
            'xo_event_calendar'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/xo-event-calendar/">XO Event Calendar</a>',
            'calendarize_it'              => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/calendarize-it-for-wordpress/2568439">Calendarize it! for WordPress</a>',
            'events_schedule'             => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/events-schedule-wordpress-plugin/14907462">Events Schedule - WordPress Events Calendar Plugin</a>',
            'woo_event_manager'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/mage-eventpress/">WooCommerce Event Manager</a>',
            'vs_event_list'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/very-simple-event-list/">Very Simple Event List</a>',
            'event_on'                    => saswp_t_string('Event On').' <a target="_blank" href="https://www.myeventon.com/">Event On</a>',
            'wordlift'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wordlift/">WordLift</a>',
            'ampforwp'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/accelerated-mobile-pages/">AMP for WP</a>',
            'quickandeasyfaq'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/quick-and-easy-faqs/">Quick and Easy FAQs</a>',
            'accordionfaq'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/responsive-accordion-and-collapse">Accordion FAQ</a>',
            'webfaq10'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/faq-wd/">10WebFAQ</a>',
            'ultimatefaqs'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/ultimate-faqs/">Ultimate FAQs</a>',
            'ultimatemember'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/ultimate-member/">Ultimate Member</a>',
            'showcaseidx'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/showcase-idx/">Showcaseidx</a>',
            'easyaccordion'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/easy-accordion-free/">Easy Accordion</a>',
            'wpresponsivefaq'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/sp-faq">WP responsive FAQ with category plugin</a>',
            'arconixfaq'                  => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/arconix-faq/">Arconix FAQ</a>',
            'faqconcertina'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/faq-concertina/">FAQ Concertina</a>',
            'masteraccordion'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-awesome-faq/">Master Accordion</a>',
            'wpfaqschemamarkup'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-faq-schema-markup-for-seo/">WP FAQ Schema Markup for SEO</a>',
            'faqschemaforpost'            => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/faq-schema-for-pages-and-posts/">FAQ Schema For Pages And Posts</a>',
            'accordion'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/accordions">Accordion By PickPlugins</a>',
            'easyfaqs'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/easy-faqs/">Easy FAQs</a>',
            'html5responsivefaq'          => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/html5-responsive-faq/">HTML5 Responsive FAQ</a>',
            'helpiefaq'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/helpie-faq/">Helpie FAQ – WordPress FAQ Accordion Plugin</a>',
            'mooberrybm'                  => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/mooberry-book-manager/">Mooberry Book Manager</a>',
            'novelist'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/novelist">Novelist</a>',
            'ampbyautomatic'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/amp/">AMP</a>',
            'wpreviewslider'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-facebook-reviews/">WP Review Slider</a>',
            'jetpackrecipe'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/jetpack/">JetPack Recipe</a>',
            'cmp'                         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/cmp-coming-soon-maintenance/">CMP – Coming Soon & Maintenance Plugin</a>',
            'wpecommerce'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-e-commerce/">WP eCommerce</a>',
            'wpreviewpro'                 => saswp_t_string('Requires').' <a target="_blank" href="https://mythemeshop.com/plugins/wordpress-review/">WP Review Pro</a>',
            'webstories'                  => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/web-stories/">Web Stories</a>',
            'simplejobboard'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/simple-job-board/">Simple Job Board</a>',
            'wpjobmanager'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-job-manager/">WP Job Manager</a>',
            'wpjobopenings'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-job-openings/">WP Job Openings</a>',
            'schemaforfaqs'               => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/faq-schema-markup-faq-structured-data/">FAQ Schema Markup</a>',
            'betteramp'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/better-amp/">Better AMP</a>',
            'wpamp'                       => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/wp-amp-accelerated-mobile-pages-for-wordpress-and-woocommerce/16278608">WP AMP</a>',
            'ampwp'                       => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/amp-wp/">AMP WP</a>',
            'kk_star_ratings'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/kk-star-ratings/">kk Star Rating</a>',
            'rmprating'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/rate-my-post">Rate my Post – WP Rating System</a>',
            'elementor'                   => saswp_t_string('Requires').' <a target="_blank" href="https://elementor.com/widgets/testimonial-widget/">Elementor Testimonial</a>',
            'ratingform'                  => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/rating-form/10357679/">Rating Form</a>',
            'simple_author_box'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/simple-author-box//">Simple Author Box</a>',
            'wp_post_ratings'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-postratings/">WP-PostRatings</a>',
            'bb_press'                    => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/bbpress/">bbPress</a>',
            'woocommerce'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/woocommerce/">Woocommerce</a>',
            'cooked'                      => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/cooked/">Cooked</a>',
            'the_events_calendar'         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/the-events-calendar/">The Events Calendar</a>',
            'yoast_seo'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wordpress-seo/">Yoast SEO</a>',
            'metatagmanager'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/meta-tag-manager/">Meta Tag Manager</a>',
            'slimseo'                     => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/slim-seo/">Slim SEO</a>',
            'rank_math'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/seo-by-rank-math/">WordPress SEO Plugin – Rank Math</a>',            
            'dw_qna'                      => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/dw-question-answer/">DW Question Answer</a>',
            'wpqa'                        => saswp_t_string('Requires').' <a target="_blank" href="https://2code.info/wpqa-builder/">WPQA Builder Plugin</a>',
            'sabaidiscuss'                => saswp_t_string('Requires').' <a target="_blank" href="https://sabaidiscuss.com">SabaiDiscuss</a>',
            'smart_crawl'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/smartcrawl-seo/">SmartCrawl Seo</a>',
            'the_seo_framework'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/autodescription/">The Seo Framework</a>',
            'seo_press'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-seopress/">SEOPress</a>',
            'aiosp'                       => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/all-in-one-seo-pack/">All in One SEO Pack</a>',
            'squirrly_seo'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/squirrly-seo/">Squirrly SEO</a>',          
            'wp_recipe_maker'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-recipe-maker/">WP Recipe Maker</a>',        
            'wpzoom'                      => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/recipe-card-blocks-by-wpzoom">Recipe Card Blocks by WPZOOM</a>',        
            'video_thumbnails'            => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/video-thumbnails/">Video Thumbnails</a>',        
            'featured_video_plus'         => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/featured-video-plus/">Featured Video Plus</a>',        
            'yotpo'                       => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/yotpo-social-reviews-for-woocommerce/">Yotpo: Product & Photo Reviews for WooCommerce</a>',
            'ryviu'                       => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/ryviu">Ryviu – Product Reviews for WooCommerce</a>',
            'starsrating'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/stars-rating">Stars Rating</a>',        
            'ultimate_blocks'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/ultimate-blocks">Ultimate Blocks – Gutenberg Blocks Plugin</a>',        
            'wptastyrecipe'               => saswp_t_string('Requires').' <a target="_blank" href="https://www.wptasty.com">WP Tasty Recipe</a>',
            'recipress'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/recipress">ReciPress</a>',
            'wp_ultimate_recipe'          => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-ultimate-recipe/">WP Ultimate Recipe</a>',
            'learn_press'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/learnpress/">Learn Press</a>',
            'learn_dash'                  => saswp_t_string('Requires').' <a target="_blank" href="https://www.learndash.com/pricing-and-purchase/">Learn Dash</a>',
            'lifter_lms'                  => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/lifterlms/">LifterLMS</a>',
            'senseilms'                   => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/sensei-lms/">Sensei LMS</a>',
            'wplms'                       => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/wplms-learning-management-system/6780226">WPLMS</a>',
            'wp_event_manager'            => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-event-manager/">WP Event Manager</a>',
            'events_manager'              => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/events-manager/">Events Manager</a>',
            'event_calendar_wd'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/event-calendar-wd/">Event Calendar WD</a>',
            'event_organiser'             => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/event-organiser/">Event Organiser</a>',
            'modern_events_calendar'      => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/modern-events-calendar-lite/">Modern Events Calendar</a>',
            'flex_mls_idx'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/flexmls-idx/">FlexMLS IDX</a>',        
            'woocommerce_membership'      => saswp_t_string('Requires').' <a target="_blank" href="https://woocommerce.com/products/woocommerce-memberships">Woocommerce Membership</a>',
            'woocommerce_bookings'        => saswp_t_string('Requires').' <a target="_blank" href="https://woocommerce.com/products/woocommerce-bookings">Woocommerce Bookings</a>',        
            'extra'                       => saswp_t_string('Requires').' <a target="_blank" href="https://www.elegantthemes.com/gallery/extra/">Extra Theme</a>',
            'homeland'                    => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/homeland-responsive-real-estate-theme-for-wordpress/6518965">Homeland</a>',            
            'ratency'                     => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/ratency-review-magazine-theme/21303977">Ratency - Review & Magazine Theme</a>',            
            'wpresidence'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wpresidence.net/">WP Residence</a>',            
            'reviews'                     => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/reviews-products-and-services-review-wp-theme/13004739?s_rank=4">Reviews - Products And Services Review WP Theme</a>',            
            'realhomes'                   => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/real-homes-wordpress-real-estate-theme/5373914">RealHomes</a>',
            'myhome'                      => saswp_t_string('Requires').' <a target="_blank" href="https://myhometheme.net/">My Home Theme</a>',
            'realestate_5'                => saswp_t_string('Requires').' <a target="_blank" href="https://myhometheme.net/">WP Pro Realstate 5</a>',
            'realestate_7'                => saswp_t_string('Requires').' <a target="_blank" href="https://myhometheme.net/">WP Pro Realstate 7</a>',
            'geodirectory'                => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/geodirectory/">GeoDirectory – Business Directory</a>',
            'classipress'                 => saswp_t_string('Requires').' <a target="_blank" href="https://www.appthemes.com/themes/classipress/">ClassiPress</a>',
            'taqyeem'                     => saswp_t_string('Requires').' <a target="_blank" href="https://codecanyon.net/item/taqyeem-wordpress-review-plugin/4558799">Taqyeem</a>',
            'wp_product_review'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/wp-product-review/">WP Product Review</a>',
            'stamped'                     => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/stampedio-product-reviews">Stamped.io Product Reviews & UGC for WooCommerce</a>',
            'soledad'                     => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/soledad-multiconcept-blogmagazine-wp-theme/12945398">Soledad Theme</a>',
            'enfold'                      => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/enfold-responsive-multipurpose-theme/4519990">Enfold Theme</a>',
            'zip_recipes'                 => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/zip-recipes/">Zip Recipes</a>',
            'mediavine_create'            => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/mediavine-create/">Create by Mediavine</a>',
            'ht_recipes'                  => saswp_t_string('Requires').' <a target="_blank" href="https://themeforest.net/item/culinier-food-recipe-wordpress-theme/11088564/">HT-Recipes</a>',
            'easy_testimonials'           => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/easy-testimonials">Easy Testimonials</a>',
            'bne_testimonials'            => saswp_t_string('Requires').' <a target="_blank" href="https://wordpress.org/plugins/bne-testimonials/">BNE Testimonials</a>',
            'testimonial_pro'             => saswp_t_string('Requires').' <a target="_blank" href="https://shapedplugin.com/plugin/testimonial-pro/">Testimonial Pro</a>',
            'tevolution_events'           => saswp_t_string('Requires').' <a target="_blank" href="https://templatic.com/wordpress-plugins/tevolution/">Tevolution Events</a>'
        
        );
          
    $active = saswp_compatible_active_list();
        
    if(!isset($active[$pname])){
        
        return $notes[$pname];
        
    }
    
}    

function saswp_get_category_link($term_id){
        
    $url = get_category_link($term_id);
        
    if ((function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint()) || function_exists( 'is_amp_endpoint' ) && is_amp_endpoint()) {  
    
        if(function_exists('ampforwp_url_controller')){
            
            $url = ampforwp_url_controller( $url );
            
        }
        
    }
    
    return $url;
        
}

function saswp_get_current_url(){
 
    $link = "http"; 
      
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){
        $link = "https"; 
    } 
  
    $link .= "://"; 
    $link .= $_SERVER['HTTP_HOST']; 
    $link .= $_SERVER['REQUEST_URI']; 
      
    return $link;
}

function saswp_has_slash($url){
 
    $response = false;
    
    if(strrev($url)[0]==='/') {
        $response = true;
    }
    
    return $response;
}

function saswp_remove_slash($url){
    
    $url = rtrim($url, '/\\');
    
    return $url;

}

function saswp_get_user_roles(){
    
        global $wp_roles;
        $allroles = array();
        
        foreach ( $wp_roles->roles as $key=>$value ){
            $allroles[esc_attr($key)] = esc_html($value['name']);
        }
        
        return $allroles;
}


function saswp_get_capability_by_role($role){
        
        $cap = apply_filters('saswp_default_manage_option_capability', 'manage_options' );
        
        switch ($role) {
            
            case 'wpseo_editor':
                $cap = 'edit_pages';                
                break;                  
            case 'editor':
                $cap = 'edit_pages';                
                break;            
            case 'author':
                $cap = 'publish_posts';                
                break;
            case 'contributor':
                $cap = 'edit_posts';                
                break;
            case 'wpseo_manager':
                $cap = 'edit_posts';                
                break;
            case 'subscriber':
                $cap = 'read';                
                break;

            default:
                break;
        }   
    
        return $cap;
    
}

function saswp_current_user_allowed(){
    
    global $sd_data;
    $currentuserrole = array();
    
    // if(!function_exists('wp_get_current_user')) {                
    //     require_once( ABSPATH . '/wp-includes/pluggable.php' );
    // } 

    if( ( function_exists('is_user_logged_in') && is_user_logged_in() )  && function_exists('wp_get_current_user') ) {
    
    $currentUser     = wp_get_current_user();        
    $saswp_roles     = isset($sd_data['saswp-role-based-access']) ? $sd_data['saswp-role-based-access'] : array('administrator');

    if($currentUser){
        
        if($currentUser->roles){
                $currentuserrole = (array) $currentUser->roles;
        }else{
            if( isset($currentUser->caps['administrator']) ){
                    $currentuserrole = array('administrator');
            }	
        }
        
        if( is_array($currentuserrole) ){

            $hasrole         = array_intersect( $currentuserrole, $saswp_roles );
        
            if( !empty($hasrole)){                                     
                return reset($hasrole);
            }

        }        

      }    
                
    }
    
    return false;
}

function saswp_current_user_can(){
        
        $capability = saswp_current_user_allowed() ? saswp_get_capability_by_role(saswp_current_user_allowed()) : 'manage_options';        
        return $capability;                    
}

function saswp_post_type_capabilities(){
        
        $caplist = array();
            
        // if(!function_exists('is_super_admin') || !function_exists('wp_get_current_user')) {                
        //     require_once( ABSPATH . '/wp-includes/capabilities.php' );
        //     require_once( ABSPATH . '/wp-includes/pluggable.php' );
        // }

        if( function_exists('is_super_admin') && function_exists('wp_get_current_user') ){

            $cap = saswp_current_user_can();

            if(!is_super_admin()){
        
                $caplist =  array(
                    'publish_posts'       => $cap,
                    'edit_posts'          => $cap,
                    'edit_others_posts'   => $cap,
                    'delete_posts'        => $cap,
                    'delete_others_posts' => $cap,
                    'read_private_posts'  => $cap,
                    'edit_post'           => $cap,
                    'delete_post'         => $cap,
                    'read_post'           => $cap,
                ); 
                
            }

        }        
        
        return $caplist;      
}

function saswp_get_image_by_url($url){
    
    $response = array();
    
    if($url){        
                
            $image_details      = @getimagesize($url);                    
            
            if($image_details){

                    $response['@type']  = 'ImageObject';
                    $response['url']    = $url;
                    $response['width']  = $image_details[0]; 
                    $response['height'] = $image_details[1];                   
                    
            }
                
    }
    
    return $response;
    
}

function saswp_get_image_by_id($image_id){
    
    $response = array();
    
    if($image_id){
        
            $image_details      = wp_get_attachment_image_src($image_id, 'full');                    
            
            if($image_details){
                
                    $response['@type']  = 'ImageObject';
                    $response['url']    = $image_details[0];
                    $response['width']  = $image_details[1]; 
                    $response['height'] = $image_details[2];                   
                    
            }
                
    }
    
    return $response;
    
}

function saswp_is_date_field($date_str){
    
    $response = false;
    
    if (strpos($date_str, 'date_modified')                    !== false 
        || strpos($date_str, 'date_published')                !== false
        || strpos($date_str, 'last_reviewed')                 !== false
        || strpos($date_str, 'date_posted')                   !== false
        || strpos($date_str, 'date_expires')                  !== false
        || strpos($date_str, 'published_date')                !== false
        || strpos($date_str, 'upload_date')                   !== false
        || strpos($date_str, 'qa_date_created')               !== false 
        || strpos($date_str, 'accepted_answer_date_created')  !== false 
        || strpos($date_str, 'suggested_answer_date_created') !== false 
        || strpos($date_str, 'priceValidUntil')               !== false
        || strpos($date_str, 'priceValidUntil')               !== false
        || strpos($date_str, 'priceValidUntil')               !== false
        || strpos($date_str, 'start_date')                    !== false
        || strpos($date_str, 'end_date')                      !== false
        || strpos($date_str, 'validfrom')                     !== false
        || strpos($date_str, 'dateposted')                    !== false
        || strpos($date_str, 'validthrough')                  !== false
        || strpos($date_str, 'date_of_birth')                 !== false
        || strpos($date_str, 'date_created')                  !== false
        || strpos($date_str, 'created_date')                  !== false
        ) {
            $response = true;
        }
    
    return $response;
    
}



function saswp_get_video_metadata($content = ''){
    
        global $post, $sd_data;
      
        $response = array();

        if(!$content){
            if(is_object($post)){
                $content = $post->post_content;
            }    
        }
       
        if(function_exists('has_block')){
            if( has_block('acf/video') ){
                $attributes = saswp_get_gutenberg_multiple_block_data('acf/video');    
                if(!empty($attributes)){  $attributes = $attributes;  }else{   $attributes = "";  }    
            }else{
                $attributes = "";
            }
            
            if(!empty($attributes)){
                $temp_aray = array(); 
                foreach($attributes as $match){
                    if(!empty($match['attrs']['data']['blok_vid_code'])){
                         $iframe_string = trim($match['attrs']['data']['blok_vid_code'], '"'); 
                         preg_match('/src="([^"]+)"/', $iframe_string, $match);
                         $vurl = $match[1];
                         if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                            continue;
                        }
                        if(!empty($vurl)){
                            $metadata['video_url'] = $vurl;                    
                            $response[] = $metadata;
                        }
                    }
                }
            }
        }
                                                           
         preg_match_all( '/\[video(.*?)\[\/video]/s', $content, $matches, PREG_SET_ORDER);
         
         if($matches){

             foreach ($matches as $match) {
                
                $mached = rtrim($match[0], '[/video]'); 
                $mached = ltrim($mached, '[');
                $mached = trim($mached, '[]');

                $attr = shortcode_parse_atts($mached);
                
                foreach ($attr as $key => $value) {

                    if(strpos($value, 'http')!== false){
                        $vurl = trim($value, '"');
                        if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                            continue;
                        }
                        $response[]['video_url'] = $vurl;

                    }
                }

             }
             
         }

         $pattern = get_shortcode_regex();
                  
         if ( preg_match_all( '/'. $pattern .'/s', $content, $matches )
            && array_key_exists( 2, $matches )
            && in_array( 'playlist', $matches[2] ) )
            {
             
              foreach ($matches[0] as $match){
            
                $mached = rtrim($match, ']'); 
                $mached = ltrim($mached, '[');
                $mached = trim($mached, '[]');
                $attr   = shortcode_parse_atts($mached);  

                if(isset($attr['ids'])){

                    $vurl = wp_get_attachment_url($attr['ids']);
                    if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                        continue;
                    }
                    $response[]['video_url'] = trim($vurl, '"');

                }
                                
              }
                          
            }
           
            preg_match_all( '/src\=\"(.*?)youtube(.*?)\"/s', $content, $youtubematches, PREG_SET_ORDER );
             
            if(!empty($youtubematches)){
             
                foreach($youtubematches as $match){
                   $vurl = trim($match[1].'youtu.be'.$match[2], '"');     
                   if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                     continue;
                   }             
                   $metadata   = array();  
                  
                   if(isset($sd_data['saswp-youtube-api']) && $sd_data['saswp-youtube-api'] != ''){
 
                     $vid = saswp_get_youtube_vid($vurl);
 
                     $video_meta = SASWP_Youtube::getVideoInfo($vid, $sd_data['saswp-youtube-api']);
 
                     if(!empty($video_meta)){
                         $metadata['title']      = $video_meta['title'];
                         $metadata['description']      = $video_meta['description'];
                         $metadata['viewCount']      = $video_meta['viewCount'];
                         $metadata['duration']      = $video_meta['duration'];
                         $metadata['uploadDate']      = $video_meta['uploadDate'];
                         $metadata['thumbnail_url'] = $video_meta['thumbnail']['sdDefault'];
                     }
 
                   }else{
                     $rulr     = 'https://www.youtube.com/oembed?url='.esc_attr($vurl).'&format=json';  
                     $result   = @wp_remote_get($rulr);                                    
                     $metadata = json_decode(wp_remote_retrieve_body($result),true);
                   }
                   
                   $metadata['video_url'] = $vurl;          
                   $response[] = $metadata;
                }/* end for */  
            } /* end if */

           preg_match_all( '/src\=\"(.*?)youtu\.be(.*?)\"/s', $content, $youtubematches, PREG_SET_ORDER );
             
           if(!empty($youtubematches)){
            
               foreach($youtubematches as $match){
                  $vurl = trim($match[1].'youtu.be'.$match[2], '"');     
                  if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                    continue;
                  }             
                  $metadata   = array();  
                 
                  if(isset($sd_data['saswp-youtube-api']) && $sd_data['saswp-youtube-api'] != ''){

                    $vid = saswp_get_youtube_vid($vurl);

                    $video_meta = SASWP_Youtube::getVideoInfo($vid, $sd_data['saswp-youtube-api']);

                    if(!empty($video_meta)){
                        $metadata['title']      = $video_meta['title'];
                        $metadata['description']      = $video_meta['description'];
                        $metadata['viewCount']      = $video_meta['viewCount'];
                        $metadata['duration']      = $video_meta['duration'];
                        $metadata['uploadDate']      = $video_meta['uploadDate'];
                        $metadata['thumbnail_url'] = $video_meta['thumbnail']['sdDefault'];
                    }

                  }else{
                    $rulr     = 'https://www.youtube.com/oembed?url='.esc_attr($vurl).'&format=json';  
                    $result   = @wp_remote_get($rulr);                                    
                    $metadata = json_decode(wp_remote_retrieve_body($result),true);
                  }
                  
                  $metadata['video_url'] = $vurl;          
                  $response[] = $metadata;
               }/* end for */  
           } /* end if */


           if(function_exists('has_block')){
                if( has_block('core-embed/youtube') ){
                    $attributes = saswp_get_gutenberg_multiple_block_data('core-embed/youtube');
                    if(!empty($attributes)){  $attributes = $attributes;  }else{   $attributes = "";  }    
                }else{
                       $attributes = ""; 
                 }

                if( has_block('core/embed') ){
                    $attributes = saswp_get_gutenberg_multiple_block_data('core/embed');  
                    if(!empty($attributes)){  $attributes = $attributes;  }else{   $attributes = "";  }    
                }else{  
                     $attributes = "";
                }
                
                if(!empty($attributes)){
                    $temp_aray = array(); 
                    foreach($attributes as $match){
                        if(!empty($match['attrs']['url'])){
                            $vurl = trim($match['attrs']['url'], '"'); 
                            if(strpos($vurl,'type') == true || strpos($vurl,'className') == true){
                                continue;
                            }
                           
                            $metadata = array();
                          
                            if(isset($sd_data['saswp-youtube-api']) && $sd_data['saswp-youtube-api'] != ''){
                               $vid = saswp_get_youtube_vid($vurl);
                                $video_meta = SASWP_Youtube::getVideoInfo($vid, $sd_data['saswp-youtube-api']);
                                if(!empty($video_meta)){
                                    $metadata['title']      = $video_meta['title'];
                                    $metadata['description']      = $video_meta['description'];
                                    $metadata['viewCount']      = $video_meta['viewCount'];
                                    $metadata['duration']      = $video_meta['duration'];
                                    $metadata['uploadDate']      = $video_meta['uploadDate'];
                                    $metadata['thumbnail_url'] = $video_meta['thumbnail']['sdDefault'];
                                }
                            }else{
                                $rulr     = 'https://www.youtube.com/oembed?url='.esc_attr($vurl).'&format=json';  
                                $result   = @wp_remote_get($rulr);                                    
                                $metadata = json_decode(wp_remote_retrieve_body($result),true); 
                            }
                            $metadata['video_url'] = $vurl;                  
                            $response[] = $metadata;
                            
                        }
                    }
                    
                }
            }
           
        $result = saswp_unique_multidim_array($response,'video_url');
       
        return $result;
}

function saswp_unique_multidim_array($array, $key) { 
    $temp_array = array(); 
    $i = 0; 
    $key_array = array(); 
    if(!empty($array) && !empty($key)){
        foreach($array as $val) { 
            if(!empty($val[$key])){    
                $checked = saswp_youtube_check_validate_url($val[$key]);
                if (!empty($checked)) {
                    if (!in_array($val[$key], $key_array)) { 
                        $key_array[$i] = $val[$key]; 
                        $temp_array[$i] = $val; 
                        $i++; 
                    } 
                }
            }
        } 
    }
    return $temp_array; 
}

function saswp_youtube_check_validate_url($yt_url) { 
    if(!empty($yt_url) && isset($yt_url)){
        $url_parsed_arr = parse_url($yt_url);
        if ($url_parsed_arr['host'] == "youtu.be" || $url_parsed_arr['host'] == "www.youtube.com" || $url_parsed_arr['path'] == "/watch" ) {
            return $yt_url;
        }else{
            return "";
        }
    }else{
        return "";
    }
}
  

function saswp_get_thumbnail(){

    global $thumbnail, $sd_data;

    if(!$thumbnail){

        $image_id 	        = get_post_thumbnail_id();	
        $image_details 	    = wp_get_attachment_image_src($image_id);

        if( isset($sd_data['saswp-video-thumbnails']) && $sd_data['saswp-video-thumbnails'] == 1 && class_exists('Video_Thumbnails') ){

            $thumbnail = get_video_thumbnail();

        }else if (isset($image_details[0])){

            $thumbnail = $image_details[0];

        }else{

            if(isset($sd_data['sd_default_image']['thumbnail'])){
                $thumbnail = $sd_data['sd_default_image']['thumbnail'];    
            }

        }

    }    

    return $thumbnail;

}

function saswp_remove_all_images($content){

    if($content){
        $content = preg_replace('/<img[^>]+./','', $content);   
    }
    
    return $content;

}

function saswp_update_global_post(){

  global $post, $redux_builder_amp, $saswp_post_data;
  
  if( (function_exists('ampforwp_is_front_page') && ampforwp_is_front_page()) && (function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint()) ){

    $page_id = ampforwp_get_the_ID();  
    
    if($page_id){

        if(!$saswp_post_data){
                $saswp_post_data = get_post($page_id);      
        }

         $post = $saswp_post_data;     

    }            
  }

}

add_filter('wpseo_metadesc', 'saswp_yoast_homepage_meta_desc', 10,2);

function saswp_yoast_homepage_meta_desc($description, $peresentation = false){

    global $saswp_yoast_home_meta;

    $saswp_yoast_home_meta = $description;

    return $description;
}	

function saswp_insert_schema_type($title){

  $postarr = array(
        'post_type'   =>'saswp',
        'post_title'  =>$title,
        'post_status' =>'publish',
  );

  $insertedPageId = wp_insert_post(  $postarr );

  if($insertedPageId){
    
      $post_data_array = array();                                       
      $post_data_array['group-0'] =array(
                                      'data_array' => array(
                                                  array(
                                                  'key_1' => 'post_type',
                                                  'key_2' => 'equal',
                                                  'key_3' => 'post',
                                        )
                                      )               
                                     );
      $post_data_array['group-1'] =array(
      'data_array' => array(
                array(
                'key_1' => 'post_type',
                'key_2' => 'equal',
                'key_3' => 'page',
        )
     )               
    );                               
   
  $schema_options_array = array('isAccessibleForFree'=>False,'notAccessibleForFree'=>0,'paywall_class_name'=>'');
  update_post_meta( $insertedPageId, 'data_group_array', $post_data_array);
  update_post_meta( $insertedPageId, 'schema_type', 'FAQ');
  update_post_meta( $insertedPageId, 'schema_options', $schema_options_array);

  }

  return $insertedPageId;

}

function saswp_get_posts_by_arg($arg){
      
    $response = array();

    $meta_query = new WP_Query($arg);        
            
      if($meta_query->have_posts()) {
           
          $data = array();  
          $post_meta = array();        
          while($meta_query->have_posts()) {
              $meta_query->the_post();
              $data['post_id']       =  get_the_ID();
              $data['post_title']    =  get_the_title();
              $data['post_status']   =  get_post_status();
              $data['post_modified'] =  get_the_date('M, d Y');
              $post_meta             = get_post_meta(get_the_ID(), '', true);
              if($post_meta){
                  foreach($post_meta as $key => $val ){
                      $post_meta[$key] = $val[0];
                  }
              }
              
              $posts_data[] = array(
              'post'        => (array) $data,
              'post_meta'   => $post_meta                
              ); 

          }
          wp_reset_postdata(); 
          $response['posts_data']  = $posts_data;
          $response['posts_found'] = $meta_query->found_posts;
      }

      return $response;

  }

function saswp_get_condition_list($condition, $search = '', $saved_data = ''){

    $choices      = array();  
    $array_search = false;  

    switch($condition){
    
      case "languages_polylang":
        
        $array_search = true;             
        $choices = apply_filters('saswp_set_languages_polylang_condition', $saved_data);
                                                       
        break;

       case "languages_wpml":
        
        $array_search = true;             
        $choices = apply_filters('saswp_set_languages_wpml_condition', $saved_data);
                                                        
        break;   

      case "post_type":
           
          $post_type = array();
          $args['public'] = true;
            
          if(!empty($search) && $search != null){                
            $args['name'] = $search; 
          }                     
          if($saved_data){
            $args['name'] = $saved_data; 
          }
          $choices = get_post_types( $args, 'names');    
          unset($choices['attachment'], $choices['amp_acf'], $choices['saswp-collections'], $choices['saswp_reviews'], $choices['saswp_reviews_server'], $choices['saswp'] );                    
          
          if($choices){
            foreach($choices as $key =>$value){
              $post_type[] = array('id' => $value, 'text' => $key);
            }
          }          
            
          $choices = $post_type;
        break;                         

      case "page_template" :
        $array_search = true;
        $choices[] = array('id' => 'default', 'text' => 'Default Template');

        $templates = get_page_templates();
        
        if($saved_data){
            $new_arr = array();
            foreach ($templates as $key => $value) {
                if($value == $saved_data){
                  $new_arr[$key] = $value;
                }
            }
            $templates = $new_arr;            
        }

        if($templates){
            
            foreach($templates as $k => $v){
                             
                 $choices[] = array('id' => $v, 'text' => $k);
          
            }
            
        }
        
        break;

      case "post" :
      case "page" :
        
        if($condition == 'page'){

          $post_types['page'] = 'page';

        }else{

          $post_types = get_post_types();                        
          unset( $post_types['page'], $post_types['attachment'], $post_types['revision'] , $post_types['nav_menu_item'], $post_types['acf'] , $post_types['amp_acf'],$post_types['saswp']  );

        }

        if( $post_types )
        {
          foreach( $post_types as $post_type ){
          
            $arg['post_type']      = $post_type;
            $arg['posts_per_page'] = 50;  
            $arg['post_status']    = 'any'; 

            if(!empty($search)){
              $arg['s']              = $search;
            }

            if($saved_data){
                $arg['p'] = $saved_data;  
            }
                
            $posts = saswp_get_posts_by_arg($arg);             
            
            if(isset($posts['posts_data'])){
                            
              foreach($posts['posts_data'] as $post){                                                          
                
                $choices[] = array('id' => $post['post']['post_id'], 'text' => $post['post']['post_title']);

              }
              
            }
            
          }
          
        }
        
        break;

      case "post_category" :

        $terms = array();
        $args = array( 
                    'hide_empty' => false,
                    'number'     => 50, 
                  );

        if(!empty($search)){
          $args['name__like'] = $search;
        }      
        if($saved_data){             
            $new_obj  = get_term($saved_data);
            $terms[0] = $new_obj;            
        }else{
            $terms = get_terms( 'category', $args);
        }   
        
        if( !empty($terms) ) {

          foreach( $terms as $term ) {

            $choices[] = array('id' => $term->term_id, 'text' => $term->name);                

          }

        }

        break;

      case "user_type" :

        global $wp_roles;

          $array_search = true;                 
          $general_arr = array();  
          $choices = $wp_roles->get_names();            

          if( is_multisite() ){
          
            $choices['super_admin'] = saswp_t_string('Super Admin');
            
          }

          if($saved_data){
            $new_arr = array();
            foreach ($choices as $key => $value) {
                if($key == $saved_data){
                  $new_arr[$key] = $value;
                }
            }
             $choices = $new_arr;            
          }
          
          if($choices){
            foreach($choices as $key =>$value){
              $general_arr[] = array('text' => $value, 'id' => $key);
            }
          }        
          $choices = $general_arr; 

      break;
      case "post_format" :
          $array_search = true;                 
          $general_arr = array();
          $choices = get_post_format_strings();

          if($saved_data){
            $new_arr = array();
            foreach ($choices as $key => $value) {
                if($key == $saved_data){
                  $new_arr[$key] = $value;
                }
            }
          $choices = $new_arr;            
         }

          if($choices){
            foreach($choices as $key =>$value){
              $general_arr[] = array('text' => $value, 'id' => $key);
            }
          }        
          $choices = $general_arr; 

      break;

      case "ef_taxonomy" :
        
        $args['public'] = true;

        if(!empty($search) && $search != null){                
            $args['name'] = $search; 
        }  
        if($saved_data){
            $args['name'] = $saved_data; 
        }      

        $taxonomies = get_taxonomies( $args, 'objects');
        
        if($taxonomies){
            
            if($taxonomies){
        
                foreach($taxonomies as $taxonomy) {                                      
                  $choices[] = array('id' => $taxonomy->name, 'text' => $taxonomy->labels->name);                  
                }
                  
              }

        }
                                     
        break;      

        case "homepage":
        case "author":
            $array_search = true; 
            $choices = array(
                array('id'  => 'true', 'text' => 'True'),
                array('id'  => 'false', 'text' => 'False')                                         
            );     
            
            if($saved_data == 'false'){
                $choices = array(                    
                    array('id'  => 'false', 'text' => 'False')                                         
                );     
            }
            if($saved_data == 'true'){
                $choices = array(
                    array('id'  => 'true', 'text' => 'True'),                    
                );     
            }
             
        break;      

        case "author_name":

        $authors = get_users('orderby=display_name&order=ASC');
        foreach ($authors as $author) {
               $choices[] = array('id'  => $author->ID, 'text' => $author->display_name);
        }               
        break;      

        case "all":

            $args = array( 
                'hide_empty' => false,
                'number'     => 50, 
            );

            if(!empty($search)){
                $args['name__like'] = $search;
            }

            $taxonomies =  get_terms( $args );               
            
            if($taxonomies){

                foreach($taxonomies as $tax){
                    $choices[] = array('id' => $tax->slug, 'text' => $tax->name);
                }
                
            }                        
             
        break;

        default:
        
        $args = array( 
            'hide_empty' => false,
            'number'     => 50, 
        );

        if(!empty($search)){
            $args['name__like'] = $search;
        }

        if($saved_data){                         
            $args['slug'] = $saved_data;
        }   
        $taxonomies    =  get_terms($condition, $args);  
                      
        if($taxonomies){

            foreach($taxonomies as $tax){

                if(is_object($tax)){
                    $choices[] = array('id' => $tax->slug, 'text' => $tax->name);
                }
                
            }
            
        }

    }        

    if(!empty($search) && $search != null){
        
        if($array_search){

            $search_data = array();

            foreach($choices as $val){
              if((strpos($val['id'], $search) !== false) || (strpos($val['text'], $search) !== false)){
                $search_data[] = $val; 
              }
            }

            $choices = $search_data;           

        }
        
        return array('results' => $choices);
    }else{
        return $choices;
    }    
 
}

/**
 * Get image url by specified $size
 * 
 * @since 1.9.43
 * 
 * @param  string|number $image_id    	The image id to get url
 * @param  string $size        			The specific image size
 * @param  array  $image_sizes 			Available image sizes for specified image id
 * @return string              			The image url
 */
function saswp_get_image_size_url( $image_id, $size = 'full', $image_sizes = array() ) {
    if ( isset( $image_sizes[ $size ] ) ) {
        if ( isset( $image_sizes[ $size ]['url'] ) ) {
            $image_url = $image_sizes[ $size ]['url'];
        } elseif ( isset( $image_sizes[ $size ]['source_url'] ) ) {
            $image_url = $image_sizes[ $size ]['source_url'];
        }
    }

    if ( function_exists( 'fly_get_attachment_image_src' ) ) {
        $thumb = fly_get_attachment_image_src( $image_id, $size );

        if ( $thumb ) {
            $image_url = isset( $thumb[0] ) ? $thumb[0] : $thumb['src'];
        }
    }

    if ( !isset( $image_url ) ) {
        $thumb = wp_get_attachment_image_src( $image_id, $size );
        $image_url = $thumb && isset( $thumb[0] ) ? $thumb[0] : '';
    }

    return $image_url;
}

function saswp_explode_comma_seprated ($data, $type) {

    $response = array();

    if($data){

        $area_served = explode(',', $data);

        if($area_served){

            foreach ($area_served as  $value) {
                $response[] = array(
                '@type' => $type,
                'name'  => $value
            );      
            }
            
        }

    }
    
    return $response;
}

function saswp_t_string($string){

    $settings       = saswp_defaultSettings();    

    if(function_exists('pll__') && (isset($settings['saswp-polylang']) && $settings['saswp-polylang'] == 1) ){
        return pll__($string);
    }else{
        return esc_html__( $string , 'schema-and-structured-data-for-wp');
    }
    
}

function saswp_get_elementor_widget_data($element_data, $widget_type){

    if ( null!=$element_data['elType'] ) {

      if ( 'widget' == $element_data['elType']) {

                if( $element_data['widgetType'] == $widget_type ){
                    return $element_type  = $element_data;
                }else{
                    return '';
                }
        
      } else {

                foreach($element_data['elements'] as $element_ot){
                    return saswp_get_elementor_widget_data($element_ot, $widget_type);
                }

      }
    }
}

function saswp_isset($str){

    $result = false;

    if(isset($str) && $str !=''){
        $result = true;
    }

    return $result;
}

function saswp_get_youtube_vid($url){

    $youtube_id = '';

    if( $url ){
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
        $youtube_id = isset($match[1]) ? $match[1] : '';
    }
    
    return $youtube_id;

}

function saswp_format_time_to_ISO_8601($string) {

    $response = '';   
    $exploded = explode(" ", trim($string));
     
    if( !empty($exploded) && is_array($exploded) ) {
        
        if(count($exploded) > 1){

            $response = 'PT';    
            $arr_count = count($exploded);
            $arr_count = $arr_count - 1;         
        
            for ($i=0; $i < $arr_count; $i++){
                
                if( strpos($exploded[($i+1)], 'hr') !== false || strpos($exploded[($i+1)], 'hour') !== false ){
                    $response .= $exploded[$i].'H';
                }
                if( strpos($exploded[($i+1)], 'min') !== false || strpos($exploded[($i+1)], 'minute') !== false ){
                    $response .= $exploded[$i].'M';
                }
                if( strpos($exploded[($i+1)], 'sec') !== false || strpos($exploded[($i+1)], 'second') !== false ){
                    $response .= $exploded[$i].'S';
                }

            }

        }        
                    
    }
    
    if($response){
        return $response;
    }
   
    return $string;
}

function saswp_prepend_schema_org( $short_str ){

    $response = '';

    switch (strtolower($short_str)) {
        
        case 'instock':
            $response = 'https://schema.org/InStock';    
        break;

        case 'soldout':
            $response = 'https://schema.org/SoldOut';    
        break;

        case 'presale':
            $response = 'https://schema.org/PreSale';    
        break;

        case 'onlineonly':
            $response = 'https://schema.org/OnlineOnly';    
        break;

        case 'limitedavailability':
            $response = 'https://schema.org/LimitedAvailability';    
        break;

        case 'instoreonly':
            $response = 'https://schema.org/InStoreOnly';    
        break;

        case 'outofstock':
            $response = 'https://schema.org/OutOfStock';    
        break;
        
        case 'discontinued':
            $response = 'https://schema.org/Discontinued';    
        break;
       
        case 'onbackorder':
            $response = 'https://schema.org/BackOrder';    
        break;
        case 'preorder':
            $response = 'https://schema.org/PreOrder';
        break;

        default:
            
            break;
    }

    return $response;
}

function saswp_get_page_range($current, $max, $total_pages = 5) {

    $desired_pages = $max < $total_pages ? $max : $total_pages;

    $middle = ceil($desired_pages/2);
    if ($current <= $middle){
        return [1, $desired_pages];
    }
    if ($current > $middle && $current <= ($max - $middle)) {
        return [
            $current - $middle,
            $current + $middle
        ];
    }
    if ($current <= $max ) {
        return [
            $current - ($desired_pages - 1),
            $max
        ];
    }

}
function saswp_get_post_meta( $post_id, $key=null, $single = null ){
    
        if( (isset($_GET['tag_ID'] ) && is_admin()) || (is_tag() || is_tax() || is_category()) ){
            return get_term_meta($post_id, $key, $single);
        }else{
            return get_post_meta($post_id, $key, $single);
        }                        
}
function saswp_update_post_meta( $post_id, $meta_key, $meta_value ){
    
    if((!empty($_POST['tag_ID']) || !empty($_GET['tag_ID'])) && is_admin()){      
        return update_term_meta($post_id, $meta_key, $meta_value);        
    }else{
        return update_post_meta($post_id, $meta_key, $meta_value);
    }    
}

function saswp_delete_post_meta( $post_id, $meta_key, $meta_value = null ){
    
        if(!empty($_POST['tag_ID']) && is_admin()){
            return delete_term_meta( $post_id, $meta_key, $meta_value );            
        }else{
            return delete_post_meta( $post_id, $meta_key, $meta_value );
        }        
}
function saswp_get_the_ID(){

    $id =  get_the_ID();

    if( is_tag() || is_tax() || is_category() ){
        $id = get_queried_object_id();
    }    
    
    return $id;
}

/**
 * Internal helper function to sanitize a string from user input or from the db
 *
 * @since 1.9.94
 * @copied from wordpress 4.7.0 core to make compatible sanitize_textarea_field with WordPress v4.6.3
 *
 * @param string $str           String to sanitize.
 * @param bool   $keep_newlines Optional. Whether to keep newlines. Default: false.
 * @return string Sanitized string.
 */
function saswp_sanitize_textarea_field( $str ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}

	$str = (string) $str;

	$filtered = wp_check_invalid_utf8( $str );

	if ( strpos( $filtered, '<' ) !== false ) {
		$filtered = wp_pre_kses_less_than( $filtered );
		// This will strip extra whitespace for us.
		$filtered = wp_strip_all_tags( $filtered, false );

		// Use HTML entities in a special case to make sure no later
		// newline stripping stage could lead to a functional tag.
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}
	
	$filtered = trim( $filtered );

	$found = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}

	if ( $found ) {
		// Strip out the whitespace that may now exist after removing the octets.
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}

	return $filtered;
}