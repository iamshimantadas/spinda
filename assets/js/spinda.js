/**
 * Spinda Admin JavaScript
 *
 * @package Spinda
 */

(function ($) {
    'use strict';

    // DOM ready
    $(document).ready(function () {
        spineximInitExport();
        spineximInitImport();
        spineximInitProgressRefresh();
        spineximInitPostExport();
        spineximInitPostImport();
        spineximInitTaxonomiesExport();
        spineximInitTaxonomiesImport();
        spineximInitUsersExport();
        spineximInitUsersImport();
    });

    /**
     * Initialize WooCommerce products export functionality
     */
    function spineximInitExport() {
        var $exportForm = $('#spinexim-export-form');
        var $exportButton = $('#spinexim-export-button');
        var $progress = $('#spinexim-export-progress');

        if (!$exportForm.length) {
            return;
        }

        $exportForm.on('submit', function () {
            // Show progress indicator
            $exportButton.prop('disabled', true);

            // Allow form submission
            setTimeout(function () {
                $exportButton.prop('disabled', false);
            }, 3000);
        });
    }

    /**
     * Initialize WooCommerce products import functionality
     */
    function spineximInitImport() {
        var $importForm = $('#spinexim-import-form');
        var $importButton = $('#spinexim-import-button');
        var $resetImport = $('#spinexim-reset-import');

        if (!$importForm.length) {
            return;
        }

        // Confirm before import
        $importForm.on('submit', function () {
            if (!confirm(spineximData.i18n.confirm_import)) {
                return false;
            }

            $importButton.prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
                    spineximData.i18n.processing);

            return true;
        });

        // Confirm before reset
        if ($resetImport.length) {
            $resetImport.on('click', function (e) {
                if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to reset all running imports?')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }
    }

    /**
     * Auto-refresh during import progress
     */
    function spineximInitProgressRefresh() {
        var $progressFill = $('.spinexim-progress-fill');
        var $importNotice = $('.spinexim-import-notice');

        if ($importNotice.length && $progressFill.length) {
            var currentWidth = $progressFill.width();
            var parentWidth = $progressFill.parent().width();

            if (currentWidth < parentWidth) {
                // Refresh page every 5 seconds during import
                setTimeout(function () {
                    window.location.reload();
                }, 5000);
            }
        }
    }

    /**
     * Initialize Post Types Export functionality
     */
    function spineximInitPostExport() {
        var $postTypeSelect = $('#spinexim-export-post-type');
        var $taxonomiesSection = $('#spinexim-taxonomies-section');
        var $taxonomyCheckboxes = $('#spinexim-taxonomy-checkboxes');
        var $optionsSection = $('#spinexim-export-options-section');
        var $submitSection = $('#spinexim-export-submit-section');
        var $exportButton = $('#spinexim-post-export-button');

        if (!$postTypeSelect.length) {
            return;
        }

        // Handle post type selection change
        $postTypeSelect.on('change', function () {
            var postType = $(this).val();

            if (postType) {
                // Show loading state
                $taxonomiesSection.show();
                $taxonomyCheckboxes.html(
                    '<p class="spinexim-loading">' +
                    '<span class="spinexim-spinner"></span> ' +
                    spineximData.i18n.loading_taxonomies + '</p>'
                );

                // AJAX to get taxonomies
                $.ajax({
                    url: spineximData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spinexim_get_taxonomies',
                        post_type: postType,
                        nonce: spineximData.post_export_nonce
                    },
                    success: function (response) {
                        if (response.success && response.data.length > 0) {
                            var html = '<label class="spinexim-tax-select-all">' +
                                '<input type="checkbox" id="spinexim-select-all-tax" checked> ' +
                                '<strong>' + spineximData.i18n.select_all + '</strong></label><br><br>';

                            $.each(response.data, function (index, tax) {
                                html += '<label class="spinexim-tax-label">';
                                html += '<input type="checkbox" name="spinexim_export_taxonomies[]" ' +
                                    'value="' + spineximEscapeHtml(tax.name) + '" checked ' +
                                    'class="spinexim-tax-checkbox"> ';
                                html += '<strong>' + spineximEscapeHtml(tax.label) + '</strong>';
                                html += ' <span class="spinexim-tax-count">(' + tax.count + ' ' +
                                    spineximData.i18n.terms + ')</span>';
                                if (tax.hierarchical) {
                                    html += ' <span class="spinexim-tax-hierarchical">(' +
                                        spineximData.i18n.hierarchical + ')</span>';
                                }
                                html += '</label> ';
                            });

                            $taxonomyCheckboxes.html(html);

                            // Select all toggle
                            $('#spinexim-select-all-tax').on('change', function () {
                                $('.spinexim-tax-checkbox').prop('checked', $(this).prop('checked'));
                            });

                            // Update individual checkboxes when any is changed
                            $('.spinexim-tax-checkbox').on('change', function () {
                                updateSelectAllCheckbox();
                            });
                        } else {
                            $taxonomyCheckboxes.html(
                                '<p>' + spineximData.i18n.no_taxonomies + '</p>'
                            );
                        }
                    },
                    error: function () {
                        $taxonomyCheckboxes.html(
                            '<p class="spinexim-error-text">' +
                            spineximData.i18n.error_loading_taxonomies + '</p>'
                        );
                    }
                });

                // Show remaining sections
                $optionsSection.show();
                $submitSection.show();
            } else {
                // Hide sections if no post type selected
                $taxonomiesSection.hide();
                $optionsSection.hide();
                $submitSection.hide();
            }
        });

        // Handle export form submission
        if ($exportButton.length) {
            $exportButton.closest('form').on('submit', function () {
                $exportButton.prop('disabled', true)
                    .find('.dashicons')
                    .removeClass('dashicons-download')
                    .addClass('dashicons-update dashicons-update-spin');
            });
        }

        /**
         * Update select all checkbox state
         */
        function updateSelectAllCheckbox() {
            var $selectAll = $('#spinexim-select-all-tax');
            var $checkboxes = $('.spinexim-tax-checkbox');
            var totalCheckboxes = $checkboxes.length;
            var checkedCheckboxes = $checkboxes.filter(':checked').length;

            if (checkedCheckboxes === 0) {
                $selectAll.prop('checked', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $selectAll.prop('checked', true);
            } else {
                $selectAll.prop('checked', false);
            }
        }
    }

    /**
     * Initialize Post Types Import functionality
     */
    // function spineximInitPostImport() {
    //     var $importForm = $('#spinexim-post-import-form');
    //     var $importButton = $('#spinexim-post-import-button');
    //     var $resetButton = $('#spinexim-post-reset-import');
    //     var $fileInput = $('.spinexim-file-input');

    //     if (!$importForm.length) {
    //         return;
    //     }

    //     // File input change handler
    //     if ($fileInput.length) {
    //         $fileInput.on('change', function() {
    //             var fileName = $(this).val().split('\\').pop();
    //             if (fileName) {
    //                 $(this).css('border-color', '#2271b1');
    //             }
    //         });
    //     }

    //     // Confirm before import
    //     $importForm.on('submit', function() {
    //         var fileSelected = $fileInput.val() !== '';

    //         if (!fileSelected) {
    //             alert(spineximData.i18n.select_file || 'Please select a file to import.');
    //             return false;
    //         }

    //         if (!confirm(spineximData.i18n.confirm_post_import || 'Are you sure you want to start the import? This may take a while for large files.')) {
    //             return false;
    //         }

    //         $importButton.prop('disabled', true)
    //             .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> ' + 
    //                    spineximData.i18n.processing);

    //         return true;
    //     });

    //     // Confirm before reset
    //     if ($resetButton.length) {
    //         $resetButton.on('click', function(e) {
    //             if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to reset all running imports?')) {
    //                 e.preventDefault();
    //                 return false;
    //             }
    //             return true;
    //         });
    //     }
    // }
    function spineximInitPostImport() {
        var $importForm = $('#spinexim-post-import-form');
        var $importButton = $('#spinexim-post-import-button');
        var $resetButton = $('#spinexim-reset-import');
        var $fileInput = $('.spinexim-file-input');
        var $stopButton = $('#spinexim-stop-post-import');

        if (!$importForm.length) {
            return;
        }

        // File input change handler
        if ($fileInput.length) {
            $fileInput.on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $(this).css('border-color', '#2271b1');
                }
            });
        }

        // Confirm before import
        $importForm.on('submit', function (e) {
            var fileSelected = $fileInput.val() !== '';

            if (!fileSelected) {
                e.preventDefault();
                alert(spineximData.i18n.select_file || 'Please select a file to import.');
                return false;
            }

            if (!confirm(spineximData.i18n.confirm_post_import || 'Are you sure you want to start the import? This may take a while for large files.')) {
                e.preventDefault();
                return false;
            }

            $importButton.prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
                    spineximData.i18n.processing);

            return true;
        });

        // Stop import button
        if ($stopButton.length) {
            $stopButton.on('click', function (e) {
                e.preventDefault();
                if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to stop this import?')) {
                    return false;
                }

                var $btn = $(this);
                $btn.prop('disabled', true)
                    .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> Stopping...');

                $.ajax({
                    url: spineximData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spinexim_stop_post_import',
                        nonce: spineximData.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.data.message || 'Failed to stop import.');
                            $btn.prop('disabled', false).html('Stop Import');
                        }
                    },
                    error: function () {
                        alert('Failed to stop import. Please try again.');
                        $btn.prop('disabled', false).html('Stop Import');
                    }
                });
            });
        }

        // Confirm before reset
        if ($resetButton.length) {
            $resetButton.on('click', function (e) {
                if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to reset all running imports?')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }
    }

    /**
     * Initialize Taxonomies Export functionality
     */
    function spineximInitTaxonomiesExport() {
        var $postTypeSelect = $('#spinexim-tax-export-post-type');
        var $taxonomiesSection = $('#spinexim-taxonomies-selection-section');
        var $taxonomyCheckboxes = $('#spinexim-taxonomy-selection-checkboxes');
        var $optionsSection = $('#spinexim-tax-export-options-section');
        var $submitSection = $('#spinexim-tax-export-submit-section');
        var $exportButton = $('#spinexim-tax-export-button');

        if (!$postTypeSelect.length) {
            return;
        }

        // Handle post type selection change
        $postTypeSelect.on('change', function () {
            var postType = $(this).val();

            if (postType) {
                // Show loading state
                $taxonomiesSection.show();
                $taxonomyCheckboxes.html(
                    '<p class="spinexim-loading">' +
                    '<span class="spinexim-spinner"></span> ' +
                    spineximData.i18n.loading_taxonomies + '</p>'
                );

                // AJAX to get taxonomies
                $.ajax({
                    url: spineximData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spinexim_get_taxonomies_for_export',
                        post_type: postType,
                        nonce: spineximData.tax_export_nonce
                    },
                    success: function (response) {
                        if (response.success && response.data.length > 0) {
                            var html = '<label class="spinexim-tax-select-all">' +
                                '<input type="checkbox" id="spinexim-select-all-taxonomies" checked> ' +
                                '<strong>' + spineximData.i18n.select_all + '</strong></label><br><br>';

                            $.each(response.data, function (index, tax) {
                                html += '<label class="spinexim-tax-label">';
                                html += '<input type="checkbox" name="spinexim_export_taxonomies[]" ' +
                                    'value="' + spineximEscapeHtml(tax.name) + '" checked ' +
                                    'class="spinexim-tax-checkbox"> ';
                                html += '<strong>' + spineximEscapeHtml(tax.label) + '</strong>';
                                html += ' <span class="spinexim-tax-count">(' + tax.count + ' ' +
                                    spineximData.i18n.terms + ')</span>';
                                if (tax.hierarchical) {
                                    html += ' <span class="spinexim-tax-hierarchical">(' +
                                        spineximData.i18n.hierarchical + ')</span>';
                                }
                                html += '</label> ';
                            });

                            $taxonomyCheckboxes.html(html);

                            // Select all toggle
                            $('#spinexim-select-all-taxonomies').on('change', function () {
                                $('.spinexim-tax-checkbox').prop('checked', $(this).prop('checked'));
                            });

                            // Update individual checkboxes when any is changed
                            $('.spinexim-tax-checkbox').on('change', function () {
                                updateSelectAllTaxonomiesCheckbox();
                            });
                        } else {
                            $taxonomyCheckboxes.html(
                                '<p>' + spineximData.i18n.no_taxonomies + '</p>'
                            );
                        }
                    },
                    error: function () {
                        $taxonomyCheckboxes.html(
                            '<p class="spinexim-error-text">' +
                            spineximData.i18n.error_loading_taxonomies + '</p>'
                        );
                    }
                });

                // Show remaining sections
                $optionsSection.show();
                $submitSection.show();
            } else {
                // Hide sections if no post type selected
                $taxonomiesSection.hide();
                $optionsSection.hide();
                $submitSection.hide();
            }
        });

        // Handle export form submission
        if ($exportButton.length) {
            $exportButton.closest('form').on('submit', function () {
                $exportButton.prop('disabled', true)
                    .find('.dashicons')
                    .removeClass('dashicons-download')
                    .addClass('dashicons-update dashicons-update-spin');
            });
        }

        /**
         * Update select all checkbox state
         */
        function updateSelectAllTaxonomiesCheckbox() {
            var $selectAll = $('#spinexim-select-all-taxonomies');
            var $checkboxes = $('.spinexim-tax-checkbox');
            var totalCheckboxes = $checkboxes.length;
            var checkedCheckboxes = $checkboxes.filter(':checked').length;

            if (checkedCheckboxes === 0) {
                $selectAll.prop('checked', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $selectAll.prop('checked', true);
            } else {
                $selectAll.prop('checked', false);
            }
        }
    }

    /**
     * Initialize Taxonomies Import functionality
     */
    function spineximInitTaxonomiesImport() {
        var $importForm = $('#spinexim-taxonomies-import-form');
        var $importButton = $('#spinexim-taxonomies-import-button');
        var $resetButton = $('#spinexim-reset-tax-import');
        var $fileInput = $('.spinexim-file-input');

        if (!$importForm.length) {
            return;
        }

        // File input change handler
        if ($fileInput.length) {
            $fileInput.on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $(this).css('border-color', '#2271b1');
                }
            });
        }

        // Confirm before import
        $importForm.on('submit', function () {
            var fileSelected = $fileInput.val() !== '';

            if (!fileSelected) {
                alert(spineximData.i18n.select_file || 'Please select a file to import.');
                return false;
            }

            if (!confirm(spineximData.i18n.confirm_tax_import || 'Are you sure you want to start the taxonomies import? This may take a while for large files.')) {
                return false;
            }

            $importButton.prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
                    spineximData.i18n.processing);

            return true;
        });

        // Confirm before reset
        if ($resetButton.length) {
            $resetButton.on('click', function (e) {
                if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to reset all running imports?')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }
    }

    /**
     * Initialize Users Export functionality
     */
    function spineximInitUsersExport() {
        var $exportForm = $('#spinexim-users-export-form');
        var $exportButton = $('#spinexim-users-export-button');

        if (!$exportForm.length) {
            return;
        }

        // Select all roles toggle
        $('#spinexim-select-all-roles').on('change', function () {
            $('.spinexim-role-checkbox').prop('checked', $(this).prop('checked'));
        });

        $('.spinexim-role-checkbox').on('change', function () {
            var $selectAll = $('#spinexim-select-all-roles');
            var $checkboxes = $('.spinexim-role-checkbox');
            var totalCheckboxes = $checkboxes.length;
            var checkedCheckboxes = $checkboxes.filter(':checked').length;

            if (checkedCheckboxes === 0) {
                $selectAll.prop('checked', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $selectAll.prop('checked', true);
            } else {
                $selectAll.prop('checked', false);
            }
        });

        // Handle export form submission
        if ($exportButton.length) {
            $exportButton.closest('form').on('submit', function () {
                $exportButton.prop('disabled', true)
                    .find('.dashicons')
                    .removeClass('dashicons-download')
                    .addClass('dashicons-update dashicons-update-spin');
            });
        }
    }

    /**
     * Initialize Users Import functionality
     */
    function spineximInitUsersImport() {
        var $importForm = $('#spinexim-users-import-form');
        var $importButton = $('#spinexim-users-import-button');
        var $resetButton = $('#spinexim-reset-users-import');
        var $fileInput = $('.spinexim-file-input');

        if (!$importForm.length) {
            return;
        }

        // File input change handler
        if ($fileInput.length) {
            $fileInput.on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $(this).css('border-color', '#2271b1');
                }
            });
        }

        // Confirm before import
        $importForm.on('submit', function () {
            var fileSelected = $fileInput.val() !== '';

            if (!fileSelected) {
                alert(spineximData.i18n.select_file || 'Please select a file to import.');
                return false;
            }

            if (!confirm(spineximData.i18n.confirm_user_import || 'Are you sure you want to start the users import? This may take a while for large files.')) {
                return false;
            }

            $importButton.prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
                    spineximData.i18n.processing);

            return true;
        });

        // Confirm before reset
        if ($resetButton.length) {
            $resetButton.on('click', function (e) {
                if (!confirm(spineximData.i18n.confirm_reset || 'Are you sure you want to reset all running imports?')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }
    }

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} text Text to escape
     * @return {string} Escaped text
     */
    function spineximEscapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

})(jQuery);