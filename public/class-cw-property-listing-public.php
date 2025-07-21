class Cw_Property_Listing_Public {
    // ... existing properties and methods ...

    /**
     * Fetch property data from API with caching
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
                'headers' => ['Accept' => 'application/json']
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

    public function load_html_view_shortcode($atts) {
        // ... existing code ...
        $property_slug = get_query_var('property_slug');
        $output = '';

        // Add hidden SEO content
        if ($property_slug) {
            $property = $this->get_property_data($property_slug);
            if ($property) {
                $output .= $this->generate_hidden_seo_content($property);
                wp_localize_script("{$this->plugin_name}-frontend", 'cw_property_current', ['data' => $property]);
            }
        } else {
            $output .= $this->generate_archive_seo_content();
        }

        // ... existing file inclusion code ...
        return $output;
    }

    private function generate_hidden_seo_content($property) {
        ob_start(); ?>
        <div class="property-seo-content" style="position:absolute;left:-9999px;top:-9999px;">
            <h1><?= esc_html($property['title'] ?? '') ?></h1>
            <p><?= esc_html($property['description'] ?? '') ?></p>
            <?php if (!empty($property['amenities'])) : ?>
                <ul>
                    <?php foreach ($property['amenities'] as $amenity) : ?>
                        <li><?= esc_html($amenity) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function generate_archive_seo_content() {
        return '<div class="archive-seo-content" style="position:absolute;left:-9999px;top:-9999px;">
            <h1>Coffs Coast Accommodation Listings</h1>
            <p>Browse our collection of vacation rentals, beach houses, and apartments in Coffs Harbour</p>
        </div>';
    }

    public function set_rank_math_metadata() {
        if (!get_query_var('property_slug')) return;
        
        $property = $this->get_property_data(get_query_var('property_slug'));
        if (!$property) return;

        // Set title
        add_filter('rank_math/frontend/title', function() use ($property) {
            return ($property['title'] ?? 'Property Listing') . ' | ' . get_bloginfo('name');
        });
        
        // Set description
        add_filter('rank_math/frontend/description', function() use ($property) {
            return wp_trim_words($property['description'] ?? '', 30);
        });
        
        // Set canonical URL
        add_filter('rank_math/frontend/canonical', function() {
            return home_url('/listings/' . get_query_var('property_slug') . '/');
        });
    }

    public function add_property_schema($schemas) {
        if (!get_query_var('property_slug')) return $schemas;
        
        $property = $this->get_property_data(get_query_var('property_slug'));
        if (!$property) return $schemas;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Accommodation',
            'name' => $property['title'] ?? '',
            'description' => wp_trim_words($property['description'] ?? '', 55),
            'url' => home_url('/listings/' . get_query_var('property_slug') . '/'),
        ];

        // Add amenities
        if (!empty($property['amenities'])) {
            $schema['amenityFeature'] = array_map(function($amenity) {
                return ['@type' => 'LocationFeatureSpecification', 'name' => $amenity];
            }, $property['amenities']);
        }

        // Add location
        if (!empty($property['location'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $property['location']['city'] ?? 'Coffs Harbour',
                'addressRegion' => $property['location']['state'] ?? 'NSW',
                'postalCode' => $property['location']['postcode'] ?? '',
                'streetAddress' => $property['location']['street'] ?? '',
            ];
        }

        $schemas['property'] = $schema;
        return $schemas;
    }

    public function enqueue_scripts() {
        // ... existing code ...
        
        // Load in footer for better performance
        wp_enqueue_script(
            "{$this->plugin_name}-frontend", 
            plugins_url('str-portal-frontend/assets/index-bbb8c24a.js', __FILE__), 
            [], 
            $this->version, 
            true
        );

        // ... existing localization code ...
    }

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add SEO hooks
        add_action('wp', [$this, 'set_rank_math_metadata']);
        add_filter('rank_math/json_ld', [$this, 'add_property_schema'], 10, 1);
    }
}