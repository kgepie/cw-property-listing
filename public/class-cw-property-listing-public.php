/**
 * Fetch property data from API with caching
 * 
 * @param string $slug Property slug
 * @return array|bool Property data or false
 */
private function get_property_data($slug) {
    $customer_id = get_option('property_listing_customer_id', '');
    $transient_key = 'cw_property_' . md5($customer_id . '_' . $slug);
    $property = get_transient($transient_key);

    // Fetch from API if not cached
    if (false === $property) {
        $api_url = "https://your-api-domain.com/properties?slug=" . urlencode($slug) . "&customer_id={$customer_id}";
        
        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $property = json_decode(wp_remote_retrieve_body($response), true);
            
            // Cache for 12 hours if valid data
            if ($property && !empty($property['id'])) {
                set_transient($transient_key, $property, 12 * HOUR_IN_SECONDS);
            }
        }
    }
    
    return $property;
}