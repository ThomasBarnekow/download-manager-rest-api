<?php

require_once dirname(__FILE__) . '/DLM_REST_Controller.php';

/**
 * The DLM REST Versions Controller.
 */
class DLM_REST_Versions_Controller extends DLM_REST_Controller
{
  public function __construct($namespace)
  {
    $this->namespace = $namespace;
    $this->resource_name = 'versions';
  }

  /**
   * Register REST routes.
   */
  public function register_routes()
  {
    register_rest_route(
      $this->namespace,
      '/downloads/(?P<downloadId>\d+)/' . $this->resource_name,
      array(
        array(
          'methods' => 'GET',
          'callback' => array($this, 'get_items'),
          'permission_callback' => array($this, 'get_items_permissions_check'),
          'args' => array(
            'downloadId' => array(
              'required' => true,
              'type' => 'integer',
              'description' => 'The parent dlm_download post ID, e.g., 123'
            )
          )
        ),
        array(
          'methods' => 'POST',
          'callback' => array($this, 'create_item'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args' => array(
            'downloadId' => array(
              'required' => true,
              'type' => 'integer',
              'description' => 'The parent dlm_download post ID, e.g., 123'
            ),
            'version' => array(
              'required' => true,
              'type' => 'string',
              'description' => 'The version string, e.g., "1.2.3"'
            ),
            'url' => array(
              'required' => true,
              'type' => 'string',
              'description' => 'The file URL',
              'format' => 'uri'
            )
          )
        )
      )
    );
  }

  /**
   * Retrieves a collection of download versions.
   * 
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
   */
  public function get_items($request)
  {
    // Make sure the given download_id exists.
    try {
      $download = download_monitor()
        ->service('download_repository')
        ->retrieve_single($request['downloadId']);
    } catch (Exception $ex) {
      return new WP_Error(
        'dlm_rest_download_not_found',
        $ex->getMessage(),
        array('status' => 404)
      );
    }
    
    // Retrieve all versions of given download.
    $download_versions = download_monitor()
      ->service('version_repository')
      ->retrieve(array(
        'post_parent' => $request['downloadId']
      ));

    $result = array_map(array($this, 'download_version_to_array'), $download_versions);
    return new WP_REST_Response($result, 200);
  }

  /**
   * Creates a download version.
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
   */
  public function create_item($request)
  {
    // Assemble parameters, which should always work due to the request validation
    // that happens before this is done.
    $download_id = $request['downloadId'];
    $author = get_current_user_id();
    $version = $request['version'];
    $url = $request['url'];

    // Make sure the given download_id exists.
    try {
      $download = download_monitor()
        ->service('download_repository')
        ->retrieve_single($download_id);
    } catch (Exception $ex) {
      return new WP_Error(
        'dlm_rest_download_not_found',
        $ex->getMessage(),
        array('status' => 404)
      );
    }

    // Increment the menu order values for all existing versions, making sure our 
    // newly added version is deemed the latest.
    try {
      $this->versions_increment_menu_order($download_id);
    } catch (Exception $ex) {
      return new WP_Error(
        'dlm_rest_exception',
        $ex->getMessage(),
        array('status' => 500)
      );
    }

    // Create new download version object.
    $download_version = new DLM_Download_Version();
    $download_version->set_download_id($download_id);
    $download_version->set_author($author);
    $download_version->set_version($version);
    $download_version->set_date(new DateTime(current_time('mysql')));
    $download_version->set_mirrors(array($url));
    $download_version->set_menu_order(0);

    try {
      // Store download version in WordPress database.
      download_monitor()
        ->service('version_repository')
        ->persist($download_version);

      // Clear transient cache. If we don't do this, the version will not be shown.
      download_monitor()
        ->service('transient_manager')
        ->clear_versions_transient($download_id);

      // Retrieve version from database to get all relevant values that were 
      // computed by WordPress but not stored in our existing object.
      $download_version = download_monitor()
        ->service('version_repository')
        ->retrieve_single($download_version->get_id());
    } catch (Exception $ex) {
      return new WP_Error(
        'dlm_rest_exception',
        $ex->getMessage(),
        array('status' => 500)
      );
    }

    $result = $this->download_version_to_array($download_version);
    return new WP_REST_Response($result, 201);
  }

  /**
   * Converts the given version to an array that can be returned in a
   * REST response.
   * 
   * @param DLM_Download_Version $download_version The download version.
   * @return array
   */
  private function download_version_to_array($download_version)
  {
    return array(
      'id' => $download_version->get_id(),
      'downloadId' => $download_version->get_download_id(),
      'author' => (int)$download_version->get_author(),
      'version' => $download_version->get_version(),
      'menuOrder' => $download_version->get_menu_order(),
      'date' => $download_version->get_date(),
      'url' => $download_version->get_url(),
      'downloadCount' => $download_version->get_download_count()
    );
  }

  /**
   * Increment existing versions' menu orders.
   * 
   * @param int $download_id The download's ID.
   */
  private function versions_increment_menu_order($download_id)
  {
    $existing_versions = download_monitor()->service('version_repository')->retrieve(
      array(
        'post_parent' => $download_id
      )
    );

    foreach ($existing_versions as $existing_version) {
      $menu_order = $existing_version->get_menu_order();
      $existing_version->set_menu_order($menu_order + 1);

      download_monitor()->service('version_repository')->persist($existing_version);
    }
  }
}
