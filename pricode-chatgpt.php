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

function pricode_chatgpt_create_post( $prompt, $json_format = false, $prefix = false ){
    if( $prefix ){
        $prompt = "Hi can you please write a blog about " . $prompt; 
    }
    if( $json_format ){
        $prompt .= $prompt . '. Please use the following json format { title, content: [ { heading, [paragraphs] } ] }';
    }

    $content = pricode_chatgpt_get_content( $prompt );
    
    $blog_response = json_decode($content->choices[0]->message->content);
    
    // blog data 
    if( isset($blog_response->title) && is_string($blog_response->title) ){
        $title = $blog_response->title;
    }

    $content = ''; 
    
    if( isset( $blog_response->content ) && is_array( $blog_response->content ) ){
        foreach ($blog_response->content as $key => $entry) {
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
      'post_status'   => 'draft',
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

    // Topics
    add_submenu_page(
        'pricode-chatgpt-settings',
        __( 'Topics', 'pricode-chatgpt' ),
        __( 'Topics', 'pricode-chatgpt' ),
        'manage_options',
        'pricode-chatgpt-topics',
        'pricode_chatgpt_publish_topics_callback'
    );
    //Logs
    add_submenu_page(
        'pricode-chatgpt-settings',
        __( 'Logs', 'pricode-chatgpt' ),
        __( 'Logs', 'pricode-chatgpt' ),
        'manage_options',
        'pricode-chatgpt-logs',
        'pricode_chatgpt_publish_logs_callback'
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

    return pricode_chatgpt_create_new_post_and_image( $prompt );

}

function pricode_chatgpt_create_post_and_image( $topic ){
    $prompt = $topic->topic;
    $new_post_id = pricode_chatgpt_create_post($prompt, true, true );
    
    if( is_wp_error($new_post_id) ){
        pricode_chatgpt_insert_log('pricode_chatgpt_create_post_and_image', 0, 'Post was not created');
        return wp_send_json( [ 'success' => false, 'message' => 'Oops, something happened. Post was not created' ] );
    }
    //Adding image
    $attachment_id = pricode_chatgpt_set_new_image_new_post( $prompt, $new_post_id );
    if( is_wp_error($attachment_id) ){
        pricode_chatgpt_insert_log('pricode_chatgpt_create_post_and_image', 0, 'Images was not created');
        return wp_send_json( [ 'success' => false, 'message' => 'Oops, something happened. Image was not created' ] );
    }
    pricode_chatgpt_send_notification( $new_post_id );
    pricode_chatgpt_insert_log('pricode_chatgpt_create_post_and_image', 1, 'Post and Iamge was created');

    pricode_chatgpt_update_topic_time( $topic );
    return wp_send_json( [ 'success' => true, 'message' => 'New post and image created!' ] );

}

function pricode_chatgpt_set_new_image_new_post( $prompt, $new_post_id ){

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
    $mimetype = getimagesize($imageurl)['mime'];
    $types = explode( '/', $mimetype );
    $imagetype = end( $types );
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
    if( !is_wp_error( $attachment_id ) ){
        $imagenew = get_post( $attachment_id );
        $fullsizepath = get_attached_file( $imagenew->ID );
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $fullsizepath );
        wp_update_attachment_metadata( $attachment_id, $attach_data ); 
        set_post_thumbnail($new_post_id, $attachment_id);
    }
    return $attachment_id;    
}
function pricode_chatgpt_activate() {
    pricode_chatgpt_db_deltas();
}
register_activation_hook( __FILE__, 'pricode_chatgpt_activate' );

add_action( 'pricode_chatgpt_cron_youtube', 'pricode_chatgpt_run_cron' );
if ( !wp_next_scheduled ( 'pricode_chatgpt_cron_youtube' ) ) {
    wp_schedule_event( time(), 'daily', 'pricode_chatgpt_cron_youtube' );
}


function pricode_chatgpt_run_cron(){

    $topic = pricode_chatgpt_get_random_topic();
    if( !empty($topic[0]->topic) ){

        pricode_chatgpt_insert_log('pricode_chatgpt_run', 1, 'Cronjob has been started');
        return pricode_chatgpt_create_post_and_image( $topic[0] );    
    }
    
}

function pricode_chatgpt_cron_schedules( $schedules ) {
    $schedules['three_mins'] = array(
        'interval' => 180,
        'display' => __('3 mins')
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'pricode_chatgpt_cron_schedules' );

function pricode_chatgpt_send_notification( $new_post_id ){
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $admin_email = get_option( 'admin_email' );
    $title = "ChatGPT just created a post for your to review!";
    $message = "Hey there is a new post created by chatgpt!!! here you can take a look of it >>> <a taget='_blank' href='" . admin_url("post.php?post=$new_post_id&action=edit") . "'> Edit post!</a>";
    wp_mail($admin_email, $title, $message, $headers);
}

function pricode_chatgpt_topcis_post_type() {
    $args = array(
        'public'    => false,
        'label'     => __( 'Topics', 'textdomain' ),
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'show_ui' => true,
        'supports' => ['title', 'custom-fields']

    );
    register_post_type( 'topic', $args );
}
// add_action( 'init', 'pricode_chatgpt_topcis_post_type' );


function pricode_chatgpt_publish_logs_callback(){
    $logs = pricode_chatgpt_get_logs();
    include_once 'templates/logs.php';
}

function pricode_chatgpt_publish_topics_callback(){
    $topics = pricode_chatgpt_get_topics();
    include_once 'templates/topics.php';
}


register_activation_hook( __FILE__, 'pricode_chatgpt_db_deltas' );
function pricode_chatgpt_db_deltas(){
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( pricode_chatgpt_sql_log_create( $wpdb->prefix . 'pricode_chatgpt_logs' ) );
    dbDelta( pricode_chatgpt_sql_topic_create( $wpdb->prefix . 'pricode_chatgpt_topics' ) );

}
function pricode_chatgpt_sql_log_create( $table_name ){
    global $wpdb;
    return 
    "CREATE TABLE {$table_name} (
        id mediumint(8) unsigned NOT NULL auto_increment ,
        event_name varchar(100) NOT NULL,
        event_type BOOL NOT NULL,
        description TEXT NULL,
        created_at INT NOT NULL,
        PRIMARY KEY  (id)
        )
    COLLATE {$wpdb->collate}";
}
function pricode_chatgpt_sql_topic_create( $table_name ){

    global $wpdb;
    return 
    "CREATE TABLE {$table_name} (
        id mediumint(8) unsigned NOT NULL auto_increment ,
        topic varchar(100) NOT NULL,
        last_published INT NULL default(0),
        created_at INT NOT NULL,
        updated_at INT NOT NULL,
        PRIMARY KEY  (id)
        )
    COLLATE {$wpdb->collate}";

}

register_deactivation_hook( __FILE__, 'pricode_chatgpt_db_delta_remove' );

function pricode_chatgpt_db_delta_remove(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_logs';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}");
    $table_name = $wpdb->prefix . 'pricode_chatgpt_topics';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}");
    wp_clear_scheduled_hook( 'pricode_chatgpt_cron' );
}


