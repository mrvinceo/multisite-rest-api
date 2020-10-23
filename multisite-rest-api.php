<?php
/**
 * Plugin Name: Multisite Rest API
 * Plugin URI: https://krux.us/multisite-rest-api/
 * Description: Adds multisite functionality to Rest API
 * Version: 1.1
 * Author: Brett Krueger
 * Author URI: https://krux.us
 */

/* Setup routes for site management */

add_action( 'rest_api_init', function () {
  register_rest_route('wp/v2', '/sites/?(?P<blog_id>\d+)?', [
    'methods'  => "GET",
    'callback' => 'wmra_sites_callback',
    'args' => [
      'blog_id',
    ],
  ]);
});

add_action( 'rest_api_init', function () {
  $now = current_time( 'mysql', true );
  register_rest_route('wp/v2', '/sites/create', [
    'methods'  => "POST",
    'callback' => 'wmra_sites_callback',
    'args' => [
      'domain',
  		'path',
  		'network_id'   => get_current_network_id(),
  		'registered'   => $now,
  		'last_updated' => $now,
  		'public'       => 1,
  		'archived'     => 0,
  		'mature'       => 0,
  		'spam'         => 0,
  		'deleted'      => 0,
  		'lang_id'      => 0,
    ],
  ]);
});


add_action( 'rest_api_init', function () {
  $now = current_time( 'mysql', true );
  register_rest_route('wp/v2', '/sites/clone', [
    'methods'  => "POST",
    'callback' => 'wmra_sites_callback',
    'args' => [
        'domain',
        'source',
  		'title', 
    ],
  ]);
});

add_action( 'rest_api_init', function () {
  $now = current_time( 'mysql', true );
  register_rest_route('wp/v2', '/sites/assign', [
    'methods'  => "PATCH",
    'callback' => 'wmra_sites_callback',
    'args' => [
	'user_id',
	'blog_id',
	'role',
    ],
  ]);
});

add_action( 'rest_api_init', function () {
  register_rest_route('wp/v2', '/sites/update', [
    'methods'  => "PUT",
    'callback' => 'wmra_sites_callback',
    'args' => [
      'blog_id',
      'option',
      'value',
    ],
  ]);
});

add_action( 'rest_api_init', function () {
  register_rest_route('wp/v2', '/sites/delete/?(?P<blog_id>\d+)?', [
    'methods'  => "DELETE",
    'callback' => 'wmra_sites_callback',
    'args' => [
      'blog_id',
    ],
  ]);
});
/* End Site Route setup */

/**
 * Before doing anything: set up clone request data.
 * These are the same fields that get submitted via an AJAX request when cloning
 * via the admin interface, so you can inspect that request to determine other
 * ways to configure the parameters, particularly if you're using NS Cloner Pro
 * and have more options available.
 * $request = array(
 *     'clone_mode'     => 'core',
 *     'source_id'      => 1, // any blog/site id on network
 *     'target_name'    => 'subdomain-or-subdir,
 *     'target_title'   => 'New Site Title',
 *     'debug'          => 1 // optional: enables logs
 * }
 */



