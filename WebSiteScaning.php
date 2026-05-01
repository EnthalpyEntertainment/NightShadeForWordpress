
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

add_action('rest_api_init', function () {
    register_rest_route('uploadNightShade/v1', '/getPosibleImages', array(
        'methods'  => 'POST',
        'callback' => 'filter_images',
        'permission_callback' => 'check_api_password' 
    ));
});
