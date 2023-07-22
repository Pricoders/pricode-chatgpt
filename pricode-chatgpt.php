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
    $yourApiKey = '';
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

function pricode_chatgpt_create_post(){
    $prompt = 'Hi, please tell me how to cook carbonara pasta, but please make a blog entry for this topic, in a json format { title, content: [ { heading, [paragraphs] } ] }';
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
    wp_insert_post( $new_post );
    
}

