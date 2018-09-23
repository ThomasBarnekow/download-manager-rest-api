<?php
/*
Plugin Name: Download Manager REST API
Description: RESTful API for Download Manager
Version: 0.2.0
Author: Thomas Barnekow
 */

function dlm_rest_activate()
{
  flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'dlm_rest_activate');

function dlm_rest_register_routes()
{
  $namespace = '/download-manager/v1';

  $downloads_controller = new DLM_REST_Downloads_Controller($namespace);
  $downloads_controller->register_routes();

  $versions_controller = new DLM_REST_Versions_Controller($namespace);
  $versions_controller->register_routes();
}
add_action('rest_api_init', 'dlm_rest_register_routes');
