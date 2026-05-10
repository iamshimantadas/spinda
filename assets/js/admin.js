/**
 * Spinda Plugin Admin Scripts
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Confirm before export
        $('form').on('submit', function(e) {
            var action = $(this).find('input[name="action"]').val();
            
            if (action === 'spinexim_export_posts' || action === 'spinexim_export_users') {
                if (typeof spinexim_ajax !== 'undefined' && spinexim_ajax.confirmExport) {
                    if (!confirm(spinexim_ajax.confirmExport)) {
                        e.preventDefault();
                        return false;
                    }
                } else {
                    if (!confirm('Are you sure you want to export this data?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
            
            if (action === 'spinexim_import_posts' || action === 'spinexim_import_users') {
                var fileInput = $(this).find('input[type="file"]');
                if (fileInput.length && !fileInput.val()) {
                    if (typeof spinexim_ajax !== 'undefined' && spinexim_ajax.selectFile) {
                        alert(spinexim_ajax.selectFile);
                    } else {
                        alert('Please select a JSON file to import.');
                    }
                    e.preventDefault();
                    return false;
                }
                
                if (typeof spinexim_ajax !== 'undefined' && spinexim_ajax.confirmImport) {
                    if (!confirm(spinexim_ajax.confirmImport)) {
                        e.preventDefault();
                        return false;
                    }
                } else {
                    if (!confirm('Import will add new content. Existing content will not be overwritten. Continue?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
        
        // Show/hide options based on post type selection
        $('#post_type').on('change', function() {
            var postType = $(this).val();
            
            if (postType === 'product') {
                $('input[name="include_variations"]').closest('.spinexim-form-group').show();
            } else {
                $('input[name="include_variations"]').prop('checked', false);
                $('input[name="include_variations"]').closest('.spinexim-form-group').hide();
            }
        }).trigger('change');
        
        // Taxonomy AJAX loading
        $('#spinexim-post-type-select').on('change', function() {
            var postType = $(this).val();
            var container = $('#spinexim-taxonomies-container');
            var listContainer = $('#spinexim-taxonomies-list');
            
            if (postType) {
                listContainer.html('<p class="description">Loading taxonomies...</p>');
                container.show();
                
                $.ajax({
                    url: spinexim_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spinexim_get_taxonomies',
                        post_type: postType,
                        nonce: spinexim_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            listContainer.empty();
                            
                            if (response.data.taxonomies.length > 0) {
                                $.each(response.data.taxonomies, function(index, tax) {
                                    var checkbox = $('<label class="spinexim-checkbox-label">' +
                                        '<input type="checkbox" name="taxonomies[]" value="' + tax.name + '" checked> ' +
                                        tax.label +
                                        ' <span class="description">(' + tax.count + ' terms)</span>' +
                                        '</label>'
                                    );
                                    listContainer.append(checkbox);
                                });
                            } else {
                                listContainer.html('<p class="description">No taxonomies found for this post type.</p>');
                            }
                        } else {
                            listContainer.html('<p class="error">Error loading taxonomies.</p>');
                        }
                    },
                    error: function() {
                        listContainer.html('<p class="error">Failed to load taxonomies.</p>');
                    }
                });
            } else {
                container.hide();
                listContainer.empty();
            }
        });
        
    });
    
})(jQuery);