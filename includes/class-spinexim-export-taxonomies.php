<?php
/**
 * Taxonomy export handler class
 * 
 * @since 1.0.1
 * @package Spinda
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Spinexim_Export_Taxonomies
 * 
 * Handles exporting taxonomies with complete hierarchy and metadata
 */
class Spinexim_Export_Taxonomies {
    
    /**
     * Skip meta keys array
     * 
     * @since 1.0.1
     * @var array
     */
    private $skip_meta = array( '_edit_lock', '_edit_last' );
    
    /**
     * Media map to track unique media URLs
     * 
     * @since 1.0.1
     * @var array
     */
    private $media_map = array();
    
    /**
     * Collected media data for export
     * 
     * @since 1.0.1
     * @var array
     */
    private $collected_media = array();
    
    /**
     * Export taxonomies with complete hierarchy and meta
     * 
     * @since 1.0.1
     * @param string $post_type            Post type to export taxonomies from.
     * @param array  $selected_taxonomies  Array of taxonomy names to export (empty for all).
     * @param bool   $include_term_meta    Whether to include term meta.
     * @param bool   $include_empty_terms  Whether to include terms with no posts.
     * @return void
     */
    public function spinexim_export_taxonomies( $post_type, $selected_taxonomies = array(), $include_term_meta = true, $include_empty_terms = false ) {
        // Validate post type exists.
        $post_type_obj = get_post_type_object( $post_type );
        if ( ! $post_type_obj ) {
            wp_die( esc_html__( 'Invalid post type.', 'spinda-exportimport-data' ) );
        }
        
        // Reset media tracking
        $this->media_map = array();
        $this->collected_media = array();
        
        // Get all taxonomies for this post type.
        $all_taxonomies = get_object_taxonomies( $post_type, 'objects' );
        
        // Filter selected taxonomies if specified.
        $taxonomies_to_export = $this->spinexim_filter_taxonomies( $all_taxonomies, $selected_taxonomies );
        
        $data = array(
            'export_type'      => 'taxonomies',
            'source_post_type' => $post_type,
            'export_date'      => current_time( 'mysql' ),
            'media'            => array(),
            'taxonomies'       => array(),
        );
        
        // Export each taxonomy.
        foreach ( $taxonomies_to_export as $taxonomy ) {
            $taxonomy_data = $this->spinexim_export_single_taxonomy(
                $taxonomy,
                $post_type,
                $include_term_meta,
                $include_empty_terms
            );
            
            if ( ! empty( $taxonomy_data['terms'] ) ) {
                $data['taxonomies'][ $taxonomy->name ] = $taxonomy_data;
            }
        }
        
        // Add collected media to export data
        if ( ! empty( $this->collected_media ) ) {
            $data['media'] = array_values( $this->collected_media );
        }
        
        // Download JSON.
        $this->spinexim_download_json( $data, $post_type );
    }
    
