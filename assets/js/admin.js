/**
 * Quil Plugin Admin Scripts
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Confirm before export
        $('form').on('submit', function(e) {
            var action = $(this).find('input[name="action"]').val();
            
            if (action === 'mc_quil_export_posts' || action === 'mc_quil_export_users') {
                var confirmMsg = 'Are you sure you want to export this data?';
                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            if (action === 'mc_quil_import_posts' || action === 'mc_quil_import_users') {
                var fileInput = $(this).find('input[type="file"]');
                if (fileInput.length && !fileInput.val()) {
                    alert('Please select a JSON file to import.');
                    e.preventDefault();
                    return false;
                }
                
                var confirmMsg = 'Import will add new content. Existing content will not be overwritten. Continue?';
                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Show/hide options based on post type selection
        $('#post_type').on('change', function() {
            var postType = $(this).val();
            
            if (postType === 'product') {
                $('input[name="include_variations"]').closest('.quil-form-group').show();
            } else {
                $('input[name="include_variations"]').prop('checked', false);
                $('input[name="include_variations"]').closest('.quil-form-group').hide();
            }
        }).trigger('change');
        
    });
    
})(jQuery);