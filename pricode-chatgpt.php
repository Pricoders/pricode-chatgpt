<?php
/**
 * Plugin Name:     Pricode Chatgpt
 * Plugin URI:      Plugin for automated content generation for WordPress
 * Description:     Create your blog posts automatically from chatgpt
 * Author:          Alejandro Giraldo
 * Author URI:      https://pricode.io
 * Text Domain:     pricode-chatgpt
 * Domain Path:     /languages
 * Version:         0.00000001
 *
 * @package         Pricode_Chatgpt
 */

require 'vendor/autoload.php';


function pricode_chatgpt_init(){
    $yourApiKey = get_option('pricode_chatgpt_api_key');
    return $client = OpenAI::client($yourApiKey);    
}



function pricode_chatgpt_get_content( $prompt ){
    $client = pricode_chatgpt_init();
    $response = null;
    try{
        $response = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt ],
            ],
        ]);        
    }catch(\Exception $e){
        echo $e->getMessage();
    }
    

    return $response;
}

function pricode_chatgpt_create_post( $prompt ){
    
    $content = pricode_chatgpt_get_content( $prompt );
    
    $blog_response = json_decode($content->choices[0]->message->content);
    
    error_log( print_r($blog_response,true ) );
    // blog data 
    if( isset($blog_response->title) && is_string($blog_response->title) ){
        $title = $blog_response->title;
    }

    $content = ''; 
    
    if( isset( $blog_response->content ) && is_array( $blog_response->content ) ){
        foreach ($blog_response->content as $key => $entry) {
            error_log( print_r($entry,true ) );
            if( isset( $entry->heading ) ){
                $content .= '<h2>' . $entry->heading . '</h2>';    
            }
            if( isset($entry->paragraphs) && is_array($entry->paragraphs) ){
                foreach($entry->paragraphs  as $key => $paragraph){
                    $content .= '<p>' . $paragraph . '</p>';
                }    
            }
        }    
    }
    
    $new_post = array(
      'post_title'    => wp_strip_all_tags( $title ),
      'post_content'  => $content,
      'post_status'   => 'publish',
      'post_author'   => 1
    );
    return wp_insert_post( $new_post );
    
}


function pricode_chatgpt_menus() {

    //Settings page
    add_menu_page(
        __( 'Pricode ChatGPT', 'pricode-chatgpt' ),
        'Pricode ChatGPT',
        'manage_options',
        'pricode-chatgpt-settings',
        'pricode_chatgpt_settings_callback'
    );

    //Submenu pages
    add_submenu_page(
        'pricode-chatgpt-settings',
        __( 'Publish Random Post', 'pricode-chatgpt' ),
        __( 'Publish Random Post', 'pricode-chatgpt' ),
        'manage_options',
        'pricode-chatgpt-publish-post',
        'pricode_chatgpt_publish_post_callback'
    );

    //Register settings
    add_action( 'admin_init', 'pricode_chatgpt_register_settings' );

}
add_action( 'admin_menu', 'pricode_chatgpt_menus' );

function pricode_chatgpt_register_settings(){

    //register our settings
    register_setting( 'pricode-chatgpt-settings-group', 'pricode_chatgpt_api_key' );
}

function pricode_chatgpt_settings_callback(){
    ?>
    <h1>Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'pricode-chatgpt-settings-group' ); ?>
        <?php do_settings_sections( 'pricode-chatgpt-settings-group' ); ?>
        <input type="password" name="pricode_chatgpt_api_key" value="<?php echo esc_attr( get_option('pricode_chatgpt_api_key') ); ?>" />
        <?php submit_button(); ?>
    </form>
    <?php
}