    /**
     * Filter taxonomies based on selection
     * 
     * @since 1.0.1
     * @param array $all_taxonomies       All available taxonomies.
     * @param array $selected_taxonomies  Selected taxonomy names.
     * @return array Filtered taxonomies.
     */
    private function spinexim_filter_taxonomies( $all_taxonomies, $selected_taxonomies ) {
        if ( empty( $selected_taxonomies ) ) {
            return $all_taxonomies;
        }
        
        $filtered = array();
        foreach ( $selected_taxonomies as $tax_name ) {
            if ( isset( $all_taxonomies[ $tax_name ] ) ) {
                $filtered[ $tax_name ] = $all_taxonomies[ $tax_name ];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Export a single taxonomy with all its terms and hierarchy
     * 
     * @since 1.0.1
     * @param object $taxonomy            Taxonomy object.
     * @param string $post_type           Post type.
     * @param bool   $include_term_meta   Include term meta.
     * @param bool   $include_empty_terms Include empty terms.
     * @return array Taxonomy export data.
     */
    private function spinexim_export_single_taxonomy( $taxonomy, $post_type, $include_term_meta, $include_empty_terms ) {
        $taxonomy_data = array(
            'name'         => $taxonomy->name,
            'label'        => $taxonomy->label,
            'hierarchical' => $taxonomy->hierarchical,
            'public'       => $taxonomy->public,
            'terms'        => array(),
        );
        
        // Get all terms for this taxonomy.
        $args = array(
            'taxonomy'   => $taxonomy->name,
            'hide_empty' => ! $include_empty_terms,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );
        
        $terms = get_terms( $args );
        
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $taxonomy_data;
        }
        
        // Organize terms by ID for easy access.
        $terms_by_id = array();
        foreach ( $terms as $term ) {
            $terms_by_id[ $term->term_id ] = $term;
        }
        
        // Build hierarchy.
        $hierarchy = $this->spinexim_build_term_hierarchy( $terms_by_id );
        
        // Export terms recursively with their children.
        $taxonomy_data['terms'] = $this->spinexim_export_terms_recursive(
            $hierarchy,
            $terms_by_id,
            $taxonomy->name,
            $include_term_meta
        );
        
        return $taxonomy_data;
    }
    
    /**
     * Build term hierarchy array
     * 
     * @since 1.0.1
     * @param array $terms_by_id Terms indexed by ID.
     * @return array Hierarchical term structure.
     */
    private function spinexim_build_term_hierarchy( $terms_by_id ) {
        $hierarchy = array();
        
        foreach ( $terms_by_id as $term_id => $term ) {
            if ( 0 === $term->parent ) {
                // Top level term.
                $hierarchy[ $term_id ] = array(
                    'id'       => $term_id,
                    'children' => $this->spinexim_get_term_children( $term_id, $terms_by_id ),
                );
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * Get child terms recursively
     * 
     * @since 1.0.1
     * @param int   $parent_id    Parent term ID.
     * @param array $terms_by_id  All terms by ID.
     * @return array Child term IDs.
     */
    private function spinexim_get_term_children( $parent_id, $terms_by_id ) {
        $children = array();
        
        foreach ( $terms_by_id as $term_id => $term ) {
            if ( $term->parent === $parent_id ) {
                $children[ $term_id ] = array(
                    'id'       => $term_id,
                    'children' => $this->spinexim_get_term_children( $term_id, $terms_by_id ),
                );
            }
        }
        
        return $children;
    }
    
    /**
     * Export terms recursively maintaining parent-child relationships
     * 
     * @since 1.0.1
     * @param array  $hierarchy         Hierarchical term structure.
     * @param array  $terms_by_id       All terms by ID.
     * @param string $taxonomy          Taxonomy name.
     * @param bool   $include_term_meta Include term meta.
     * @return array Exported terms with hierarchy.
     */
    private function spinexim_export_terms_recursive( $hierarchy, $terms_by_id, $taxonomy, $include_term_meta ) {
        $exported_terms = array();
        
        foreach ( $hierarchy as $term_id => $term_info ) {
            if ( ! isset( $terms_by_id[ $term_id ] ) ) {
                continue;
            }
            
            $term = $terms_by_id[ $term_id ];
            
            $term_data = array(
                'slug'        => sanitize_title( $term->slug ),
                'name'        => sanitize_text_field( $term->name ),
                'description' => sanitize_textarea_field( $term->description ),
                'count'       => intval( $term->count ),
                'children'    => array(),
            );
            
            // Add term meta if requested with media conversion.
            if ( $include_term_meta ) {
                $term_data['meta'] = $this->spinexim_get_term_meta_with_media( $term->term_id );
            }
            
            // Recursively export children.
            if ( ! empty( $term_info['children'] ) ) {
                $term_data['children'] = $this->spinexim_export_terms_recursive(
                    $term_info['children'],
                    $terms_by_id,
                    $taxonomy,
                    $include_term_meta
                );
            }
            
            $exported_terms[] = $term_data;
        }
        
        return $exported_terms;
    }
    
    /**
     * Get term meta with media URL conversion
     * 
     * @since 1.0.1
     * @param int $term_id Term ID.
     * @return array Processed term meta with media URLs.
     */
    private function spinexim_get_term_meta_with_media( $term_id ) {
        $meta_raw = get_term_meta( $term_id );
        $meta      = array();
        
        foreach ( $meta_raw as $meta_key => $meta_values ) {
            $meta_key = sanitize_key( $meta_key );
            
            // Skip internal meta.
            if ( in_array( $meta_key, $this->skip_meta, true ) ) {
                continue;
            }
            
            if ( 0 === strpos( $meta_key, '_wp_' ) ) {
                continue;
            }
            
            foreach ( $meta_values as $meta_value ) {
                $meta_value = maybe_unserialize( $meta_value );
                $meta_value = $this->spinexim_detect_and_convert_media( $meta_value );
                $meta[ $meta_key ][] = $meta_value;
            }
        }
        
        return $meta;
    }
    
    /**
     * Detect and convert media IDs to URLs recursively
     * This matches the pattern from class-spinexim-export.php
     * 
     * @since 1.0.1
     * @param mixed $value Value to check for media.
     * @return mixed Processed value with URLs instead of IDs.
     */
    // private function spinexim_detect_and_convert_media( $value ) {
    //     // Handle attachment ID (numeric)
    //     if ( is_numeric( $value ) ) {
    //         $attachment_id = absint( $value );
    //         $url = wp_get_attachment_url( $attachment_id );
    //         if ( $url ) {
    //             $url = esc_url_raw( $url );
    //             if ( ! isset( $this->media_map[ $url ] ) ) {
    //                 $this->media_map[ $url ] = $attachment_id;
                    
    //                 // Collect attachment meta
    //                 $attachment_meta = get_post_meta( $attachment_id );
    //                 $sanitized_meta = array();
    //                 foreach ( $attachment_meta as $meta_key => $meta_values ) {
    //                     $sanitized_meta[ sanitize_key( $meta_key ) ] = $meta_values;
    //                 }
                    
    //                 $this->collected_media[ $url ] = array(
    //                     'url'  => $url,
    //                     'meta' => $sanitized_meta,
    //                 );
    //             }
    //             return $url;
    //         }
    //     }
        
    //     // Handle WooCommerce gallery IDs (comma separated string like "123,456,789")
    //     if ( is_string( $value ) && preg_match( '/^\d+(,\d+)+$/', $value ) ) {
    //         $ids = array_map( 'absint', explode( ',', $value ) );
    //         $urls = array();
            
    //         foreach ( $ids as $id ) {
    //             $url = wp_get_attachment_url( $id );
    //             if ( $url ) {
    //                 $url = esc_url_raw( $url );
    //                 $urls[] = $url;
    //                 if ( ! isset( $this->media_map[ $url ] ) ) {
    //                     $this->media_map[ $url ] = $id;
    //                     $this->collected_media[ $url ] = array(
    //                         'url'  => $url,
    //                         'meta' => array(),
    //                     );
    //                 }
    //             }
    //         }
    //         return $urls;
    //     }
        
    //     // Handle serialized ACF gallery or repeater fields
    //     if ( is_array( $value ) ) {
    //         // Check if it's an ACF image field array
    //         if ( isset( $value['id'] ) && isset( $value['url'] ) ) {
    //             $url = esc_url_raw( $value['url'] );
    //             if ( ! isset( $this->media_map[ $url ] ) ) {
    //                 $attachment_id = isset( $value['id'] ) ? absint( $value['id'] ) : 0;
    //                 $this->media_map[ $url ] = $attachment_id;
    //                 $this->collected_media[ $url ] = array(
    //                     'url'  => $url,
    //                     'meta' => array(),
    //                 );
    //             }
    //             return $url;
    //         }
            
    //         // Check if it's an ACF file field array
    //         if ( isset( $value['url'] ) && isset( $value['filename'] ) ) {
    //             $url = esc_url_raw( $value['url'] );
    //             if ( ! isset( $this->media_map[ $url ] ) ) {
    //                 $this->media_map[ $url ] = true;
    //                 $this->collected_media[ $url ] = array(
    //                     'url'  => $url,
    //                     'meta' => array(),
    //                 );
    //             }
    //             return $url;
    //         }
            
    //         // Recursively process arrays
    //         foreach ( $value as $key => $val ) {
    //             $value[ $key ] = $this->spinexim_detect_and_convert_media( $val );
    //         }
    //     }
        
    //     // Handle direct media URL (already a URL)
    //     if ( is_string( $value ) && preg_match( '/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)(\?.*)?$/i', $value ) ) {
    //         $url = esc_url_raw( $value );
    //         if ( ! isset( $this->media_map[ $url ] ) ) {
    //             $this->media_map[ $url ] = true;
    //             $this->collected_media[ $url ] = array(
    //                 'url'  => $url,
    //                 'meta' => array(),
    //             );
    //         }
    //         return $url;
    //     }
        
    //     return $value;
    // }
    private function spinexim_detect_and_convert_media( $value ) {
        // Handle attachment ID (numeric)
        if ( is_numeric( $value ) ) {
            $attachment_id = absint( $value );
            $url = wp_get_attachment_url( $attachment_id );
            if ( $url ) {
                $url = esc_url_raw( $url );
                if ( ! isset( $this->media_map[ $url ] ) ) {
                    $this->media_map[ $url ] = $attachment_id;
                    
                    // Collect attachment meta
                    $attachment_meta = get_post_meta( $attachment_id );
                    $sanitized_meta = array();
                    foreach ( $attachment_meta as $meta_key => $meta_values ) {
                        $sanitized_meta[ sanitize_key( $meta_key ) ] = $meta_values;
                    }
                    
                    $this->collected_media[ $url ] = array(
                        'url'  => $url,
                        'meta' => $sanitized_meta,
                    );
                }
                return $url;
            }
        }
        
        // Handle WooCommerce gallery IDs (comma separated string like "123,456,789")
        if ( is_string( $value ) && preg_match( '/^\d+(,\d+)+$/', $value ) ) {
            $ids = array_map( 'absint', explode( ',', $value ) );
            $urls = array();
            
            foreach ( $ids as $id ) {
                $url = wp_get_attachment_url( $id );
                if ( $url ) {
                    $url = esc_url_raw( $url );
                    $urls[] = $url;
                    if ( ! isset( $this->media_map[ $url ] ) ) {
                        $this->media_map[ $url ] = $id;
                        $this->collected_media[ $url ] = array(
                            'url'  => $url,
                            'meta' => array(),
                        );
                    }
                }
            }
            // Return as array of URLs (will be converted back to comma-separated IDs on import)
            return $urls;
        }
        
        // Handle ACF gallery field (array of attachment IDs)
        if ( is_array( $value ) && ! empty( $value ) ) {
            $all_numeric = true;
            foreach ( $value as $item ) {
                if ( ! is_numeric( $item ) ) {
                    $all_numeric = false;
                    break;
                }
            }
            
            if ( $all_numeric ) {
                $urls = array();
                foreach ( $value as $id ) {
                    $url = wp_get_attachment_url( absint( $id ) );
                    if ( $url ) {
                        $url = esc_url_raw( $url );
                        $urls[] = $url;
                        if ( ! isset( $this->media_map[ $url ] ) ) {
                            $this->media_map[ $url ] = $id;
                            $this->collected_media[ $url ] = array(
                                'url'  => $url,
                                'meta' => array(),
                            );
                        }
                    }
                }
                return $urls;
            }
        }
        
        // Handle serialized ACF gallery or repeater fields
        if ( is_array( $value ) ) {
            // Check if it's an ACF image field array
            if ( isset( $value['id'] ) && isset( $value['url'] ) ) {
                $url = esc_url_raw( $value['url'] );
                if ( ! isset( $this->media_map[ $url ] ) ) {
                    $attachment_id = isset( $value['id'] ) ? absint( $value['id'] ) : 0;
                    $this->media_map[ $url ] = $attachment_id;
                    $this->collected_media[ $url ] = array(
                        'url'  => $url,
                        'meta' => array(),
                    );
                }
                return $url;
            }
            
            // Check if it's an ACF file field array
            if ( isset( $value['url'] ) && isset( $value['filename'] ) ) {
                $url = esc_url_raw( $value['url'] );
                if ( ! isset( $this->media_map[ $url ] ) ) {
                    $this->media_map[ $url ] = true;
                    $this->collected_media[ $url ] = array(
                        'url'  => $url,
                        'meta' => array(),
                    );
                }
                return $url;
            }
            
            // Recursively process arrays
            foreach ( $value as $key => $val ) {
                $value[ $key ] = $this->spinexim_detect_and_convert_media( $val );
            }
        }
        
        // Handle direct media URL (already a URL)
        if ( is_string( $value ) && preg_match( '/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)(\?.*)?$/i', $value ) ) {
            $url = esc_url_raw( $value );
            if ( ! isset( $this->media_map[ $url ] ) ) {
                $this->media_map[ $url ] = true;
                $this->collected_media[ $url ] = array(
                    'url'  => $url,
                    'meta' => array(),
                );
            }
            return $url;
        }
        
        return $value;
    }
    
    /**
     * Download JSON file
     * 
     * @since 1.0.1
     * @param array  $data      Export data.
     * @param string $post_type Post type name.
     * @return void
     */
    private function spinexim_download_json( $data, $post_type ) {
        $filename = sanitize_title( $post_type ) . '-taxonomies-export.json';
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        
        // Clean up empty arrays for better readability.
        if ( empty( $data['media'] ) ) {
            unset( $data['media'] );
        }
        
        foreach ( $data['taxonomies'] as $tax_name => $tax_data ) {
            if ( empty( $tax_data['terms'] ) ) {
                unset( $data['taxonomies'][ $tax_name ] );
            }
        }
        
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }
}