<?php
/**
 * Taxonomy import handler class
 * 
 * @since 1.0.1
 * @package Spinda
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Spinexim_Import_Taxonomies
 * 
 * Handles importing taxonomies with complete hierarchy and metadata
 */
class Spinexim_Import_Taxonomies {
    
    /**
     * Term map array (original slug -> new term ID)
     * 
     * @since 1.0.1
     * @var array
     */
    private $term_map = array();
    
    /**
     * Parent queue for setting relationships after creation
     * 
     * @since 1.0.1
     * @var array
     */
    private $parent_queue = array();
    
    /**
     * Media map array (URL -> attachment ID)
     * 
     * @since 1.0.1
     * @var array
     */
    private $media_map = array();
    
    /**
     * Import taxonomies from JSON file
     * 
     * @since 1.0.1
     * @param array $file Uploaded file array.
     * @return void
     */
    public function spinexim_import_taxonomies( $file ) {
        // Validate file.
        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            wp_die( esc_html__( 'Invalid file upload.', 'spinda-exportimport-data' ) );
        }
        
        // Check file extension.
        $file_ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
        if ( 'json' !== strtolower( $file_ext ) ) {
            wp_die( esc_html__( 'Only JSON files are allowed.', 'spinda-exportimport-data' ) );
        }
        
        // Increase execution time for large imports.
        ini_set( 'max_execution_time', 500 );
        
        $json_content = file_get_contents( $file['tmp_name'] );
        if ( false === $json_content ) {
            wp_die( esc_html__( 'Failed to read file content.', 'spinda-exportimport-data' ) );
        }
        
        $data = json_decode( $json_content, true );
        
        if ( ! $data || ! isset( $data['export_type'] ) || 'taxonomies' !== $data['export_type'] ) {
            wp_die( esc_html__( 'Invalid JSON file format. This does not appear to be a taxonomies export file.', 'spinda-exportimport-data' ) );
        }
        
        // Import media first if present
        if ( isset( $data['media'] ) && ! empty( $data['media'] ) ) {
            foreach ( $data['media'] as $media_item ) {
                if ( isset( $media_item['url'] ) ) {
                    $url = esc_url_raw( $media_item['url'] );
                    if ( ! empty( $url ) && ! isset( $this->media_map[ $url ] ) ) {
                        $this->media_map[ $url ] = $this->spinexim_import_media( $url, $media_item );
                    }
                }
            }
        }
        
        if ( empty( $data['taxonomies'] ) ) {
            wp_die( esc_html__( 'No taxonomies found in import file.', 'spinda-exportimport-data' ) );
        }
        
        // Import each taxonomy.
        $results = array();
        foreach ( $data['taxonomies'] as $taxonomy_name => $taxonomy_data ) {
            $result = $this->spinexim_import_single_taxonomy( $taxonomy_name, $taxonomy_data );
            $results[ $taxonomy_name ] = $result;
        }
        
        // Set parent-child relationships after all terms are created.
        $this->spinexim_set_parent_relationships();
        