/* Begin Sites Callback */
function wmra_sites_callback( $request ) {
    if ( is_multisite() ) {
      $params = $request->get_params();
      $route = $request->get_route();
      $user = get_current_user_id();
      if(! $params["blog_id"]) {
        $site_id = explode('/', $matches[0])[1];
      } else {
        $site_id = $params["blog_id"];
      }
      if($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match("/sites\/?/", $route, $matches)) {
        if(($params["blog_id"]) || (preg_match("/sites\/\d\/?/", $route, $matches))) {
          if(! is_user_member_of_blog($user, $site_id)) {
            return rest_ensure_response('Invalid user permissions.');
          } else {
            return rest_ensure_response( get_site( $site_id ));
          }
        }
        elseif(preg_match("/sites\/?$/", $route, $matches)) {
          if(! current_user_can('create_sites')) {
            $user_sites = get_blogs_of_user( $user );
            return rest_ensure_response( get_sites( $user_sites ));
          } else {
            return rest_ensure_response( get_sites());
          }
        }
      }
      elseif (preg_match("/sites\/create/", $route, $matches)) {
        if(! current_user_can('create_sites')){
          return rest_ensure_response('Invalid user permissions.');
          die();
        } else {
          if(! $params["domain"]) {
            return rest_ensure_response('You must provide a domain.');
            die();
          } else {
            $domain = $params["domain"];
          }
          if(! $params["path"]) {
            $path = '/';
          } else {
            $path = $params["path"];
          }
          if (! $params["title"]) {
            $title = $domain;
          } else {
            $title = $params["title"];
          }
          if (! $params["user_id"]) {
            $user_id = $user;
          } else {
            $user_id = $params["user_id"];
          }
          $options = [
            "admin_email" => $params["admin_email"],
          ];
          return rest_ensure_response(wpmu_create_blog($domain, $path, $title, $user_id, $options));
        }
      }
        
        
        // Edits below
        
        elseif (preg_match("/sites\/clone/", $route, $matches)) {
        if(! current_user_can('create_sites')){
          return rest_ensure_response('Invalid user permissions.');
          die();
        } else {
          if(! $params["domain"]) {
            return rest_ensure_response('You must provide a domain.');
            die();
          } else {
            $target_name = $params["domain"];
          }
          if (! $params["title"]) {
            $title = $domain;
          } else {
            $title = $params["title"];
          }
          if (! $params["source"]) {
            $source_id = 2;
          } else {
            $source_id = $params["source"];
          }
            
                   $request = array(
      'clone_mode'     => 'core',
      'action'         => 'process',                   
      'source_id'      => $source_id, // any blog/site id on network
      'target_name'    => $target_name,
      'target_title'   => $title,
      'disable_addons' => false,
      'clone_nonce'    => wp_create_nonce('ns_cloner'),
      'do_copy_posts'  => 1, 
      'debug'          => 1 // optional: enables logs
);
 /*             
   ns_cloner()->schedule->add(
   $request,          // array of request data as specified above
   time(),            // timestamp of date/time to start cloning - use time() to run immediately
   'multisite-rest-api' // name of your project, required but used only for debugging
);
   */         
        foreach ( $request as $key => $value ) {
        ns_cloner_request()->set( $key, $value );
        }

        // Get the cloner process object.
        $cloner = ns_cloner()->process_manager;

        // Begin cloning.
        $cloner->init();

        // Check for errors (from invalid params, or already running process).
        $errors = $cloner->get_errors();
        if ( ! empty( $errors ) ) {
        echo $errors;   // Handle error(s) and exit
        }
            
        sleep(6);
            


        // Last you'll need to poll for completion to run the cleanup process
        // when content is done cloning. Could be via AJAX to avoid timeout, or like:
         /*do {
            // Attempt to run finish, if content is complete.
           $pm->maybe_finish();
           $progress = $pm->get_progress();
           // Pause, so we're not constantly hammering the server with progress checks.
           sleep( 3 );
         } while ( 'reported' !== $progress['status'] )

        */     
        //  return rest_ensure_response('reported');
        //    sleep (3);

        foreach ( $request as $key => $value ) {
        $$key = $value;
        }
        $blog_details = get_blog_details($target_name,1);
        return rest_ensure_response($blog_details);

            
        }
    }
        
 
        
        //Edits above
        
      elseif (preg_match("/sites\/assign/", $route, $matches)) {
        if(! current_user_can('update_sites')){
          return rest_ensure_response('Invalid user permissions.');
          die();
        } else {
          $user_id = $params["user_id"] ?? $user;
          $blog_id = $params["blog_id"];
          $role = $params["role"];
          add_user_to_blog( $blog_id, $user_id, $role );
          return rest_ensure_response('User '.$user_id.' added to blog id '.$blog_id.'.');
        }
  	  }
      elseif (preg_match("/sites\/update/", $route, $matches)) {
        if(! current_user_can('update_sites')){
          return rest_ensure_response('Invalid user permissions.');
          die();
        } else {
          $option = $params["option"];
          $value = $params["value"];
          return rest_ensure_response(update_blog_option($site_id, $option, $value));
        }
        return $route;
      }
      elseif (preg_match("/sites\/delete/", $route, $matches)) {
        if(! current_user_can('delete_sites')){
          return rest_ensure_response('Invalid user permissions.');
          die();
        } else {
          return rest_ensure_response(wp_delete_site($site_id));
        }
        return $route;
      }
      else {
        return rest_ensure_response('Invalid REST Route.');
        die();
      }
    } else {
      return rest_ensure_response('This is not a Multisite install. Please enable Multisite to make use of this endpoint.');
      die();
    }
}
/* End Sites Callback */

?>