function pricode_chatgpt_publish_post_callback(){
    ?>
    <h1>Chat with ChatGPT</h1>
    <form id="pricode-chatgpt-form">
        <p><input type="text" class="prompt" name="prompt" id="pricode-chatgpt-prompt" maxlength="200" placeholder='Hi can you please write a blog about...'/></p>
        <?php wp_nonce_field('pricode_publish_post', 'pricode_publish_post_nonce') ?>
        <p><button id="publish-post-button">Publish post</button></p>
        <small id="chatgpt-response"><b>Response:</b> <span></span></small>
    </form>
    
    <script>
        jQuery('#pricode-chatgpt-form').submit(function(event){
            event.preventDefault();
            jQuery('#publish-post-button').attr('disable',true);
            data = {
                action: 'pricode_publish_post',
                data: jQuery('#pricode-chatgpt-form').serialize()
            }
            
            jQuery.post('<?php echo admin_url( 'admin-ajax.php' ) ?>', data, function(response){
                jQuery('#chatgpt-response span').empty();
                jQuery('#chatgpt-response span').removeClass('error');
                jQuery('#chatgpt-response span').removeClass('success');
                jQuery('#chatgpt-response span').html(response.message);
                if(response.success){
                    jQuery('#chatgpt-response span').addClass('success');
                }else{
                    jQuery('#chatgpt-response span').addClass('error');
                }
                jQuery('#publish-post-button').attr('disable',false);
            }, 'json');
        })
    </script>
    <style>
        .error{color: red}
        .success{color: green}
        .prompt{width: 90%}
    </style>
    <?php

}
add_action('wp_ajax_pricode_publish_post', 'pricode_chatgpt_ajax_response');

function pricode_chatgpt_ajax_response(){
    parse_str($_POST['data'], $data);
    if ( ! wp_verify_nonce($data['pricode_publish_post_nonce'], 'pricode_publish_post') ){
        return wp_send_json( ['success' => false, 'message' => 'Incorrect data'] );    
    }
    if( empty( $data['prompt'] ) ) {
        return wp_send_json( ['success' => false, 'message' => 'empty prompt'] );    
    }

    $new_post_id = pricode_chatgpt_create_post( trim($data['prompt']) . '. Please use the following json format { title, content: [ { heading, [paragraphs] } ] }' );
    if( is_wp_error( $new_post_id ) ){
        return wp_send_json( ['success' => false, 'message' => 'Something went wrong'] );    
    }
    pricode_chatgpt_create_image( trim($data['prompt']), $new_post_id );
    return wp_send_json( ['success' => true, 'message' => 'Post Created successfully!'] );

}


function pricode_chatgpt_create_image( $prompt, $new_post_id ){

    $client = pricode_chatgpt_init();
    $response = null;
    try{
        $response = $client->images()->create([
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
        ]);
        
        return pricodechatgpt_wp_save_image($response->data[0]->url, $new_post_id);
    }catch(\Exception $e){
        echo $e->getMessage();
    }
    
    

}

function pricodechatgpt_wp_save_image( $imageurl, $new_post_id ){
    include_once( ABSPATH . 'wp-admin/includes/image.php' );
    $imagetype = end(explode('/', getimagesize($imageurl)['mime']));
    $uniq_name = date('dmY').''.(int) microtime(true); 
    $filename = $uniq_name.'.'.$imagetype;

    $uploaddir = wp_upload_dir();
    $uploadfile = $uploaddir['path'] . '/' . $filename;
    $contents = file_get_contents($imageurl);
    $savefile = fopen($uploadfile, 'w');

    //Saving the image 
    fwrite($savefile, $contents);
    fclose($savefile);

    $wp_filetype = wp_check_filetype(basename($filename), null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => $filename,
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attachment_id = wp_insert_attachment( $attachment, $uploadfile );
    $imagenew = get_post( $attachment_id );
    $fullsizepath = get_attached_file( $imagenew->ID );
    $attach_data = wp_generate_attachment_metadata( $attachment_id, $fullsizepath );
    wp_update_attachment_metadata( $attachment_id, $attach_data ); 
    set_post_thumbnail($new_post_id, $attachment_id);
    return $attachment_id;    
}

