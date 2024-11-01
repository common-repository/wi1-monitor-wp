<?php

/*
Plugin Name: WI1 WP Plugin
Description: Gathers the WordPress, Themes and Plugin versions and send to WI1 to be used in the dashboard.
Author: Jumping Giraffe
Author URI: https://jumpinggiraffe.com
Plugin URI: https://WI1.com
Requries at least: 4.3
Version: 1.1
License: GPL2
 
WI1 WP Plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
WI1 WP Plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with WI1 WP Plugin. If not, see {License URI}.
 */

define("WI_REQUEST_URI", "https://app.wi1.com/tools/wp-plugin-check/WI1_WP_plugin_check.php"); //URL to make server request

//Updating Status Activte - SC what to run when plugin made active. __FILE__ = this file, replace if using another file.
register_activation_hook(__FILE__, 'cwi_on_activation');

function cwi_on_activation() {
    wp_schedule_event(time(), 'hourly', 'cwi_update_status');
}

//Disabling Updating - SC what to run when plugin deactivated.
register_deactivation_hook(__FILE__, 'cwi_on_deactivate');

function cwi_on_deactivate() {
    wp_clear_scheduled_hook('cwi_update_status');
}

add_action('cwi_update_status', 'cwi_update_status_cb',500);

function cwi_update_status_cb() {
    set_time_limit(0);
    $timestamp = time();
    require_once ABSPATH . "/wp-admin/includes/plugin.php";
    require_once ABSPATH . "/wp-admin/includes/theme.php";
    //Theme Section
    $themes = wp_get_themes();
    $response = get_site_transient('update_themes')->response;
    $current_theme = wp_get_theme();
    
    $filter_themes = array();
    foreach ($themes as $theme){
        $stylesheet = $theme->get_stylesheet();
        $require_update = 0;
        $next_version = 0;
        if(isset($response[$stylesheet])){
            $require_update = 1;
            $next_version = $response[$stylesheet];
            $next_version = $next_version['new_version'];
        }
        $filter_themes[] = array(
            "stylesheet"    => $stylesheet ,
            "version"   => $theme->display("Version"),
            "next_version"  => $next_version,
            "is_active" => ($stylesheet == $current_theme->get_stylesheet())?1:0,
            "require_update"    =>  $require_update,
            "theme_name"    => $theme->display("Name")
            
        );
    }
    //Plugin Section
    $plugins = get_plugins();
    $domain = site_url();
	
    $filter_plugins = array();
    //$plugins_dir = WP_PLUGIN_DIR;
    foreach ($plugins as $file => $plugin) {        
        if(preg_match("%(.*)/.*\.php$%", $file,$match)){
            $slug = $match[1];
        }
        $is_active = 0;
        if(is_plugin_active($file)){
            $is_active = 1;
        }
        $currentVersion = $plugin['Version'];
        $url = "http://api.wordpress.org/plugins/info/1.0/$slug/";
        $html = @file_get_contents($url);
        $unserialize = unserialize($html);
        $nextVersion=0;
        if(isset($unserialize->version)){
            $nextVersion = $unserialize->version;
        }
        $require_update = 0;
        if(!empty($nextVersion) && !empty($currentVersion) && version_compare($nextVersion, $currentVersion)){
            $require_update = 1;
        }
        $filter_plugins[] = array(
            "slug" => $slug,
            "version" => $plugin['Version'],
            "next_version" => $nextVersion,
            "is_active" => $is_active,
            "require_update" => $require_update,
            "plugin_name" => $plugin['Name']
        );
    }
    global $wp_version;
    $info = array(
        "domain" => $domain,
        "plugins_count" => count($plugins),
        "themes_count"  => count($filter_themes),
        'wp_version'    =>  $wp_version,
        "timestamp" => $timestamp,
        "plugins" => $filter_plugins,
        "themes"  => $filter_themes,
        "action" => "do_add"
    );
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WI_REQUEST_URI);
    curl_setopt($ch, CURLOPT_POST, count($info));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($info));
    $result = curl_exec($ch);
    curl_close($ch);
}

add_action('init','cwpi_init');

function cwpi_init(){
    if(!wp_next_scheduled('cwi_update_status')){
         wp_schedule_event(time(), 'hourly', 'cwi_update_status');
    }
}

add_action('admin_enqueue_scripts','cwpi_add_admin_scripts');


function cwpi_add_admin_scripts(){
    wp_enqueue_script('function', plugins_url("/public/js/functions.js", __FILE__) , array('jquery'));
}

add_action('wp_ajax_wi_send_notice','cwpi_send_notices');

function cwpi_send_notices(){
    
    $post = array(
        'type'  => filter_input(INPUT_POST,'type',FILTER_SANITIZE_STRING ),
        'message'   => trim(filter_input(INPUT_POST,'message',FILTER_SANITIZE_STRING )),
        'action'    =>  'admin_notices',
        'hash'      => md5(trim(filter_input(INPUT_POST,'message',FILTER_SANITIZE_STRING ))),
        'domain'    => site_url()
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WI_REQUEST_URI);
    curl_setopt($ch, CURLOPT_POST, count($post));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_exec($ch);
    curl_close($ch);
    die();
}
//Debug Only
//add_action('init', 'cwi_update_status_cb');

$file = 'WI1_WP_error.txt';
$stu_error1 = file_get_contents($file);
$stu_error2 = "WI1 WP Error here: ".$filter_themes["next_version"];
file_put_contents($file, $stu_error1);
