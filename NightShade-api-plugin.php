<?php
/**
 * Plugin Name: Night Shade for wordpress
 * Description: Night Shade for wordpress
 * Version: 1.0
 */
    function check_api_password(WP_REST_Request $request) {

    $creds = include plugin_dir_path(__FILE__) . 'credentials.php';

    $provided = $request->get_header('x-api-password');

    if (!$provided || $provided !== $creds['api_password']) {
        return false;
    }

    return true;
}






add_action('rest_api_init', function () {
    register_rest_route('uploadNightShade/v1', '/file', array(
        'methods'  => 'POST',
        'callback' => 'simple_file_upload',
        'permission_callback' => 'check_api_password' 
    ));
});

function simple_file_upload(WP_REST_Request $request){
 $old  =  "";
if($request["old_filr_url"]){
 $old  = $request["old_filr_url"];
}else{
        return new WP_REST_Response([
        'success' => false,
        'message' => "no old file name in request"
    ], 200);
}

    if (empty($_FILES['file'])) {
        return new WP_REST_Response(['error' => 'No file uploaded'], 400);
    }

    $file = $_FILES['file'];

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/uploads/Converted';

    if (!file_exists($target_dir)) {
        wp_mkdir_p($target_dir);
    }

    // Generate safe filename
    $filename = uniqid('upload_', true) . '-' . sanitize_file_name($file['name']);
    $path = $target_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return new WP_REST_Response(['error' => 'Upload failed'], 500);
    }

    //replace old refrences 
    $new = wp_upload_dir()['baseurl'] . '/simple-uploads/' . $filename;
    Replace_old_refrences($old,$new);


    return new WP_REST_Response([
        'success' => true,
        'file' => $filename,
        'url'  => $new
    ], 200);
}

function Replace_old_refrences($old,$new){
global $wpdb;
$tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

foreach ($tables as $table) {
    $table_name = $table[0];

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_name 
             SET option_value = REPLACE(option_value, %s, %s)
             WHERE option_value LIKE %s",
            $old,
            $new,
            '%' . $wpdb->esc_like($old) . '%'
        )
    );
}

}





function filter_images(WP_REST_Request $request) {

    $min_size = (int) $request->get_param('min_size');
    $max_size = (int) $request->get_param('max_size');
    $min_usage = (int) $request->get_param('min_usage');

    // 1. Get all image attachments
    $images = get_posts([
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    ]);

    // 2. Build usage index (VERY IMPORTANT PART)
    $usage_map = build_image_usage_map();

    $results = [];

    foreach ($images as $img) {

        $file = get_attached_file($img->ID);
        if (!$file || !file_exists($file)) continue;

        $size = filesize($file);

        // usage count (how many times image appears in posts/meta)
        $usage = $usage_map[$img->ID] ?? 0;

        // FILTERS
        if ($min_size && $size < $min_size) continue;
        if ($max_size && $size > $max_size) continue;
        if ($min_usage && $usage < $min_usage) continue;

        $meta = wp_get_attachment_metadata($img->ID);

        $results[] = [
            'id'       => $img->ID,
            'url'      => wp_get_attachment_url($img->ID),
            'file'     => basename($file),
            'size'     => $size,
            'width'    => $meta['width'] ?? null,
            'height'   => $meta['height'] ?? null,
            'usage'    => $usage
        ];
    }

    return rest_ensure_response($results);
}







