<?php

require_once dirname(__FILE__) . '/DLM_REST_Controller.php';

/**
 * The DLM REST Download Controller.
 */
class DLM_REST_Downloads_Controller extends DLM_REST_Controller
{
  public function __construct($namespace)
  {
    $this->namespace = $namespace;
    $this->resource_name = 'downloads';
  }

  /**
   * Register REST routes.
   */
  public function register_routes()
  {
    register_rest_route(
      $this->namespace,
      '/' . $this->resource_name,
      array(
        array(
          'methods' => 'GET',
          'callback' => array($this, 'get_items'),
          'permission_callback' => array($this, 'get_items_permissions_check')
        ),
        array(
          'methods' => 'POST',
          'callback' => array($this, 'create_item'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args' => array(
            'title' => array(
              'required' => true,
              'type' => 'string',
              'description' => 'The download title, which will be converted into a slug'
            )
          )
        )
      )
    );
  }

  /**
   * Retrieves a collection of downloads.
   * 
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
   */
  public function get_items($request)
  {
    $downloads = download_monitor()->service('download_repository')->retrieve();
    return new WP_REST_Response(array_map(array($this, 'download_to_array'), $downloads), 200);
  }

  /**
   * Creates a download.
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
   */
  public function create_item($request)
  {
    // Make sure there is no download with the given title
    $downloads = download_monitor()
      ->service('download_repository')
      ->retrieve(array(
        'title' => $request['title']
      ));

    if (array_shift($downloads) !== null) {
      return new WP_Error(
        'dlm_rest_download_exists',
        'A download with the given title already exists.',
        array('status' => 400)
      );
    }

    // Instantiate and initialize new download.
    $download = new DLM_Download();
    $download->set_title($request['title']);
    $download->set_author(get_current_user_id());
    $download->set_status('publish');

    try {
      // Store new download in WordPress database.
      download_monitor()->service('download_repository')->persist($download);

      // Retrieve again to get all relevant values that were computed by WordPress
      // but not stored in our existing object.
      $download = download_monitor()
        ->service('download_repository')
        ->retrieve_single($download->get_id());
    } catch (Exception $ex) {
      return new WP_Error(
        'dlm_rest_exception',
        $ex->getMessage(),
        array('status' => 500)
      );
    }

    return new WP_REST_Response($this->download_to_array($download), 201);
  }

  /**
   * Converts the given download to an array that can be returned in a
   * REST response.
   * 
   * @param DLM_Download $download The download.
   * @return array The corresponding associative array.
   */
  private function download_to_array($download)
  {
    return array(
      'id' => $download->get_id(),
      'status' => $download->get_status(),
      'title' => $download->get_title(),
      'slug' => $download->get_slug(),
      'author' => (int)$download->get_author(),
      'downloadLink' => $download->get_the_download_link()
    );
  }
}
