<?php

/**
 * Abstract base class for DLM REST Controllers.
 * 
 * We currently don't implement all endpoints, e.g., PUT, DELETE, so we don't
 * extend WP_REST_Controller.
 */
abstract class DLM_REST_Controller
{
  /**
   * The namespace of this controller's route.
   */
  protected $namespace;

  /**
   * The name of the resource served by this controller.
   */
  protected $resource_name;

  /**
   * Checks if a given request has access to get items.
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
   */
  public function get_items_permissions_check($request)
  {
    return $this->current_user_can_read();
  }

  /**
   * Checks if a given request has access to create items.
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
   */
  public function create_item_permissions_check($request)
  {
    return $this->current_user_is_editor();
  }

  /**
   * Checks whether the current user can read posts.
   * 
   * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
   */
  protected function current_user_can_read()
  {
    if (!current_user_can('read')) {
      return new WP_Error(
        'rest_forbidden',
        esc_html__('You cannot view the download resource.'),
        array('status' => $this->authorization_status_code())
      );
    }

    return true;
  }

  /**
   * Determines whether the current user is at least an editor.
   * 
   * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
   */
  protected function current_user_is_editor()
  {
    $user = wp_get_current_user();
    $roles = (array)$user->roles;
    $is_authorized = in_array('editor', $roles) || in_array('administrator', $roles);

    if (!$is_authorized) {
      return new WP_Error(
        'rest_forbidden',
        esc_html__('You do not have the required authorization to create or edit items.'),
        array('status' => $this->authorization_status_code())
      );
    }

    return true;
  }

  /**
   * Sets up the proper HTTP status code for authorization.
   */
  protected function authorization_status_code()
  {
    $status = 401;

    if (is_user_logged_in()) {
      $status = 403;
    }

    return $status;
  }
}