        // Display success message with details.
        $this->spinexim_display_import_results( $results );
    }
    
    /**
     * Import media from URL
     * 
     * @since 1.0.1
     * @param string $url         Media URL.
     * @param array  $media_item  Media item data (may contain meta).
     * @return int|string Attachment ID or original URL on failure.
     */
    private function spinexim_import_media( $url, $media_item = array() ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Check if media already exists by URL.
        $existing_id = attachment_url_to_postid( $url );
        if ( $existing_id ) {
            return absint( $existing_id );
        }
        
        $tmp = download_url( $url, 300 );
        
        if ( is_wp_error( $tmp ) ) {
            return $url;
        }
        
        $file_array = array(
            'name'     => sanitize_file_name( basename( $url ) ),
            'tmp_name' => $tmp,
        );
        
        $id = media_handle_sideload( $file_array, 0 );
        
        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            return $url;
        }
        
        // Restore attachment meta if available
        if ( ! empty( $media_item['meta'] ) && is_array( $media_item['meta'] ) ) {
            foreach ( $media_item['meta'] as $meta_key => $meta_values ) {
                $meta_key = sanitize_key( $meta_key );
                if ( ! empty( $meta_values ) && is_array( $meta_values ) ) {
                    foreach ( $meta_values as $meta_value ) {
                        update_post_meta( $id, $meta_key, maybe_unserialize( $meta_value ) );
                    }
                }
            }
        }
        
        return absint( $id );
    }
    
    /**
     * Convert media URLs back to attachment IDs recursively
     * This handles gallery fields, single images, and nested arrays properly
     * 
     * @since 1.0.1
     * @param mixed $value Value to convert.
     * @return mixed Converted value with IDs instead of URLs.
     */
    private function spinexim_convert_media_urls_to_ids( $value ) {
        // Handle string values
        if ( is_string( $value ) ) {
            // Check if it's a URL
            if ( preg_match( '/https?:\/\/[^\s]+\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)/i', $value ) ) {
                if ( isset( $this->media_map[ $value ] ) ) {
                    return $this->media_map[ $value ];
                }
                return $value;
            }
            
            // Check if it's a comma-separated string of URLs (gallery)
            if ( strpos( $value, ',' ) !== false ) {
                $parts = explode( ',', $value );
                $all_urls = true;
                $ids = array();
                
                foreach ( $parts as $part ) {
                    $part = trim( $part );
                    if ( preg_match( '/https?:\/\//i', $part ) ) {
                        if ( isset( $this->media_map[ $part ] ) ) {
                            $ids[] = $this->media_map[ $part ];
                        } else {
                            $all_urls = false;
                            break;
                        }
                    } else {
                        $all_urls = false;
                        break;
                    }
                }
                
                if ( $all_urls && ! empty( $ids ) ) {
                    return implode( ',', $ids );
                }
            }
            
            return $value;
        }
        
        // Handle array values
        if ( is_array( $value ) ) {
            // Check if this is a sequential array of URLs (gallery from ACF or meta)
            $all_strings = true;
            $all_urls = true;
            $has_urls = false;
            
            foreach ( $value as $item ) {
                if ( ! is_string( $item ) ) {
                    $all_strings = false;
                    break;
                }
                if ( preg_match( '/https?:\/\//i', $item ) ) {
                    $has_urls = true;
                } else {
                    $all_urls = false;
                }
            }
            
            // If it's an array of URLs, convert to comma-separated IDs (for gallery)
            if ( $all_strings && $all_urls && $has_urls && ! empty( $value ) ) {
                $ids = array();
                foreach ( $value as $url ) {
                    if ( isset( $this->media_map[ $url ] ) ) {
                        $ids[] = $this->media_map[ $url ];
                    }
                }
                if ( ! empty( $ids ) ) {
                    // Return as comma-separated string for WooCommerce gallery
                    return implode( ',', $ids );
                }
            }
            
            // Check if it's an associative array with 'url' key (ACF image field)
            if ( isset( $value['url'] ) && isset( $value['id'] ) ) {
                if ( isset( $this->media_map[ $value['url'] ] ) ) {
                    // Return the ID for ACF image field
                    return $this->media_map[ $value['url'] ];
                }
                return isset( $value['id'] ) ? $value['id'] : $value['url'];
            }
            
            // Check if it's an ACF gallery field (array of image arrays)
            $is_acf_gallery = true;
            foreach ( $value as $item ) {
                if ( ! is_array( $item ) || ! isset( $item['url'] ) ) {
                    $is_acf_gallery = false;
                    break;
                }
            }
            
            if ( $is_acf_gallery && ! empty( $value ) ) {
                $gallery_ids = array();
                foreach ( $value as $image ) {
                    if ( isset( $image['url'] ) && isset( $this->media_map[ $image['url'] ] ) ) {
                        $gallery_ids[] = $this->media_map[ $image['url'] ];
                    }
                }
                if ( ! empty( $gallery_ids ) ) {
                    // Return array of IDs for ACF gallery
                    return $gallery_ids;
                }
            }
            
            // Recursively process associative arrays
            foreach ( $value as $key => $val ) {
                $value[ $key ] = $this->spinexim_convert_media_urls_to_ids( $val );
            }
        }
        
        return $value;
    }
    
    /**
     * Import a single taxonomy
     * 
     * @since 1.0.1
     * @param string $taxonomy_name Taxonomy name.
     * @param array  $taxonomy_data Taxonomy data.
     * @return array Import results.
     */
    private function spinexim_import_single_taxonomy( $taxonomy_name, $taxonomy_data ) {
        $result = array(
            'name'            => $taxonomy_name,
            'label'           => isset( $taxonomy_data['label'] ) ? $taxonomy_data['label'] : $taxonomy_name,
            'terms_created'   => 0,
            'terms_skipped'   => 0,
            'meta_added'      => 0,
        );
        
        // Check if taxonomy exists.
        if ( ! taxonomy_exists( $taxonomy_name ) ) {
            $result['error'] = sprintf(
                /* translators: %s: taxonomy name */
                __( 'Taxonomy "%s" does not exist on this site. Please register it first.', 'spinda-exportimport-data' ),
                $taxonomy_name
            );
            return $result;
        }
        
        // Import terms recursively.
        if ( ! empty( $taxonomy_data['terms'] ) ) {
            $import_stats = $this->spinexim_import_terms_recursive(
                $taxonomy_data['terms'],
                $taxonomy_name,
                0
            );
            
            $result['terms_created'] = $import_stats['created'];
            $result['terms_skipped'] = $import_stats['skipped'];
            $result['meta_added']    = $import_stats['meta_added'];
        }
        
        return $result;
    }
    
    /**
     * Import terms recursively
     * 
     * @since 1.0.1
     * @param array  $terms      Terms data array.
     * @param string $taxonomy   Taxonomy name.
     * @param int    $parent_id  Parent term ID.
     * @return array Import statistics.
     */
    private function spinexim_import_terms_recursive( $terms, $taxonomy, $parent_id = 0 ) {
        $stats = array(
            'created'    => 0,
            'skipped'    => 0,
            'meta_added' => 0,
        );
        
        foreach ( $terms as $term_data ) {
            $slug = isset( $term_data['slug'] ) ? sanitize_title( $term_data['slug'] ) : '';
            $name = isset( $term_data['name'] ) ? sanitize_text_field( $term_data['name'] ) : '';
            $description = isset( $term_data['description'] ) ? sanitize_textarea_field( $term_data['description'] ) : '';
            
            if ( empty( $slug ) || empty( $name ) ) {
                $stats['skipped']++;
                continue;
            }
            
            // Check if term already exists.
            $existing_term = term_exists( $slug, $taxonomy );
            
            if ( $existing_term ) {
                // Term exists, get its ID.
                $term_id = is_array( $existing_term ) ? $existing_term['term_id'] : $existing_term;
                $stats['skipped']++;
            } else {
                // Create new term.
                $insert_args = array(
                    'slug'        => $slug,
                    'description' => $description,
                );
                
                // Don't set parent yet if it's not 0.
                if ( 0 !== $parent_id ) {
                    $insert_args['parent'] = $parent_id;
                }
                
                $insert = wp_insert_term( $name, $taxonomy, $insert_args );
                
                if ( is_wp_error( $insert ) ) {
                    $stats['skipped']++;
                    continue;
                }
                
                $term_id = $insert['term_id'];
                $stats['created']++;
                
                // Store mapping for parent relationships.
                $this->term_map[ $slug ] = $term_id;
                
                // Import term meta with media conversion.
                if ( ! empty( $term_data['meta'] ) && is_array( $term_data['meta'] ) ) {
                    $meta_added = $this->spinexim_import_term_meta( $term_id, $term_data['meta'] );
                    $stats['meta_added'] += $meta_added;
                }
            }
            
            // Store parent relationship for later if needed.
            if ( 0 !== $parent_id ) {
                $this->parent_queue[] = array(
                    'term_id'   => $term_id,
                    'parent_id' => $parent_id,
                    'taxonomy'  => $taxonomy,
                );
            }
            
            // Import children recursively.
            if ( ! empty( $term_data['children'] ) && is_array( $term_data['children'] ) ) {
                $child_stats = $this->spinexim_import_terms_recursive(
                    $term_data['children'],
                    $taxonomy,
                    $term_id
                );
                
                $stats['created']    += $child_stats['created'];
                $stats['skipped']    += $child_stats['skipped'];
                $stats['meta_added'] += $child_stats['meta_added'];
            }
        }
        
        return $stats;
    }
    
    /**
     * Import term meta data with media URL to ID conversion
     * 
     * @since 1.0.1
     * @param int   $term_id Term ID.
     * @param array $meta    Meta data array.
     * @return int Number of meta fields added.
     */
    private function spinexim_import_term_meta( $term_id, $meta ) {
        $count = 0;
        
        foreach ( $meta as $meta_key => $meta_values ) {
            $meta_key = sanitize_key( $meta_key );
            
            if ( is_array( $meta_values ) ) {
                // Delete existing meta to avoid duplicates.
                delete_term_meta( $term_id, $meta_key );
                
                foreach ( $meta_values as $meta_value ) {
                    // Convert media URLs back to attachment IDs
                    $converted_value = $this->spinexim_convert_media_urls_to_ids( $meta_value );
                    
                    // Handle the converted value properly
                    if ( is_array( $converted_value ) ) {
                        // For ACF gallery or repeater fields that return arrays
                        add_term_meta( $term_id, $meta_key, $converted_value );
                    } elseif ( is_string( $converted_value ) && strpos( $converted_value, ',' ) !== false ) {
                        // For WooCommerce gallery (comma-separated IDs)
                        add_term_meta( $term_id, $meta_key, $converted_value );
                    } else {
                        // For single values
                        add_term_meta( $term_id, $meta_key, $converted_value );
                    }
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Set parent-child relationships for terms
     * 
     * @since 1.0.1
     * @return void
     */
    private function spinexim_set_parent_relationships() {
        foreach ( $this->parent_queue as $relation ) {
            $term_id   = $relation['term_id'];
            $parent_id = $relation['parent_id'];
            $taxonomy  = $relation['taxonomy'];
            
            // Update term parent.
            wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent_id ) );
        }
    }
    
    /**
     * Display import results
     * 
     * @since 1.0.1
     * @param array $results Import results for each taxonomy.
     * @return void
     */
    private function spinexim_display_import_results( $results ) {
        ?>
        <div class="notice notice-success">
            <p><strong><?php esc_html_e( 'Import Completed Successfully!', 'spinda-exportimport-data' ); ?></strong></p>
            <ul>
                <?php foreach ( $results as $result ) : ?>
                    <li>
                        <strong><?php echo esc_html( $result['label'] ); ?></strong>:
                        <?php 
                        printf(
                            /* translators: 1: created terms count, 2: skipped terms count */
                            esc_html__( '%1$d terms created, %2$d terms skipped', 'spinda-exportimport-data' ),
                            intval( $result['terms_created'] ),
                            intval( $result['terms_skipped'] )
                        );
                        
                        if ( isset( $result['meta_added'] ) && $result['meta_added'] > 0 ) {
                            echo ' - ' . esc_html( sprintf(
                                /* translators: %d: meta fields added */
                                _n( '%d meta field added', '%d meta fields added', $result['meta_added'], 'spinda-exportimport-data' ),
                                $result['meta_added']
                            ) );
                        }
                        
                        if ( isset( $result['error'] ) ) {
                            echo '<br><span class="error">' . esc_html( $result['error'] ) . '</span>';
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><?php esc_html_e( 'Note: Media files have been downloaded and attached to your WordPress media library.', 'spinda-exportimport-data' ); ?></p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=spinexim-taxonomies' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Back to Taxonomies Export/Import', 'spinda-exportimport-data' ); ?>
        </a>
        <?php
        exit;
    }
}