function pricode_chatgpt_insert_log( $name, $type, $description ){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_logs';
    $wpdb->insert($table_name, array(
        'event_name' => $name,
        'event_type' => $type,
        'description' => $description,
        'created_at' => time()
    ));

}

function pricode_chatgpt_insert_topic( $topic ){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_topics';
    return $wpdb->insert($table_name, array(
        'topic' => $topic,
        'created_at' => time(),
        'updated_at' => time()
    ));
}   


function pricode_chatgpt_get_logs(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_logs';
    $logs = $wpdb->get_results(
        "
            SELECT * 
            FROM {$table_name}
            LIMIT 50
        "
    );

    return $logs;

}
function pricode_chatgpt_get_topics(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_topics';
    $topics = $wpdb->get_results(
        "
            SELECT * 
            FROM {$table_name}
            LIMIT 50
        "
    );

    return $topics;

}
function pricode_chatgpt_get_random_topic(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'pricode_chatgpt_topics';
    $time = time();
    $topic = $wpdb->get_results(
        "
            SELECT * 
            FROM {$table_name}
            WHERE ( {$time} - last_published ) > 86400
            ORDER BY rand() 
            LIMIT 1
        "
    );

    return $topic;

}
function pricode_chatgpt_update_topic_time( $topic ){
    global $wpdb;

    $table_name = $wpdb->prefix . 'pricode_chatgpt_topics';
    $wpdb->update(
        $table_name,
        array(
            'last_published' => time(), // string
        ),
        array( 'id' => $topic->id ),
    );
}


add_action('wp_ajax_pricode_add_topic', 'pricode_chatgpt_add_topic_callback');

function pricode_chatgpt_add_topic_callback(){
    parse_str($_POST['data'], $data);
    error_log( print_r( $data, true ) );
    if ( ! wp_verify_nonce($data['pricode_add_topic_nonce'], 'pricode_add_topic') ){
        return wp_send_json( ['success' => false, 'message' => 'Incorrect data'] );    
    }
    if( empty( $data['new_topic'] ) ) {
        return wp_send_json( ['success' => false, 'message' => 'empty prompt'] );    
    }

    $return =  pricode_chatgpt_insert_topic( $data['new_topic'] );
    error_log( print_r( $return, true ) );
    if( is_wp_error( $return ) ){
        return wp_send_json( ['success' => false, 'message' => 'error'] );    
    }else{
        return wp_send_json( ['success' => true, 'message' => 'New Topic added'] );    
    }


}